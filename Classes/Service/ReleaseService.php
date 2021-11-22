<?php

declare(strict_types=1);

namespace t3n\JobQueue\RabbitMQ\Service;

use Flowpack\JobQueue\Common\Queue\Message;
use Flowpack\JobQueue\Common\Queue\QueueInterface;
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
    public function reQueueMessage(QueueInterface $queue, Message $message, array $releaseOptions): void
    {
        if (! $queue instanceof RabbitQueue) {
            return;
        }

        $queue->reQueueMessage($message, $releaseOptions);
    }
}
