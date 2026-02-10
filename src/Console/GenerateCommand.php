<?php

declare(strict_types=1);

namespace LaravelCodegen\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class GenerateCommand extends Command
{
    protected $signature = 'l-codegen:generate {spec : Path to OpenAPI specification file}';

    protected $description = 'Generate Laravel code from an OpenAPI specification';

    public function handle(): int
    {
        $spec = $this->argument('spec');
        $binary = base_path('vendor/bin/lcodegen');

        if (! file_exists($binary)) {
            $this->error("Binary not found at {$binary}. Run `php artisan l-codegen:install` first.");

            return self::FAILURE;
        }

        $this->info("Generating code from {$spec}...");

        $result = Process::path(base_path())
            ->timeout(300)
            ->tty()
            ->run([$binary, $spec]);

        if ($result->failed()) {
            return self::FAILURE;
        }

        $this->info('Code generation completed successfully.');

        return self::SUCCESS;
    }
}
