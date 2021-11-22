<?php

declare(strict_types=1);

namespace t3n\JobQueue\RabbitMQ\Tests\Unit\Service;

use Flowpack\JobQueue\Common\Queue\FakeQueue;
use Flowpack\JobQueue\Common\Queue\Message;
use Neos\Flow\Tests\UnitTestCase;
use t3n\JobQueue\RabbitMQ\Queue;
use t3n\JobQueue\RabbitMQ\Service\ReleaseService;

class ReleaseServiceTest extends UnitTestCase
{

    /**
     * @var ReleaseService
     */
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ReleaseService();
    }

    /**
     * @test
     * @dataProvider provideRabbitQueueNames
     */
    public function reQueueMessage_is_called_for_RabbitQueues(string $className): void
    {
        $queue = $this->getMockBuilder($className)
            ->disableOriginalConstructor()
            ->getMock();

        $message = new Message('87cd5e52-b019-4f7a-b319-afd1631f73a6', 'Hello World!', 3);
        $releaseOptions = ['foo' => 'bar', 'baz' => 10];

        $queue
            ->expects(self::once())
            ->method('reQueueMessage')
            ->with(
                $message,
                $releaseOptions
            );

        $this->service->reQueueMessage($queue, $message, $releaseOptions);
    }

    /**
     * @test
     */
    public function reQueueMessage_is_called_for_other_Queue_Implementations(): void
    {
        $queue = $this->getMockBuilder(FakeQueue::class)
            ->disableOriginalConstructor()
            ->setMethods(['reQueueMessage'])
            ->getMock();

        $message = new Message('87cd5e52-b019-4f7a-b319-afd1631f73a6', 'Hello World!', 3);
        $releaseOptions = ['foo' => 'bar', 'baz' => 10];

        $queue
            ->expects(self::never())
            ->method('reQueueMessage');

        $this->service->reQueueMessage($queue, $message, $releaseOptions);
    }

    /**
     * @return iterable<string, string>
     */
    public function provideRabbitQueueNames(): iterable
    {
        yield Queue\RabbitQueue::class => ['className' => Queue\RabbitQueue::class];
        yield Queue\RabbitQueueWithDlx::class => ['className' => Queue\RabbitQueueWithDlx::class];
        yield Queue\RabbitStreamQueue::class => ['className' => Queue\RabbitStreamQueue::class];
    }

}