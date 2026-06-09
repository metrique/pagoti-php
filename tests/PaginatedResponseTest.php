<?php

namespace Metrique\Pagoti\Tests;

use Metrique\Pagoti\PaginatedResponse;

class PaginatedResponseTest extends TestCase
{
    public function test_paginated_response_has_more_when_not_on_last_page(): void
    {
        $result = new PaginatedResponse([
            'data' => [],
            'current_page' => 1,
            'last_page' => 3,
            'per_page' => 15,
            'total' => 45,
        ]);

        $this->assertTrue($result->hasMore());
        $this->assertSame(3, $result->lastPage());
        $this->assertSame(45, $result->total());
    }
}
