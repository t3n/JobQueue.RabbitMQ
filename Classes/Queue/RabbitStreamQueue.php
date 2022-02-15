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

        // Increase stream offset after finishing message
        $streamOffset = $this->streamOffsetService->fetch($this->name, $this->consumerTag);
        $this->streamOffsetService->store($this->name, $this->consumerTag, $streamOffset + 1);

        return true;
    }

    /**
     * @param string|int $offset
     * @return void
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

        /** @var AMQPTable $applicationHeader */
        $applicationHeader = $message->get('application_headers')->getNativeData();

        $streamOffset = ObjectAccess::getPropertyPath($applicationHeader, 'x-stream-offset');

        $this->streamOffsetService->store(
            $this->name,
            $this->consumerTag,
            $streamOffset ?? 1
        );

        return parent::handleMessage($deliveryTag, $message);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function getStreamOffsetForBasicConsume(): array
    {
        $offset = $this->streamOffsetService->fetch($this->name, $this->consumerTag);

        return ['x-stream-offset' => [is_int($offset) ? 'I' : 'S', $offset]];
    }
}
