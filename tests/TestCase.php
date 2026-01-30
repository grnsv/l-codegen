<?php

declare(strict_types=1);

namespace LaravelCodegen\Tests;

use LaravelCodegen\LaravelCodegenServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelCodegenServiceProvider::class,
        ];
    }
}
