<?php

namespace Metrique\Pagoti\Tests\Resources;

use Metrique\Pagoti\Tests\Concerns\CreatesPagotiClient;
use Metrique\Pagoti\Tests\TestCase;
use Psr\Http\Client\ClientInterface;

class MediaResourceTest extends TestCase
{
    use CreatesPagotiClient;

    public function test_project_media_get_calls_correct_url(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $client = $this->makeClient(httpClient: $httpClient, cache: $this->makeCacheStub());

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(fn ($r) => (string) $r->getUri() === 'https://pagoti.test/api/v1/projects/proj-1/media?page=1'))
            ->willReturn($this->mockResponse($this->paginatedData([])));

        $client->project('proj-1')->media()->get();
    }
}
