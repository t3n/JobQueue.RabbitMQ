<?php

declare(strict_types=1);

namespace t3n\JobQueue\RabbitMQ;

use Flowpack\JobQueue\Common\Queue\Message;
use Neos\Flow\Annotations as Flow;
use t3n\JobQueue\RabbitMQ\Queue\RabbitQueue;

/**
 * @Flow\Scope("singleton")
 */
class ReleaseService
{
    /**
     * @param mixed[] $releaseOptions
     */
    public function reQueueMessage(RabbitQueue $queue, Message $message, array $releaseOptions): void
    {
        $queue->reQueueMessage($message, $releaseOptions);
    }
}
