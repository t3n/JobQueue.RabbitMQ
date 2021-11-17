<?php

declare(strict_types=1);

namespace t3n\JobQueue\RabbitMQ;

use Flowpack\JobQueue\Common\Job\JobManager;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;
use t3n\JobQueue\RabbitMQ\Service\ReleaseService;

class Package extends BasePackage
{
    public function boot(Bootstrap $bootstrap): void
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();
        $dispatcher->connect(JobManager::class, 'messageReleased', ReleaseService::class, 'reQueueMessage');
    }
}
