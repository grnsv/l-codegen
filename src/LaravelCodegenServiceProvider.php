<?php

declare(strict_types=1);

namespace LaravelCodegen;

use Illuminate\Support\ServiceProvider;
use LaravelCodegen\Console\GenerateCommand;
use LaravelCodegen\Console\InstallCommand;

class LaravelCodegenServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateCommand::class,
                InstallCommand::class,
            ]);
        }
    }
}
