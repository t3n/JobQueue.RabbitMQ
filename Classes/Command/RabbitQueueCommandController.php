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
     * Gets the stream offset of a RabbitStreamQueue.
     *
     * @param string $queue Queue to set Stream offset for
     */
    public function getOffsetForStreamCommand(string $queue): void
    {
        $queueImpl = $this->queueManager->getQueue($queue);
        if (! $queueImpl instanceof RabbitStreamQueue) {
            $this->outputLine('<error>Setting stream offset is only available for RabbitStreamQueues!</error>');
            $this->quit(1);
        }

        $offset = $queueImpl->getOffset();
        $this->outputLine('<success>Offset for stream "%s" is "%s"</success>', [
            $queueImpl->getName(),
            $offset,
        ]);
    }

    /**
     * Sets the stream offset of a RabbitStreamQueue to the maximum value, possibly skipping messages.
     *
     * Check https://www.rabbitmq.com/streams.html#consuming
     *
     * @param string $queue Queue to set Stream offset for
     * @param string $offset The offset to store
     * @param string $type The data type of the offset. Can be int or string
     */
    public function setOffsetForStreamCommand(string $queue, string $offset, string $type = 'int'): void
    {
        if (!in_array($type, ['int', 'string'], true)) {
            $this->outputLine('<error>Type must be either "int" or "string", "%s" given!', [$type]);
            $this->quit(1);
        }

        $queueImpl = $this->queueManager->getQueue($queue);
        if (! $queueImpl instanceof RabbitStreamQueue) {
            $this->outputLine('<error>Setting stream offset is only available for RabbitStreamQueues!</error>');
            $this->quit(1);
        }

        if ($type === 'int') {
            if (((string)intval($offset)) !== $offset) {
                $this->outputLine('<error>Offset "%s" cannot be cast to int! Use "--type=string" for relative offsets.', [$offset]);
                $this->quit(1);
            }

            $offset = intval($offset);
        }

        $queueImpl->setOffset($offset);
        $this->outputLine('<success>Set offset to "%s" for stream "%s"</success>', [
            $offset,
            $queueImpl->getName(),
        ]);
    }
}
