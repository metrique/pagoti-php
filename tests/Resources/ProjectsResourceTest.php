<?php

namespace Metrique\Pagoti\Tests\Resources;

use Metrique\Pagoti\Exceptions\PagotiException;
use Metrique\Pagoti\PaginatedResponse;
use Metrique\Pagoti\Tests\Concerns\CreatesPagotiClient;
use Metrique\Pagoti\Tests\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\SimpleCache\CacheInterface;
use SplFileInfo;

class ProjectsResourceTest extends TestCase
{
    use CreatesPagotiClient;

    public function test_projects_get_calls_correct_url(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $client = $this->makeClient(httpClient: $httpClient, cache: $this->makeCacheStub());

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(fn ($r) => (string) $r->getUri() === 'https://pagoti.test/api/v1/projects?page=1'))
            ->willReturn($this->mockResponse($this->paginatedData([])));

        $client->projects()->get();
    }

    public function test_projects_get_returns_paginated_response(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')
            ->willReturn($this->mockResponse($this->paginatedData([['id' => 'proj-1']])));

        $client = $this->makeClient(httpClient: $httpClient, cache: $this->makeCacheStub());

        $result = $client->projects()->get();

        $this->assertInstanceOf(PaginatedResponse::class, $result);
        $this->assertSame([['id' => 'proj-1']], $result->items());
        $this->assertSame(1, $result->currentPage());
        $this->assertSame(1, $result->total());
        $this->assertFalse($result->hasMore());
    }

    public function test_get_returns_cached_response(): void
    {
        $cached = new PaginatedResponse($this->paginatedData([]));
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnCallback(function (string $key) use ($cached) {
            return str_starts_with($key, 'pagoti_v_') ? 1 : $cached;
        });

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->never())->method('sendRequest');

        $client = $this->makeClient(httpClient: $httpClient, cache: $cache);

        $result = $client->projects()->get();

        $this->assertSame($cached, $result);
    }

    public function test_get_stores_response_in_cache(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')
            ->willReturn($this->mockResponse($this->paginatedData([])));

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->once())->method('set');

        $client = $this->makeClient(httpClient: $httpClient, cache: $cache);

        $client->projects()->get();
    }

    public function test_fresh_bypasses_cache_read(): void
    {
        $cached = new PaginatedResponse($this->paginatedData([['id' => 'cached']]));
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnCallback(function (string $key) use ($cached) {
            return str_starts_with($key, 'pagoti_v_') ? 1 : $cached;
        });
        $cache->method('set')->willReturn(true);

        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->mockResponse($this->paginatedData([['id' => 'fresh']])));

        $client = $this->makeClient(httpClient: $httpClient, cache: $cache);

        $result = $client->projects()->fresh()->get();

        $this->assertSame([['id' => 'fresh']], $result->items());
    }

    public function test_fresh_still_writes_to_cache(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')
            ->willReturn($this->mockResponse($this->paginatedData([])));

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(null);
        $cache->expects($this->once())->method('set');

        $client = $this->makeClient(httpClient: $httpClient, cache: $cache);

        $client->projects()->fresh()->get();
    }

    public function test_projects_flush_increments_version(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(1);
        $cache->expects($this->once())
            ->method('set')
            ->with('pagoti_v_projects', 2);

        $client = $this->makeClient(cache: $cache);

        $client->projects()->flush();
    }

    public function test_create_posts_json_body_and_flushes_projects_cache(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function ($request) {
                $this->assertSame('POST', $request->getMethod());
                $this->assertSame('https://pagoti.test/api/v1/projects', (string) $request->getUri());
                $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
                $this->assertSame('{"name":"My Project","description":"Project description"}', (string) $request->getBody());

                return true;
            }))
            ->willReturn($this->mockResponse(['id' => 'proj-1'], 201));

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturn(1);
        $cache->expects($this->once())
            ->method('set')
            ->with('pagoti_v_projects', 2);

        $client = $this->makeClient(httpClient: $httpClient, cache: $cache);

        $result = $client->projects()->create([
            'name' => 'My Project',
            'description' => 'Project description',
        ]);

        $this->assertSame(['id' => 'proj-1'], $result);
    }

    public function test_create_supports_project_image_uploads(): void
    {
        $imagePath = tempnam(sys_get_temp_dir(), 'pagoti-project-image-');
        file_put_contents($imagePath, 'fake-image-content');

        try {
            $httpClient = $this->createMock(ClientInterface::class);
            $httpClient->expects($this->once())
                ->method('sendRequest')
                ->with($this->callback(function ($request) {
                    $this->assertSame('POST', $request->getMethod());
                    $this->assertStringStartsWith('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));

                    $body = (string) $request->getBody();
                    $this->assertStringContainsString('name="name"', $body);
                    $this->assertStringContainsString('My Project', $body);
                    $this->assertStringContainsString('name="image"', $body);
                    $this->assertStringContainsString('filename="', $body);
                    $this->assertStringContainsString('fake-image-content', $body);

                    return true;
                }))
                ->willReturn($this->mockResponse(['id' => 'proj-1'], 201));

            $cache = $this->createMock(CacheInterface::class);
            $cache->method('get')->willReturn(1);
            $cache->expects($this->once())
                ->method('set')
                ->with('pagoti_v_projects', 2);

            $client = $this->makeClient(httpClient: $httpClient, cache: $cache);

            $client->projects()->create([
                'name' => 'My Project',
                'image' => $imagePath,
            ]);
        } finally {
            unlink($imagePath);
        }
    }

    public function test_create_throws_exception_for_missing_image_path(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->never())->method('sendRequest');

        $client = $this->makeClient(httpClient: $httpClient, cache: $this->makeCacheStub());

        $this->expectException(PagotiException::class);
        $this->expectExceptionMessage('does not exist or is not readable');

        $client->projects()->create([
            'name' => 'My Project',
            'image' => sys_get_temp_dir() . '/missing-pagoti-project-image.jpg',
        ]);
    }

    public function test_create_throws_exception_for_missing_spl_file_info_image(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->never())->method('sendRequest');

        $client = $this->makeClient(httpClient: $httpClient, cache: $this->makeCacheStub());

        $this->expectException(PagotiException::class);
        $this->expectExceptionMessage('does not exist or is not readable');

        $client->projects()->create([
            'name' => 'My Project',
            'image' => new SplFileInfo(sys_get_temp_dir() . '/missing-pagoti-project-image.jpg'),
        ]);
    }

    public function test_get_passes_page_number_in_request_parameters(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $client = $this->makeClient(httpClient: $httpClient, cache: $this->makeCacheStub());

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(fn ($r) => str_contains((string) $r->getUri(), 'page=3')))
            ->willReturn($this->mockResponse($this->paginatedData([])));

        $client->projects()->get(page: 3);
    }
}
