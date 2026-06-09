<?php

namespace Metrique\Pagoti\Tests;

use GuzzleHttp\Psr7\HttpFactory;
use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected HttpFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new HttpFactory();
    }
}
