<?php

namespace Metrique\Pagoti\Tests\Resources;

use Metrique\Pagoti\Tests\Concerns\CreatesPagotiClient;
use Metrique\Pagoti\Tests\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\SimpleCache\CacheInterface;

class PagesResourceTest extends TestCase
{
    use CreatesPagotiClient;

    public function test_project_pages_get_calls_correct_url(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $client = $this->makeClient(httpClient: $httpClient, cache: $this->makeCacheStub());

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(fn ($r) => (string) $r->getUri() === 'https://pagoti.test/api/v1/projects/proj-1/pages?page=1'))
            ->willReturn($this->mockResponse($this->paginatedData([])));

        $client->project('proj-1')->pages()->get();
    }

    public function test_pages_flush_only_increments_pages_version(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturn(1);

        $setKeys = [];
        $cache->method('set')
            ->willReturnCallback(function (string $key) use (&$setKeys) {
                $setKeys[] = $key;
                return true;
            });

        $client = $this->makeClient(cache: $cache);

        $client->project('proj-1')->pages()->flush();

        $this->assertContains('pagoti_v_project_proj-1_pages', $setKeys);
        $this->assertNotContains('pagoti_v_project_proj-1', $setKeys);
        $this->assertNotContains('pagoti_v_project_proj-1_media', $setKeys);
    }
}
