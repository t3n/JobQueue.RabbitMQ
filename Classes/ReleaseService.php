<?php

declare(strict_types=1);

namespace t3n\JobQueue\RabbitMQ;

use Flowpack\JobQueue\Common\Queue\Message;
use Flowpack\JobQueue\Common\Queue\QueueInterface;
use Flowpack\JobQueue\Common\Queue\QueueManager;
use Neos\Flow\Annotations as Flow;
use t3n\JobQueue\RabbitMQ\Queue\RabbitQueue;

/**
 * @Flow\Scope("singleton")
 */
class ReleaseService
{
    /**
     * @Flow\Inject()
     *
     * @var QueueManager
     */
    protected $queueManager;

    /**
     * @param mixed[] $releaseOptions
     */
    public function reQueueMessage(QueueInterface $queue, Message $message, array $releaseOptions): void
    {
        /** @var RabbitQueue $queue */
        $queue->reQueueMessage($message, $releaseOptions);
    }
}
