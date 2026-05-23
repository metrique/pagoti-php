<?php

namespace Metrique\Pagoti\Tests;

use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Metrique\Pagoti\Exceptions\PagotiApiException;
use Metrique\Pagoti\Exceptions\PagotiAuthException;
use Metrique\Pagoti\Exceptions\PagotiException;
use Metrique\Pagoti\Exceptions\PagotiNotFoundException;
use Metrique\Pagoti\PaginatedResponse;
use Metrique\Pagoti\PagotiClient;
use Metrique\Pagoti\Queries\MediaQuery;
use Metrique\Pagoti\Queries\PagesQuery;
use Metrique\Pagoti\Queries\ProjectsQuery;
use Metrique\Pagoti\Resources\PageResource;
use Metrique\Pagoti\Resources\ProjectResource;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\SimpleCache\CacheInterface;

class PagotiClientTest extends TestCase
{
    private ClientInterface&MockObject $httpClient;
    private CacheInterface&MockObject $cache;
    private HttpFactory $factory;
    private PagotiClient $client;

    protected function setUp(): void
    {
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);
        $this->factory = new HttpFactory();

        $this->client = new PagotiClient(
            apiKey: 'test-key',
            cache: $this->cache,
            baseUrl: 'https://pagoti.test/api',
            httpClient: $this->httpClient,
            requestFactory: $this->factory,
            streamFactory: $this->factory,
        );
    }

    private function mockResponse(array $data, int $status = 200): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], json_encode($data));
    }

    private function paginatedData(array $items): array
    {
        return [
            'data' => $items,
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => 15,
            'total' => count($items),
        ];
    }

    private function noCacheHit(): void
    {
        $this->cache->method('get')->willReturn(null);
    }

    public function test_projects_returns_projects_query(): void
    {
        $this->assertInstanceOf(ProjectsQuery::class, $this->client->projects());
    }

    public function test_project_returns_project_resource(): void
    {
        $this->assertInstanceOf(ProjectResource::class, $this->client->project('proj-1'));
    }

    public function test_project_pages_returns_pages_query(): void
    {
        $this->assertInstanceOf(PagesQuery::class, $this->client->project('proj-1')->pages());
    }

    public function test_project_page_returns_page_resource(): void
    {
        $this->assertInstanceOf(PageResource::class, $this->client->project('proj-1')->page('page-1'));
    }

    public function test_project_media_returns_media_query(): void
    {
        $this->assertInstanceOf(MediaQuery::class, $this->client->project('proj-1')->media());
    }

    public function test_projects_get_calls_correct_url(): void
    {
        $this->noCacheHit();

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(fn ($r) => (string) $r->getUri() === 'https://pagoti.test/api/v1/projects?page=1'))
            ->willReturn($this->mockResponse($this->paginatedData([])));

        $this->client->projects()->get();
    }

    public function test_project_get_calls_correct_url(): void
    {
        $this->noCacheHit();

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(fn ($r) => (string) $r->getUri() === 'https://pagoti.test/api/v1/projects/proj-1'))
            ->willReturn($this->mockResponse(['id' => 'proj-1']));

        $this->client->project('proj-1')->get();
    }

    public function test_project_pages_get_calls_correct_url(): void
    {
        $this->noCacheHit();

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(fn ($r) => (string) $r->getUri() === 'https://pagoti.test/api/v1/projects/proj-1/pages?page=1'))
            ->willReturn($this->mockResponse($this->paginatedData([])));

        $this->client->project('proj-1')->pages()->get();
    }

    public function test_project_page_get_calls_correct_url(): void
    {
        $this->noCacheHit();

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(fn ($r) => (string) $r->getUri() === 'https://pagoti.test/api/v1/projects/proj-1/pages/page-1'))
            ->willReturn($this->mockResponse(['id' => 'page-1']));

        $this->client->project('proj-1')->page('page-1')->get();
    }

    public function test_project_media_get_calls_correct_url(): void
    {
        $this->noCacheHit();

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(fn ($r) => (string) $r->getUri() === 'https://pagoti.test/api/v1/projects/proj-1/media?page=1'))
            ->willReturn($this->mockResponse($this->paginatedData([])));

        $this->client->project('proj-1')->media()->get();
    }

    public function test_request_sends_bearer_token(): void
    {
        $this->noCacheHit();

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(fn ($r) => $r->getHeaderLine('Authorization') === 'Bearer test-key'))
            ->willReturn($this->mockResponse(['id' => 'proj-1']));

        $this->client->project('proj-1')->get();
    }

    public function test_projects_get_returns_paginated_response(): void
    {
        $this->noCacheHit();
        $this->cache->method('set')->willReturn(true);

        $this->httpClient->method('sendRequest')
            ->willReturn($this->mockResponse($this->paginatedData([['id' => 'proj-1']])));

        $result = $this->client->projects()->get();

        $this->assertInstanceOf(PaginatedResponse::class, $result);
        $this->assertSame([['id' => 'proj-1']], $result->items());
        $this->assertSame(1, $result->currentPage());
        $this->assertSame(1, $result->total());
        $this->assertFalse($result->hasMore());
    }

    public function test_project_get_returns_array(): void
    {
        $this->noCacheHit();
        $this->cache->method('set')->willReturn(true);

        $this->httpClient->method('sendRequest')
            ->willReturn($this->mockResponse(['id' => 'proj-1', 'name' => 'My Project']));

        $result = $this->client->project('proj-1')->get();

        $this->assertSame(['id' => 'proj-1', 'name' => 'My Project'], $result);
    }

    public function test_get_returns_cached_response(): void
    {
        $cached = new PaginatedResponse($this->paginatedData([]));

        $this->cache->method('get')->willReturnCallback(function (string $key) use ($cached) {
            return str_starts_with($key, 'pagoti_v_') ? 1 : $cached;
        });
        $this->httpClient->expects($this->never())->method('sendRequest');

        $result = $this->client->projects()->get();

        $this->assertSame($cached, $result);
    }

    public function test_get_stores_response_in_cache(): void
    {
        $this->noCacheHit();

        $this->httpClient->method('sendRequest')
            ->willReturn($this->mockResponse($this->paginatedData([])));

        $this->cache->expects($this->once())->method('set');

        $this->client->projects()->get();
    }

    public function test_fresh_bypasses_cache_read(): void
    {
        $cached = new PaginatedResponse($this->paginatedData([['id' => 'cached']]));

        $this->cache->method('get')->willReturnCallback(function (string $key) use ($cached) {
            return str_starts_with($key, 'pagoti_v_') ? 1 : $cached;
        });
        $this->cache->method('set')->willReturn(true);

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->willReturn($this->mockResponse($this->paginatedData([['id' => 'fresh']])));

        $result = $this->client->projects()->fresh()->get();

        $this->assertSame([['id' => 'fresh']], $result->items());
    }

    public function test_fresh_still_writes_to_cache(): void
    {
        $this->cache->method('get')->willReturn(null);
        $this->cache->expects($this->once())->method('set');

        $this->httpClient->method('sendRequest')
            ->willReturn($this->mockResponse($this->paginatedData([])));

        $this->client->projects()->fresh()->get();
    }

    public function test_client_flush_clears_cache(): void
    {
        $this->cache->expects($this->once())->method('clear');

        $this->client->flush();
    }

    public function test_projects_flush_increments_version(): void
    {
        $this->cache->method('get')->willReturn(1);
        $this->cache->expects($this->once())
            ->method('set')
            ->with('pagoti_v_projects', 2);

        $this->client->projects()->flush();
    }

    public function test_project_flush_increments_all_version_keys(): void
    {
        $this->cache->method('get')->willReturn(1);

        $setKeys = [];
        $this->cache->method('set')
            ->willReturnCallback(function (string $key) use (&$setKeys) {
                $setKeys[] = $key;
                return true;
            });

        $this->client->project('proj-1')->flush();

        $this->assertContains('pagoti_v_project_proj-1', $setKeys);
        $this->assertContains('pagoti_v_project_proj-1_pages', $setKeys);
        $this->assertContains('pagoti_v_project_proj-1_media', $setKeys);
    }

    public function test_pages_flush_only_increments_pages_version(): void
    {
        $this->cache->method('get')->willReturn(1);

        $setKeys = [];
        $this->cache->method('set')
            ->willReturnCallback(function (string $key) use (&$setKeys) {
                $setKeys[] = $key;
                return true;
            });

        $this->client->project('proj-1')->pages()->flush();

        $this->assertContains('pagoti_v_project_proj-1_pages', $setKeys);
        $this->assertNotContains('pagoti_v_project_proj-1', $setKeys);
        $this->assertNotContains('pagoti_v_project_proj-1_media', $setKeys);
    }

    public function test_throws_not_found_exception_on_404(): void
    {
        $this->noCacheHit();

        $this->httpClient->method('sendRequest')
            ->willReturn($this->mockResponse(['message' => 'Not found.'], 404));

        $this->expectException(PagotiNotFoundException::class);

        $this->client->project('proj-1')->get();
    }

    public function test_throws_auth_exception_on_401(): void
    {
        $this->noCacheHit();

        $this->httpClient->method('sendRequest')
            ->willReturn($this->mockResponse(['message' => 'Unauthenticated.'], 401));

        $this->expectException(PagotiAuthException::class);

        $this->client->project('proj-1')->get();
    }

    public function test_throws_auth_exception_on_403(): void
    {
        $this->noCacheHit();

        $this->httpClient->method('sendRequest')
            ->willReturn($this->mockResponse(['message' => 'Forbidden.'], 403));

        $this->expectException(PagotiAuthException::class);

        $this->client->project('proj-1')->get();
    }

    public function test_throws_api_exception_on_500(): void
    {
        $this->noCacheHit();

        $this->httpClient->method('sendRequest')
            ->willReturn($this->mockResponse(['message' => 'Server error.'], 500));

        $this->expectException(PagotiApiException::class);

        $this->client->project('proj-1')->get();
    }

    public function test_api_exception_carries_status_code_and_errors(): void
    {
        $this->noCacheHit();

        $this->httpClient->method('sendRequest')
            ->willReturn($this->mockResponse([
                'message' => 'The given data was invalid.',
                'errors' => ['name' => ['The name field is required.']],
            ], 422));

        try {
            $this->client->project('proj-1')->get();
            $this->fail('Expected PagotiApiException');
        } catch (PagotiApiException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertSame(['name' => ['The name field is required.']], $e->getErrors());
        }
    }

    public function test_throws_pagoti_exception_on_network_error(): void
    {
        $this->noCacheHit();

        $networkException = $this->createMock(NetworkExceptionInterface::class);

        $this->httpClient->method('sendRequest')->willThrowException($networkException);

        $this->expectException(PagotiException::class);

        $this->client->project('proj-1')->get();
    }

    public function test_paginated_response_has_more_when_not_on_last_page(): void
    {
        $data = [
            'data' => [],
            'current_page' => 1,
            'last_page' => 3,
            'per_page' => 15,
            'total' => 45,
        ];

        $this->noCacheHit();
        $this->cache->method('set')->willReturn(true);

        $this->httpClient->method('sendRequest')
            ->willReturn($this->mockResponse($data));

        $result = $this->client->projects()->get();

        $this->assertTrue($result->hasMore());
        $this->assertSame(3, $result->lastPage());
        $this->assertSame(45, $result->total());
    }

    public function test_get_passes_page_number_in_query(): void
    {
        $this->noCacheHit();

        $this->httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(fn ($r) => str_contains((string) $r->getUri(), 'page=3')))
            ->willReturn($this->mockResponse($this->paginatedData([])));

        $this->client->projects()->get(page: 3);
    }
}
