<?php

namespace Metrique\Pagoti\Tests\Resources;

use Metrique\Pagoti\Tests\Concerns\CreatesPagotiClient;
use Metrique\Pagoti\Tests\TestCase;
use Psr\Http\Client\ClientInterface;

class PageResourceTest extends TestCase
{
    use CreatesPagotiClient;

    public function test_project_page_get_calls_correct_url(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $client = $this->makeClient(httpClient: $httpClient, cache: $this->makeCacheStub());

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(fn ($r) => (string) $r->getUri() === 'https://pagoti.test/api/v1/projects/proj-1/pages/page-1'))
            ->willReturn($this->mockResponse(['id' => 'page-1']));

        $client->project('proj-1')->page('page-1')->get();
    }
}
