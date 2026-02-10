# l-codegen

Laravel package that downloads and installs the `lcodegen` Go binary from [GitHub Releases](https://github.com/grnsv/lcodegen/releases) into your Laravel project's `vendor/bin` directory.

## Features

- Artisan commands for binary installation and code generation
- Multi-platform support (Linux, macOS, Windows)
- Multi-architecture support (x86_64, arm64, i386)
- Checksum verification for security
- Lightweight package (binary is downloaded, not included in package)
- Version synchronization with `composer.json`

## Installation

1. Install the package:
```bash
composer require grnsv/l-codegen
```

2. Install the binary:
```bash
php artisan l-codegen:install
```

## Usage

Generate Laravel code from an OpenAPI specification:

```bash
php artisan l-codegen:generate openapi.yml
```

Or call the binary directly:

```bash
vendor/bin/lcodegen openapi.yml
```

## How It Works

1. Run the `php artisan l-codegen:install` command
2. It detects your operating system and CPU architecture
3. Downloads the appropriate binary from GitHub Releases based on the version in `composer.json`
4. Verifies the downloaded binary using SHA256 checksums
5. Extracts and installs the binary to `vendor/bin/lcodegen`
6. Makes the binary executable (on Unix systems)

## Supported Platforms

- **Linux**: x86_64, arm64, i386
- **macOS**: x86_64 (Intel), arm64 (Apple Silicon)
- **Windows**: x86_64, arm64, i386

## Version Management

The version of the binary is determined by the `version` field in this package's `composer.json`. To update to a new version of `lcodegen`, update this package to the corresponding version.

## Requirements

- PHP >= 8.2
- Composer >= 2.0
- Laravel >= 11.0 || >= 12.0

## Development

### Running Tests

```bash
composer test
```

### Running Tests with Coverage

```bash
composer test-coverage
```

### Test Coverage

The package includes integration tests that verify:
- Command registration in Laravel
- Platform detection (OS and architecture)
- Binary installation process
- Code generation via Artisan command
- Service provider auto-discovery

## Troubleshooting

### Binary not found after installation

Run the install command manually:

```bash
php artisan l-codegen:install
```

### Checksum verification failed

This might indicate a corrupted download or a network issue. Try:

```bash
rm -rf vendor/grnsv/l-codegen
composer install
```

### Permission denied

On Unix systems, ensure the binary is executable:

```bash
chmod +x vendor/bin/lcodegen
```

## License

MIT

## Links

- [lcodegen GitHub Repository](https://github.com/grnsv/lcodegen)
- [Issues](https://github.com/grnsv/l-codegen/issues)
