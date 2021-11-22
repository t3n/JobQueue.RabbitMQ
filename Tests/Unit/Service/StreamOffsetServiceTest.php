<?php

declare(strict_types=1);

namespace t3n\JobQueue\RabbitMQ\Tests\Unit\Service;

use Neos\Cache\Backend\TransientMemoryBackend;
use Neos\Cache\Frontend\FrontendInterface;
use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Tests\UnitTestCase;
use t3n\JobQueue\RabbitMQ\Service\StreamOffsetService;

class StreamOffsetServiceTest extends UnitTestCase
{

    /**
     * @var StreamOffsetService
     */
    private $service;

    /**
     * @var FrontendInterface
     */
    private $cache;

    public function setUp(): void
    {
        parent::setUp();

        $backend = new TransientMemoryBackend();
        $this->cache = new VariableFrontend('foo', $backend);
        $backend->setCache($this->cache);

        $this->service = new StreamOffsetService();
        $this->service->injectCache($this->cache);
    }

    /**
     * @test
     */
    public function Stream_offset_can_be_stored_and_retrieved(): void
    {
        $name = 'foo';
        $consumerTag = 'bar';
        $offset = 35;

        $this->service->store($name, $consumerTag, $offset);

        $this->assertSame($offset, $this->service->fetch($name, $consumerTag));
    }

    /**
     * @test
     */
    public function Stream_offset_can_be_reset(): void
    {
        $name = 'foo';
        $consumerTag = 'bar';
        $offset = 35;

        $this->service->store($name, $consumerTag, $offset);
        $this->service->reset($name, $consumerTag);

        $this->assertSame(0, $this->service->fetch($name, $consumerTag));
    }

    /**
     * @test
     */
    public function Stream_offset_is_stored_per_consumerTag(): void
    {
        $name = 'foo';
        $consumerTag = 'bar';
        $offset = 35;

        $this->service->store($name, $consumerTag, $offset);
        $this->service->store($name, 'otherConsumer', 21);

        $this->assertSame($offset, $this->service->fetch($name, $consumerTag));
        $this->assertSame(21, $this->service->fetch($name, 'otherConsumer'));

        $this->service->reset($name, 'otherConsumer');

        $this->assertSame($offset, $this->service->fetch($name, $consumerTag));
        $this->assertSame(0, $this->service->fetch($name, 'otherConsumer'));
    }

    /**
     * @test
     */
    public function Stream_offset_is_stored_per_name(): void
    {
        $name = 'foo';
        $consumerTag = 'bar';
        $offset = 35;

        $this->service->store($name, $consumerTag, $offset);
        $this->service->store('otherQueue', $consumerTag, 21);

        $this->assertSame($offset, $this->service->fetch($name, $consumerTag));
        $this->assertSame(21, $this->service->fetch('otherQueue', $consumerTag));

        $this->service->reset('otherQueue', $consumerTag);

        $this->assertSame($offset, $this->service->fetch($name, $consumerTag));
        $this->assertSame(0, $this->service->fetch('otherQueue', $consumerTag));
    }

}