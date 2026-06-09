<?php

namespace Metrique\Pagoti\Tests\Resources;

use Metrique\Pagoti\Tests\Concerns\CreatesPagotiClient;
use Metrique\Pagoti\Tests\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\SimpleCache\CacheInterface;

class ProjectResourceTest extends TestCase
{
    use CreatesPagotiClient;

    public function test_project_get_calls_correct_url(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $client = $this->makeClient(httpClient: $httpClient, cache: $this->makeCacheStub());

        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(fn ($r) => (string) $r->getUri() === 'https://pagoti.test/api/v1/projects/proj-1'))
            ->willReturn($this->mockResponse(['id' => 'proj-1']));

        $client->project('proj-1')->get();
    }

    public function test_project_get_returns_array(): void
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')
            ->willReturn($this->mockResponse(['id' => 'proj-1', 'name' => 'My Project']));

        $client = $this->makeClient(httpClient: $httpClient, cache: $this->makeCacheStub());

        $result = $client->project('proj-1')->get();

        $this->assertSame(['id' => 'proj-1', 'name' => 'My Project'], $result);
    }

    public function test_project_flush_increments_all_version_keys(): void
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

        $client->project('proj-1')->flush();

        $this->assertContains('pagoti_v_project_proj-1', $setKeys);
        $this->assertContains('pagoti_v_project_proj-1_pages', $setKeys);
        $this->assertContains('pagoti_v_project_proj-1_media', $setKeys);
    }

    public function test_update_sends_json_body_and_flushes_related_cache_keys(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function ($request) {
                $this->assertSame('PUT', $request->getMethod());
                $this->assertSame('https://pagoti.test/api/v1/projects/proj-1', (string) $request->getUri());
                $this->assertSame('application/json', $request->getHeaderLine('Content-Type'));
                $this->assertSame('{"name":"Updated Project"}', (string) $request->getBody());

                return true;
            }))
            ->willReturn($this->mockResponse(['id' => 'proj-1', 'name' => 'Updated Project']));

        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturn(1);

        $setKeys = [];
        $cache->method('set')->willReturnCallback(function (string $key) use (&$setKeys) {
            $setKeys[] = $key;
            return true;
        });

        $client = $this->makeClient(httpClient: $httpClient, cache: $cache);

        $result = $client->project('proj-1')->update([
            'name' => 'Updated Project',
        ]);

        $this->assertSame(['id' => 'proj-1', 'name' => 'Updated Project'], $result);
        $this->assertContains('pagoti_v_project_proj-1', $setKeys);
        $this->assertContains('pagoti_v_project_proj-1_pages', $setKeys);
        $this->assertContains('pagoti_v_project_proj-1_media', $setKeys);
        $this->assertContains('pagoti_v_projects', $setKeys);
    }

    public function test_update_supports_project_image_uploads(): void
    {
        $imagePath = tempnam(sys_get_temp_dir(), 'pagoti-project-image-');
        file_put_contents($imagePath, 'fake-image-content');

        try {
            $httpClient = $this->createMock(ClientInterface::class);
            $httpClient->expects($this->once())
                ->method('sendRequest')
                ->with($this->callback(function ($request) {
                    $this->assertSame('PUT', $request->getMethod());
                    $this->assertStringStartsWith('multipart/form-data; boundary=', $request->getHeaderLine('Content-Type'));

                    $body = (string) $request->getBody();
                    $this->assertStringContainsString('name="name"', $body);
                    $this->assertStringContainsString('Updated Project', $body);
                    $this->assertStringContainsString('name="image"', $body);
                    $this->assertStringContainsString('fake-image-content', $body);

                    return true;
                }))
                ->willReturn($this->mockResponse(['id' => 'proj-1', 'name' => 'Updated Project']));

            $cache = $this->createStub(CacheInterface::class);
            $cache->method('get')->willReturn(1);
            $cache->method('set')->willReturn(true);

            $client = $this->makeClient(httpClient: $httpClient, cache: $cache);

            $client->project('proj-1')->update([
                'name' => 'Updated Project',
                'image' => $imagePath,
            ]);
        } finally {
            unlink($imagePath);
        }
    }

    public function test_delete_sends_delete_request_and_flushes_related_cache_keys(): void
    {
        $httpClient = $this->createMock(ClientInterface::class);
        $httpClient->expects($this->once())
            ->method('sendRequest')
            ->with($this->callback(function ($request) {
                $this->assertSame('DELETE', $request->getMethod());
                $this->assertSame('https://pagoti.test/api/v1/projects/proj-1', (string) $request->getUri());

                return true;
            }))
            ->willReturn($this->mockResponse([], 204));

        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturn(1);

        $setKeys = [];
        $cache->method('set')->willReturnCallback(function (string $key) use (&$setKeys) {
            $setKeys[] = $key;
            return true;
        });

        $client = $this->makeClient(httpClient: $httpClient, cache: $cache);

        $client->project('proj-1')->delete();

        $this->assertContains('pagoti_v_project_proj-1', $setKeys);
        $this->assertContains('pagoti_v_project_proj-1_pages', $setKeys);
        $this->assertContains('pagoti_v_project_proj-1_media', $setKeys);
        $this->assertContains('pagoti_v_projects', $setKeys);
    }
}
