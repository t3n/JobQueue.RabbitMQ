<?php

declare(strict_types=1);

namespace t3n\JobQueue\RabbitMQ\Command;

use Flowpack\JobQueue\Common\Queue\QueueManager;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\Cli\CommandController;
use t3n\JobQueue\RabbitMQ\Queue\RabbitStreamQueue;

/**
 * @Flow\Scope("singleton")
 */
class RabbitQueueCommandController extends CommandController
{
    /**
     * @Flow\Inject
     *
     * @var QueueManager
     */
    protected $queueManager;

    /**
     * Sets the stream offset of a RabbitStreamQueue to the maximum value, possibly skipping messages.
     *
     * Check https://www.rabbitmq.com/streams.html#consuming
     *
     * @param string $queue Queue to set Stream offset for
     * @param string $offset The offset to store
     */
    public function setOffsetForStreamCommand(string $queue, string $offset): void
    {
        $queueImpl = $this->queueManager->getQueue($queue);
        if (! $queueImpl instanceof RabbitStreamQueue) {
            $this->outputLine('<error>Setting stream offset is only available for RabbitStreamQueues!</error>');
            $this->quit(1);
        }

        $queueImpl->setOffset($offset);
        $this->outputLine('<success>Set offset to "%s" for stream "%s!</success>', [
            $offset,
            $queueImpl->getName(),
        ]);
    }
}
