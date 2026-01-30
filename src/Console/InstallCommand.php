<?php

declare(strict_types=1);

namespace LaravelCodegen\Console;

use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use PharData;
use RuntimeException;
use ZipArchive;

class InstallCommand extends Command
{
    protected $signature = 'l-codegen:install
        {--skip-checksum : Skip checksum verification}';

    protected $description = 'Install lcodegen binary';

    private const GITHUB_REPO = 'grnsv/lcodegen';

    private const BINARY_NAME = 'lcodegen';

    public function handle(): int
    {
        $this->info('Installing lcodegen binary...');

        try {
            $version = $this->getVersion();
            $platform = $this->detectPlatform();

            $binDir = base_path('vendor/bin');

            if (! is_dir($binDir)) {
                mkdir($binDir, 0755, true);
            }

            $binaryPath = $binDir.'/'.self::BINARY_NAME;

            $this->downloadAndInstall($version, $platform, $binaryPath);
            $this->info('Successfully installed lcodegen binary to '.$binaryPath);

            return self::SUCCESS;
        } catch (RuntimeException $e) {
            $this->error('Failed to install binary: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    protected function getVersion(): string
    {
        $version = InstalledVersions::getPrettyVersion('grnsv/l-codegen');

        if ($version === null || $version === '') {
            throw new RuntimeException('Unable to determine package version via Composer\InstalledVersions');
        }

        if (str_starts_with($version, 'dev-')) {
            throw new RuntimeException('Dev version ('.$version.') cannot be used to download a GitHub release. Set a specific version tag.');
        }

        return ltrim($version, 'v');
    }

    /**
     * @return array<string, string>
     */
    protected function detectPlatform(): array
    {
        $os = strtolower(PHP_OS_FAMILY);
        $arch = php_uname('m');

        // Normalize OS names
        $osMap = [
            'windows' => 'Windows',
            'darwin' => 'Darwin',
            'linux' => 'Linux',
        ];

        // Normalize architecture names
        $archMap = [
            'x86_64' => 'x86_64',
            'amd64' => 'x86_64',
            'arm64' => 'arm64',
            'aarch64' => 'arm64',
            'i386' => 'i386',
            'i686' => 'i386',
        ];

        $normalizedOs = $osMap[$os] ?? null;
        $normalizedArch = $archMap[strtolower($arch)] ?? null;

        if (! $normalizedOs || ! $normalizedArch) {
            throw new RuntimeException('Unsupported platform');
        }

        $extension = $normalizedOs === 'Windows' ? 'zip' : 'tar.gz';

        return [
            'os' => $normalizedOs,
            'arch' => $normalizedArch,
            'extension' => $extension,
        ];
    }

    /**
     * @param  array<string, string>  $platform
     */
    protected function downloadAndInstall(string $version, array $platform, string $targetPath): void
    {
        $archiveName = sprintf(
            '%s_%s_%s.%s',
            self::BINARY_NAME,
            $platform['os'],
            $platform['arch'],
            $platform['extension']
        );

        $downloadUrl = sprintf(
            'https://github.com/%s/releases/download/v%s/%s',
            self::GITHUB_REPO,
            $version,
            $archiveName
        );

        $checksumUrl = sprintf(
            'https://github.com/%s/releases/download/v%s/%s_%s_checksums.txt',
            self::GITHUB_REPO,
            $version,
            self::BINARY_NAME,
            $version
        );

        $this->line('Downloading from: '.$downloadUrl);
        $archiveContent = $this->downloadArchive($downloadUrl);

        if (! $this->option('skip-checksum')) {
            $checksumFile = $this->downloadChecksums($checksumUrl);
            $this->verifyChecksum($archiveContent, $checksumFile, $archiveName);
        } else {
            $this->warn('Skipping checksum verification');
        }

        // Save archive to temp file with correct extension for PharData
        $tempArchive = tempnam(sys_get_temp_dir(), 'lcodegen_');
        rename($tempArchive, $tempArchive.'.'.$platform['extension']);
        $tempArchive .= '.'.$platform['extension'];

        file_put_contents($tempArchive, $archiveContent);

        try {
            $this->extractBinary($tempArchive, $targetPath, $platform['extension']);

            if ($platform['os'] !== 'Windows') {
                chmod($targetPath, 0755);
            }
        } finally {
            @unlink($tempArchive);
        }
    }

    private function downloadArchive(string $url): string
    {
        $response = Http::retry(3, 100)
            ->withOptions(['timeout' => 120])
            ->withHeaders(['User-Agent' => 'laravel-codegen'])
            ->get($url);

        if ($response->failed()) {
            throw new RuntimeException("Failed to download from $url (HTTP {$response->status()})");
        }

        return $response->body();
    }

    private function downloadChecksums(string $url): string
    {
        $response = Http::retry(3, 100)
            ->withOptions(['timeout' => 30])
            ->withHeaders(['User-Agent' => 'laravel-codegen'])
            ->get($url);

        if ($response->failed()) {
            throw new RuntimeException("Failed to download checksums file from $url (HTTP {$response->status()}). Use --skip-checksum to skip verification.");
        }

        return $response->body();
    }

    protected function verifyChecksum(string $content, string $checksumFile, string $filename): void
    {
        $actualChecksum = hash('sha256', $content);

        // Parse checksums file
        $lines = explode("\n", $checksumFile);
        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (isset($parts[1]) && $parts[1] === $filename) {
                $expectedChecksum = $parts[0];

                if ($actualChecksum !== $expectedChecksum) {
                    throw new RuntimeException('Checksum verification failed');
                }

                $this->info('Checksum verified successfully');

                return;
            }
        }

        throw new RuntimeException('Checksum for '.$filename.' not found in checksums file');
    }

    protected function extractBinary(string $archivePath, string $targetPath, string $extension): void
    {
        if ($extension === 'zip') {
            $zip = new ZipArchive;
            if ($zip->open($archivePath) === true) {
                $binaryName = self::BINARY_NAME.'.exe';
                $binaryContent = $zip->getFromName($binaryName);

                if ($binaryContent === false) {
                    // Try without .exe extension
                    $binaryName = self::BINARY_NAME;
                    $binaryContent = $zip->getFromName($binaryName);
                }

                if ($binaryContent === false) {
                    throw new RuntimeException('Binary not found in archive');
                }

                file_put_contents($targetPath, $binaryContent);
                $zip->close();
            } else {
                throw new RuntimeException('Failed to open zip archive');
            }
        } else {
            // Extract tar.gz
            $phar = new PharData($archivePath);
            $tempDir = sys_get_temp_dir().'/lcodegen_extract_'.uniqid();
            mkdir($tempDir, 0755, true);

            try {
                $phar->extractTo($tempDir, self::BINARY_NAME);

                $extractedPath = $tempDir.'/'.self::BINARY_NAME;
                if (! file_exists($extractedPath)) {
                    throw new RuntimeException('Binary not found in archive');
                }

                rename($extractedPath, $targetPath);
            } finally {
                @unlink($tempDir.'/'.self::BINARY_NAME);
                @rmdir($tempDir);
            }
        }
    }
}
