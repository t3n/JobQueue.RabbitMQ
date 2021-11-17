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
     * @var StreamOffsetService
     * @Flow\Inject
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

    protected function handleMessage(string $deliveryTag, AMQPMessage $message): Message
    {
        /** @var AMQPTable $applicationHeader */
        $applicationHeader = $message->get('application_headers')->getNativeData();

        $streamOffset = ObjectAccess::getPropertyPath($applicationHeader, 'x-stream-offset');
        $this->streamOffsetService->store(
            $this->name,
            $this->consumerTag,
            $streamOffset !== null ? $streamOffset + 1 : 1
        );

        return new Message($deliveryTag, json_decode($message->body, true));
    }

    protected function getStreamOffsetForBasicConsume(): array
    {
        return ['x-stream-offset' => ['I', $this->streamOffsetService->fetch($this->name, $this->consumerTag)]];
    }

}
