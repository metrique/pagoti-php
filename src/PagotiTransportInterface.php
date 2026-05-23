<?php

namespace Metrique\Pagoti;

interface PagotiTransportInterface
{
    public function request(string $method, string $url, array $query = [], array $body = [], bool $fresh = false, string $versionKey = ''): mixed;
    public function flushVersion(string ...$versionKeys): void;
    public function apiUrl(?string $path = null): string;
}
