<?php
declare(strict_types=1);

namespace t3n\JobQueue\RabbitMQ\Queue;

use Flowpack\JobQueue\Common\Queue\Message;
use Neos\Utility\ObjectAccess;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class RabbitQueueWithDlx extends RabbitQueue
{

    /**
     * @inheritdoc
     */
    public function reQueueMessage(Message $message, array $releaseOptions): void
    {
        // Use nack to move message to DLX
        $this->channel->basic_nack($message->getIdentifier());
    }

    /**
     * @inheritdoc
     */
    public function abort(string $messageId): void
    {
        // basic_nack would move message to DLX, not actually removing it
        $this->channel->basic_ack($messageId);
    }

    protected function handleMessage(string $deliveryTag, AMQPMessage $message): Message
    {
        /** @var AMQPTable $applicationHeader */
        $applicationHeader = $message->get('application_headers')->getNativeData();

        $numberOfReleases = ObjectAccess::getPropertyPath($applicationHeader, 'x-death.0.count') ?? 0;

        return new Message($deliveryTag, json_decode($message->body, true), $numberOfReleases);
    }

}
