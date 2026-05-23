<?php

namespace Metrique\Pagoti;

use Http\Discovery\Psr17FactoryDiscovery;
use Http\Discovery\Psr18ClientDiscovery;
use Metrique\Pagoti\Exceptions\PagotiApiException;
use Metrique\Pagoti\Exceptions\PagotiAuthException;
use Metrique\Pagoti\Exceptions\PagotiException;
use Metrique\Pagoti\Exceptions\PagotiNotFoundException;
use Metrique\Pagoti\Queries\ProjectsQuery;
use Metrique\Pagoti\Resources\ProjectResource;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;

class PagotiClient implements PagotiClientInterface, PagotiTransportInterface
{
    private ClientInterface $httpClient;
    private RequestFactoryInterface $requestFactory;
    private StreamFactoryInterface $streamFactory;

    public function __construct(
        private readonly string $apiKey,
        private readonly ?CacheInterface $cache = null,
        private readonly int $cacheTtl = 3600,
        private readonly string $baseUrl = 'https://pagoti.com/api',
        private readonly string $version = 'v1',
        ?ClientInterface $httpClient = null,
        ?RequestFactoryInterface $requestFactory = null,
        ?StreamFactoryInterface $streamFactory = null,
    ) {
        $this->httpClient = $httpClient ?? Psr18ClientDiscovery::find();
        $this->requestFactory = $requestFactory ?? Psr17FactoryDiscovery::findRequestFactory();
        $this->streamFactory = $streamFactory ?? Psr17FactoryDiscovery::findStreamFactory();
    }

    public function projects(): ProjectsQuery
    {
        return new ProjectsQuery($this);
    }

    public function project(string $projectId): ProjectResource
    {
        return new ProjectResource($this, $projectId);
    }

    public function flush(): void
    {
        $this->cache?->clear();
    }

    public function flushVersion(string ...$versionKeys): void
    {
        foreach ($versionKeys as $versionKey) {
            $current = (int) ($this->cache?->get($versionKey) ?? 1);
            $this->cache?->set($versionKey, $current + 1);
        }
    }

    public function request(string $method, string $url, array $query = [], array $body = [], bool $fresh = false, string $versionKey = ''): mixed
    {
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $version = $versionKey ? (int) ($this->cache?->get($versionKey) ?? 1) : 1;
        $cacheKey = "pagoti_{$version}_" . md5($url);

        if ($this->cache && $method === 'GET' && !$fresh) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $request = $this->requestFactory->createRequest($method, $url)
            ->withHeader('Authorization', "Bearer {$this->apiKey}")
            ->withHeader('Accept', 'application/json');

        if ($body) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->streamFactory->createStream(json_encode($body, JSON_THROW_ON_ERROR)));
        }

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new PagotiException('HTTP request failed: ' . $e->getMessage(), previous: $e);
        }

        $data = json_decode($response->getBody()->getContents(), true);
        $status = $response->getStatusCode();

        if ($status >= 400) {
            $message = $data['message'] ?? 'Unknown error';
            $errors = $data['errors'] ?? [];

            throw match(true) {
                $status === 404 => new PagotiNotFoundException($message, $status, $errors),
                in_array($status, [401, 403]) => new PagotiAuthException($message, $status, $errors),
                default => new PagotiApiException($message, $status, $errors),
            };
        }

        if (isset($data['data']) && isset($data['current_page'])) {
            $result = new PaginatedResponse($data);
        } else {
            $result = $data;
        }

        if ($this->cache && $method === 'GET') {
            $this->cache->set($cacheKey, $result, $this->cacheTtl);
        }

        return $result;
    }

    public function apiUrl(?string $path = null): string
    {
        $apiUrl = "{$this->baseUrl}/{$this->version}";
        return $path ? "{$apiUrl}/{$path}" : $apiUrl;
    }
}
