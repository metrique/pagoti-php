<?php

namespace Metrique\Pagoti\Tests\Concerns;

use GuzzleHttp\Psr7\Response;
use Metrique\Pagoti\PagotiClient;
use Psr\Http\Client\ClientInterface;
use Psr\SimpleCache\CacheInterface;

trait CreatesPagotiClient
{
    protected function mockResponse(array $data, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data));
    }

    protected function paginatedData(array $items): array
    {
        return [
            'data' => $items,
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 15,
            'total' => count($items),
        ];
    }

    protected function makeClient(?ClientInterface $httpClient = null, ?CacheInterface $cache = null): PagotiClient
    {
        return new PagotiClient(
            apiKey: 'test-key',
            cache: $cache,
            baseUrl: 'https://pagoti.test/api',
            httpClient: $httpClient ?? $this->createStub(ClientInterface::class),
            requestFactory: $this->factory,
            streamFactory: $this->factory,
        );
    }

    protected function makeCacheStub(mixed $getReturn = null, bool $setReturn = true): CacheInterface
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturn($getReturn);
        $cache->method('set')->willReturn($setReturn);

        return $cache;
    }
}
