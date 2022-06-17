<?php

declare(strict_types=1);

namespace t3n\JobQueue\RabbitMQ\Queue;

use Flowpack\JobQueue\Common\Queue\Message;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\ObjectAccess;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use t3n\JobQueue\RabbitMQ\Service\StreamOffsetService;

class RabbitStreamQueue extends RabbitQueue
{
    /**
     * @Flow\Inject
     *
     * @var StreamOffsetService
     */
    protected $streamOffsetService;

    /**
     * @var int
     */
    private $offset;

    /**
     * @var int
     */
    private $offsetFromPreviousMessage;

    public function waitAndTake(?int $timeout = null): ?Message
    {
        return $this->dequeue(true, $timeout, $this->getStreamOffsetForBasicConsume());
    }

    public function waitAndReserve(?int $timeout = null): ?Message
    {
        return $this->dequeue(false, $timeout, $this->getStreamOffsetForBasicConsume());
    }

    /**
     * @param array<string, mixed> $releaseOptions
     */
    public function reQueueMessage(Message $message, array $releaseOptions): void
    {
        // Streams should never requeue messages
    }

    /**
     * @param array<string, mixed> $options
     */
    public function release(string $messageId, array $options = []): void
    {
        throw new \RuntimeException(
            sprintf(
                'release is not supported by RabbitMQ Streams. Make sure to set maximumNumberOfReleases to 0 for Queue "%s"!',
                $this->name
            ),
            1637577003
        );
    }

    public function abort(string $messageId): void
    {
        // nack is not supported for Streams, so ack instead
        $this->finish($messageId);
    }

    public function finish(string $messageId): bool
    {
        parent::finish($messageId);

        if (is_int($this->offset)) {
            $this->offset++;

            if ($this->offset % 200 === 0) {
                $this->streamOffsetService->store(
                    $this->name,
                    $this->consumerTag,
                    $this->offset
                );
            }
        } else {
            $this->offset = $this->offsetFromPreviousMessage;
        }

        return true;
    }

    /**
     * @return string|int
     */
    public function getOffset()
    {
        if (is_int($this->offset)) {
            return $this->offset;
        }

        return $this->streamOffsetService->fetch($this->name, $this->consumerTag);
    }

    /**
     * @param string|int $offset
     */
    public function setOffset($offset): void
    {
        $this->streamOffsetService->store(
            $this->name,
            $this->consumerTag,
            $offset
        );
    }

    protected function handleMessage(string $deliveryTag, AMQPMessage $message): Message
    {
        // Update current stream offset

        if (!is_int($this->offset)) {
            /** @var AMQPTable $applicationHeader */
            $applicationHeader = $message->get('application_headers')->getNativeData();
            $streamOffset = ObjectAccess::getPropertyPath($applicationHeader, 'x-stream-offset');
            $this->offsetFromPreviousMessage = $streamOffset ?? 1;
        }

        return parent::handleMessage($deliveryTag, $message);
    }

    public function shutdownObject(): void
    {
        if (is_int($this->offset)) {
            $this->streamOffsetService->store(
                $this->name,
                $this->consumerTag,
                $this->offset
            );
        }
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function getStreamOffsetForBasicConsume(): array
    {
        $offset = $this->getOffset();

        return ['x-stream-offset' => [is_int($offset) ? 'I' : 'S', $offset]];
    }
}
