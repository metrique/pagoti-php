<?php

namespace Metrique\Pagoti;

use Metrique\Pagoti\Resources\ProjectResource;
use Metrique\Pagoti\Resources\ProjectsResource;

interface PagotiClientInterface
{
    public function projects(): ProjectsResource;
    public function project(string $projectId): ProjectResource;
    public function flush(): void;
}
