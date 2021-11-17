<?php
declare(strict_types=1);

namespace t3n\JobQueue\RabbitMQ\Service;

use Neos\Cache\Frontend\VariableFrontend;
use Neos\Flow\Annotations as Flow;

/**
 * @Flow\Scope("singleton")
 */
class StreamOffsetService
{

    /**
     * @var VariableFrontend
     */
    private $cache;

    public function store(string $name, string $consumerTag, int $offset): void
    {
        $this->cache->set(self::entryIdentifier($name, $consumerTag), $offset);
    }

    public function fetch(string $name, string $consumerTag): int
    {
        $offset = $this->cache->get(self::entryIdentifier($name, $consumerTag));

        return $offset !== false ? $offset : 0;
    }

    public function reset(string $name, string $consumerTag): void
    {
        $this->cache->remove(self::entryIdentifier($name, $consumerTag));
    }

    public function injectCache(VariableFrontend $variableFrontend): void
    {
        $this->cache = $variableFrontend;
    }

    private static function entryIdentifier(string $name, string $consumerTag): string
    {
        return sha1(implode('_', [$name, $consumerTag]));
    }

}