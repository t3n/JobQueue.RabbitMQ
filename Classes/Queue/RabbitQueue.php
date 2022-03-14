<?php

declare(strict_types=1);

namespace t3n\JobQueue\RabbitMQ\Queue;

use Flowpack\JobQueue\Common\Exception as JobQueueException;
use Flowpack\JobQueue\Common\Queue\Message;
use Flowpack\JobQueue\Common\Queue\QueueInterface;
use Neos\Utility\ObjectAccess;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitQueue implements QueueInterface
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var AMQPStreamConnection
     */
    protected $connection;

    /**
     * @var int
     */
    protected $defaultTimeout = null;

    /**
     * @var AMQPChannel
     */
    protected $channel;

    /**
     * @var string
     */
    protected $queueName = '';

    /**
     * @var string
     */
    protected $exchangeName = '';

    /**
     * @var string
     */
    protected $routingKey = '';

    /**
     * @var bool
     */
    protected $useDLX = false;

    /**
     * @param mixed[] $options
     */
    public function __construct(string $name, array $options = [])
    {
        $this->name = $name;
        // Create a connection
        $clientOptions = $options['client'] ?? [];
        $host = $clientOptions['host'] ?? 'localhost';
        $port = $clientOptions['port'] ?? 5672;
        $username = $clientOptions['username'] ?? 'guest';
        $password = $clientOptions['password'] ?? 'guest';
        $vhost = $clientOptions['vhost'] ?? '/';
        $insist = isset($clientOptions['insist']) ? (bool) $clientOptions['insist'] : false;
        $loginMethod = isset($clientOptions['loginMethod']) ? (string) $clientOptions['loginMethod'] : 'AMQPLAIN';
        $heartbeat = (int) ($clientOptions['heartbeat'] ?? 0);

        $this->connection = new AMQPStreamConnection(
            $host,
            $port,
            $username,
            $password,
            $vhost,
            $insist,
            $loginMethod,
            null,
            'en_US',
            3.0,
            3.0,
            null,
            true,
            $heartbeat
        );
        $this->channel = $this->connection->channel();

        // a worker should only get one message at a time
        $this->channel->basic_qos(null, 1, null);

        // declare exchange
        if (isset($options['exchange']) && ! empty($options['exchange'])) {
            $exchangeOptions = $options['exchange'];

            $this->exchangeName = $exchangeOptions['name'] ?? '';

            $type = $exchangeOptions['type'] ?? 'direct';
            $passive = isset($exchangeOptions['passive']) ? (bool) $exchangeOptions['passive'] : false;
            $durable = isset($exchangeOptions['durable']) ? (bool) $exchangeOptions['durable'] : false;
            $autoDelete = isset($exchangeOptions['autoDelete']) ? (bool) $exchangeOptions['autoDelete'] : true;
            $internal = isset($exchangeOptions['internal']) ? (bool) $exchangeOptions['internal'] : false;
            $nowait = isset($exchangeOptions['nowait']) ?(bool) $exchangeOptions['nowait'] : false;

            $exchangeArguments = [];
            if (isset($exchangeOptions['arguments']) && ! empty($exchangeOptions['arguments'])) {
                $exchangeArguments = new AMQPTable(['x-delayed-type' => 'topic']);
            }
            $this->channel->exchange_declare($this->exchangeName, $type, $passive, $durable, $autoDelete, $internal, $nowait, $exchangeArguments);
        }

        // declare queue
        $queueOptions = $options['queueOptions'];

        $this->queueName = $queueOptions['name'] ?? $this->name;
        $passive = isset($queueOptions['passive']) ? (bool) $queueOptions['passive'] : false;
        $durable = isset($queueOptions['durable']) ? (bool) $queueOptions['durable'] : false;
        $exclusive = isset($queueOptions['exclusive']) ? (bool) $queueOptions['exclusive'] : false;
        $autoDelete = isset($queueOptions['autoDelete']) ? (bool) $queueOptions['autoDelete'] : true;
        $nowait = isset($queueOptions['nowait']) ? (bool) $queueOptions['nowait'] : false;
        $arguments = isset($queueOptions['arguments']) ? new AMQPTable($queueOptions['arguments']) : [];

        $this->routingKey = $options['routingKey'] ?? '';

        if (isset($queueOptions['declare']) ? (bool) $queueOptions['declare'] : true) {
            $this->channel->queue_declare($this->queueName, $passive, $durable, $exclusive, $autoDelete, $nowait, $arguments);

            // bind the queue to an exchange if there is a specific set
            if ($this->exchangeName !== '') {
                $this->channel->queue_bind($this->queueName, $this->exchangeName, $this->routingKey);
            }
        }

        $this->useDLX = $options['useDLX'] ?? false;
    }

    protected function connect(): void
    {
        if (! $this->connection->isConnected()) {
            $this->connection->reconnect();
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param mixed $payload
     * @param mixed[] $options
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
     */
    public function submit($payload, array $options = []): string
    {
        return $this->queue($payload, $options);
    }

    public function waitAndTake(?int $timeout = null): ?Message
    {
        return $this->dequeue(true, $timeout);
    }

    public function waitAndReserve(?int $timeout = null): ?Message
    {
        return $this->dequeue(false, $timeout);
    }

    /**
     * @param mixed[] $options
     */
    public function release(string $messageId, array $options = []): void
    {
        // We cannot fetch a message by id from rabbit. Therefore we implement the release with a
        // workaround. We will listen on the "messageReleased" signal. This signal has the fill
        // $message available. So we will ack the origin message and queue a new message
        // with the $same payload
    }

    /**
     * Connected to the "messageReleased" Signal
     *
     * @param mixed[] $releaseOptions
     *
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.TypeHintDeclaration.MissingParameterTypeHint
     */
    public function reQueueMessage(Message $message, array $releaseOptions): void
    {
        if ($this->useDLX) {
            // Use nack to move message to DLX
            $this->channel->basic_nack($message->getIdentifier());
        } else {
            // Ack the current message
            $this->channel->basic_ack($message->getIdentifier());

            // requeue the message
            $this->queue($message->getPayload(), $releaseOptions, $message->getNumberOfReleases() + 1);
        }
    }

    /**
     * @inheritdoc
     */
    public function abort(string $messageId): void
    {
        if ($this->useDLX) {
            // basic_nack would move message to DLX, not actually removing it
            $this->channel->basic_ack($messageId);
        } else {
            $this->channel->basic_nack($messageId);
        }
    }

    /**
     * @inheritdoc
     */
    public function finish(string $messageId): bool
    {
        $this->channel->basic_ack($messageId);
        return true;
    }

    /**
     * @throws JobQueueException
     *
     * @inheritdoc
     */
    public function peek(int $limit = 1): array
    {
        throw new JobQueueException('Not implemented');
    }

    /**
     * @inheritdoc
     */
    public function count()
    {
        return (int) $this->channel->queue_declare($this->queueName, true)[1];
    }

    public function setUp(): void
    {
    }

    public function countReady(): int
    {
        return $this->count();
    }

    public function countFailed(): int
    {
        return 0;
    }

    public function countReserved(): int
    {
        return 0;
    }

    /**
     * @inheritdoc
     */
    public function flush(): void
    {
        $this->connect();
        $this->channel->queue_purge($this->queueName);
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->connection->close();
    }

    /**
     * @param mixed[] $options
     */
    protected function queue(string $payload, array $options = [], int $numberOfReleases = 0): string
    {
        $this->connect();

        $correlationIdentifier = \uniqid('', true);
        $mergedOptions = array_merge($options, ['correlation_id' => $correlationIdentifier, 'numberOfReleases' => 0]);

        $message = new AMQPMessage(json_encode($payload), $mergedOptions);

        $headerOptions = ['x-numberOfReleases' => $numberOfReleases];

        if (array_key_exists('delay', $options)) {
            // RabbitMQ handles delay in ms
            $headerOptions['x-delay'] = $options['delay']  * 1000;
        }

        $headers = new AMQPTable($headerOptions);
        $message->set('application_headers', $headers);
        $this->channel->basic_publish($message, $this->exchangeName, $this->routingKey);
        return $correlationIdentifier;
    }

    protected function dequeue(bool $ack = true, ?int $timeout = null): ?Message
    {
        $this->connect();

        $cache = null;
        $consumerTag = $this->channel->basic_consume($this->queueName, '', false, false, false, false, function (AMQPMessage $message) use (&$cache, $ack): void {
            $deliveryTag = (string) $message->delivery_info['delivery_tag'];

            /** @var AMQPTable $applicationHeader */
            $applicationHeader = $message->get('application_headers')->getNativeData();
            if ($this->useDLX) {
                $numberOfReleases = ObjectAccess::getPropertyPath($applicationHeader, 'x-death.0.count') ?? 0;
            } else {
                $numberOfReleases = $applicationHeader['x-numberOfReleases'] ?? 0;
            }

            if ($ack) {
                $this->channel->basic_ack($deliveryTag);
            }
            $cache = new Message($deliveryTag, json_decode($message->body, true), $numberOfReleases);
        });

        while ($cache === null) {
            try {
                $this->channel->wait(null, false, $timeout ?: 0);
            } catch (AMQPTimeoutException $e) {
                return null;
            }
        }

        $this->channel->basic_cancel($consumerTag);
        return $cache;
    }
}
