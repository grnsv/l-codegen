<?php

declare(strict_types=1);

namespace LaravelCodegen\Tests;

use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Http;
use LaravelCodegen\Console\InstallCommand;
use LaravelCodegen\LaravelCodegenServiceProvider;
use Phar;
use PharData;
use RuntimeException;

final class InstallCommandTest extends TestCase
{
    private string $binPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->binPath = base_path('vendor/bin/lcodegen');

        if (file_exists($this->binPath)) {
            unlink($this->binPath);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists($this->binPath)) {
            unlink($this->binPath);
        }

        parent::tearDown();
    }

    public function test_command_is_registered(): void
    {
        $commands = $this->app->make(ConsoleKernel::class)->all();
        $this->assertArrayHasKey('l-codegen:install', $commands);
    }

    public function test_command_downloads_and_installs_binary(): void
    {
        $this->registerCommandWithVersion('1.2.3');

        $archiveContent = $this->createFakeTarGz();
        $checksumContent = hash('sha256', $archiveContent).'  lcodegen_Linux_x86_64.tar.gz';

        Http::fake([
            'github.com/grnsv/lcodegen/releases/download/v1.2.3/lcodegen_1.2.3_checksums.txt' => Http::response($checksumContent),
            'github.com/grnsv/lcodegen/releases/download/v1.2.3/lcodegen_Linux_x86_64.tar.gz' => Http::response($archiveContent),
        ]);

        $this->artisan('l-codegen:install')
            ->expectsOutput('Installing lcodegen binary...')
            ->assertExitCode(0);
    }

    public function test_command_fails_with_dev_version(): void
    {
        $this->registerCommandWithVersion('dev-main');

        $this->artisan('l-codegen:install')
            ->expectsOutputToContain('Dev version')
            ->assertExitCode(1);
    }

    public function test_command_fails_when_checksum_invalid(): void
    {
        $this->registerCommandWithVersion('1.2.3');

        $archiveContent = $this->createFakeTarGz();
        $wrongChecksum = str_repeat('a', 64).'  lcodegen_Linux_x86_64.tar.gz';

        Http::fake([
            'github.com/grnsv/lcodegen/releases/download/v1.2.3/lcodegen_1.2.3_checksums.txt' => Http::response($wrongChecksum),
            'github.com/grnsv/lcodegen/releases/download/v1.2.3/lcodegen_Linux_x86_64.tar.gz' => Http::response($archiveContent),
        ]);

        $this->artisan('l-codegen:install')
            ->expectsOutputToContain('Checksum verification failed')
            ->assertExitCode(1);
    }

    public function test_command_fails_when_checksum_unavailable(): void
    {
        $this->registerCommandWithVersion('1.2.3');

        $archiveContent = $this->createFakeTarGz();

        Http::fake([
            'github.com/grnsv/lcodegen/releases/download/v1.2.3/lcodegen_1.2.3_checksums.txt' => Http::response('', 404),
            'github.com/grnsv/lcodegen/releases/download/v1.2.3/lcodegen_Linux_x86_64.tar.gz' => Http::response($archiveContent),
        ]);

        $this->artisan('l-codegen:install')
            ->expectsOutputToContain('Failed to download checksums')
            ->assertExitCode(1);
    }

    public function test_command_skips_checksum_with_flag(): void
    {
        $this->registerCommandWithVersion('1.2.3');

        $archiveContent = $this->createFakeTarGz();

        Http::fake([
            'github.com/grnsv/lcodegen/releases/download/v1.2.3/lcodegen_Linux_x86_64.tar.gz' => Http::response($archiveContent),
        ]);

        $this->artisan('l-codegen:install --skip-checksum')
            ->expectsOutput('Skipping checksum verification')
            ->assertExitCode(0);
    }

    public function test_service_provider_is_loaded(): void
    {
        $providers = $this->app->getLoadedProviders();
        $this->assertArrayHasKey(LaravelCodegenServiceProvider::class, $providers);
    }

    private function registerCommandWithVersion(string $version): void
    {
        $command = new class($version) extends InstallCommand
        {
            public function __construct(private readonly string $fakeVersion)
            {
                parent::__construct();
            }

            protected function getVersion(): string
            {
                if (str_starts_with($this->fakeVersion, 'dev-')) {
                    throw new RuntimeException(
                        'Dev version ('.$this->fakeVersion.') cannot be used to download a GitHub release.'
                    );
                }

                return ltrim($this->fakeVersion, 'v');
            }

            /**
             * @return array<string, string>
             */
            protected function detectPlatform(): array
            {
                return [
                    'os' => 'Linux',
                    'arch' => 'x86_64',
                    'extension' => 'tar.gz',
                ];
            }
        };

        $this->app->make(ConsoleKernel::class)->registerCommand($command);
    }

    private function createFakeTarGz(): string
    {
        $tempDir = sys_get_temp_dir().'/lcodegen_test_'.uniqid();
        mkdir($tempDir);
        file_put_contents($tempDir.'/lcodegen', '#!/bin/sh'."\n".'echo "fake binary"');

        $tarPath = $tempDir.'/archive.tar';
        $phar = new PharData($tarPath);
        $phar->addFile($tempDir.'/lcodegen', 'lcodegen');

        $phar->compress(Phar::GZ);

        $content = file_get_contents($tarPath.'.gz');
        $this->assertNotFalse($content);

        @unlink($tempDir.'/lcodegen');
        @unlink($tarPath);
        @unlink($tarPath.'.gz');
        @rmdir($tempDir);

        return $content;
    }
}
