<?php

namespace Metrique\Pagoti\Resources;

use Metrique\Pagoti\PagotiClient;
use Metrique\Pagoti\Resources\Concerns\CanFetchFresh;

class ProjectResource
{
    use CanFetchFresh;

    public function __construct(
        private readonly PagotiClient $client,
        private readonly string $projectId,
    ) {
    }


    public function flush(): void
    {
        $this->client->flushVersion(
            $this->versionKey(),
            PagesResource::versionKey($this->projectId),
            MediaResource::versionKey($this->projectId),
        );
    }

    public function get(): array
    {
        return $this->client->request(
            'GET',
            $this->client->apiUrl("projects/{$this->projectId}"),
            fresh: $this->fresh,
            versionKey: $this->versionKey(),
        );
    }

    public function update(array $data): array
    {
        $result = $this->client->request(
            'PUT',
            $this->client->apiUrl("projects/{$this->projectId}"),
            body: $data,
        );

        $this->flush();
        $this->client->flushVersion(ProjectsResource::versionKey());

        return $result;
    }

    public function delete(): void
    {
        $this->client->request(
            'DELETE',
            $this->client->apiUrl("projects/{$this->projectId}"),
        );

        $this->flush();
        $this->client->flushVersion(ProjectsResource::versionKey());
    }

    public function pages(): PagesResource
    {
        return new PagesResource($this->client, $this->projectId);
    }

    public function page(string $pageId): PageResource
    {
        return new PageResource($this->client, $this->projectId, $pageId);
    }

    public function media(): MediaResource
    {
        return new MediaResource($this->client, $this->projectId);
    }

    private function versionKey(): string
    {
        return "pagoti_v_project_{$this->projectId}";
    }
}
