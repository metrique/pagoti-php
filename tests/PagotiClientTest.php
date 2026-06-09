<?php

namespace Metrique\Pagoti\Tests;

use GuzzleHttp\Psr7\Response;
use JsonException;
use Metrique\Pagoti\Exceptions\PagotiApiException;
use Metrique\Pagoti\Exceptions\PagotiAuthException;
use Metrique\Pagoti\Exceptions\PagotiException;
use Metrique\Pagoti\Exceptions\PagotiNotFoundException;
use Metrique\Pagoti\Resources\MediaResource;
use Metrique\Pagoti\Resources\PageResource;
use Metrique\Pagoti\Resources\PagesResource;
use Metrique\Pagoti\Resources\ProjectResource;
use Metrique\Pagoti\Resources\ProjectsResource;
use Metrique\Pagoti\Tests\Concerns\CreatesPagotiClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\SimpleCache\CacheInterface;

class PagotiClientTest extends TestCase
{
    use CreatesPagotiClient;

    public function test_projects_returns_projects_resource(): void
    {
        $client = $this->makeClient();

        $this->assertInstanceOf(ProjectsResource::class, $client->projects());
    }

    public function test_project_returns_project_resource(): void
    {
        $client = $this->makeClient();

        $this->assertInstanceOf(ProjectResource::class, $client->project('proj-1'));
    }

    public function test_project_pages_returns_pages_resource(): void
    {
        $client = $this->makeClient();

        $this->assertInstanceOf(PagesResource::class, $client->project('proj-1')->pages());
    }

    public function test_project_page_returns_page_resource(): void
    {
        $client = $this->makeClient();

        $this->assertInstanceOf(PageResource::class, $client->project('proj-1')->page('page-1'));
    }

    public function test_project_media_returns_media_resource(): void
    {
        $client = $this->makeClient();

        $this->assertInstanceOf(MediaResource::class, $client->project('proj-1')->media());
    }

    public function test_request_sends_bearer_token(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $client = $this->makeClient(httpClient: $httpClient, cache: $this->makeCacheStub());

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(fn ($r) => $r->getHeaderLine('Authorization') === 'Bearer test-key'))
            ->willReturn($this->mockResponse(['id' => 'proj-1']));

        $client->project('proj-1')->get();
    }

    public function test_client_flush_clears_cache(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('clear');

        $client = $this->makeClient(cache: $cache);

        $client->flush();
    }

    public function test_throws_not_found_exception_on_404(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')
            ->willReturn($this->mockResponse(['message' => 'Not found.'], 404));

        $client = $this->makeClient(httpClient: $httpClient, cache: $this->makeCacheStub());

        $this->expectException(PagotiNotFoundException::class);

        $client->project('proj-1')->get();
    }

    public function test_throws_auth_exception_on_401(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')
            ->willReturn($this->mockResponse(['message' => 'Unauthenticated.'], 401));

        $client = $this->makeClient(httpClient: $httpClient, cache: $this->makeCacheStub());

        $this->expectException(PagotiAuthException::class);

        $client->project('proj-1')->get();
    }

    public function test_throws_auth_exception_on_403(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')
            ->willReturn($this->mockResponse(['message' => 'Forbidden.'], 403));

        $client = $this->makeClient(httpClient: $httpClient, cache: $this->makeCacheStub());

        $this->expectException(PagotiAuthException::class);

        $client->project('proj-1')->get();
    }

    public function test_throws_api_exception_on_500(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')
            ->willReturn($this->mockResponse(['message' => 'Server error.'], 500));

        $client = $this->makeClient(httpClient: $httpClient, cache: $this->makeCacheStub());

        $this->expectException(PagotiApiException::class);

        $client->project('proj-1')->get();
    }

    public function test_api_exception_carries_status_code_and_errors(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')
            ->willReturn($this->mockResponse([
                'message' => 'The given data was invalid.',
                'errors' => ['name' => ['The name field is required.']],
            ], 422));

        $client = $this->makeClient(httpClient: $httpClient, cache: $this->makeCacheStub());

        try {
            $client->project('proj-1')->get();
            $this->fail('Expected PagotiApiException');
        } catch (PagotiApiException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertSame(['name' => ['The name field is required.']], $e->getErrors());
        }
    }

    public function test_throws_pagoti_exception_on_invalid_json_response(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')
            ->willReturn(new Response(200, ['Content-Type' => 'application/json'], '{invalid'));

        $client = $this->makeClient(httpClient: $httpClient, cache: $this->makeCacheStub());

        try {
            $client->project('proj-1')->get();
            $this->fail('Expected PagotiException');
        } catch (PagotiException $e) {
            $this->assertStringStartsWith('Failed to decode JSON response:', $e->getMessage());
            $this->assertInstanceOf(JsonException::class, $e->getPrevious());
        }
    }

    public function test_empty_success_response_returns_empty_array(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')
            ->willReturn(new Response(204, ['Content-Type' => 'application/json'], ''));

        $client = $this->makeClient(httpClient: $httpClient, cache: $this->makeCacheStub());

        $this->assertSame([], $client->project('proj-1')->get());
    }

    public function test_throws_pagoti_exception_on_network_error(): void
    {
        $networkException = $this->createStub(NetworkExceptionInterface::class);

        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willThrowException($networkException);

        $client = $this->makeClient(httpClient: $httpClient, cache: $this->makeCacheStub());

        $this->expectException(PagotiException::class);

        $client->project('proj-1')->get();
    }
}
