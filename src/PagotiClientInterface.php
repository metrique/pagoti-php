<?php

namespace Metrique\Pagoti;

use Metrique\Pagoti\Queries\ProjectsQuery;
use Metrique\Pagoti\Resources\ProjectResource;

interface PagotiClientInterface
{
    public function projects(): ProjectsQuery;
    public function project(string $projectId): ProjectResource;
    public function flush(): void;
}
