# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel package that downloads and installs the `lcodegen` Go binary from GitHub releases into a Laravel project's `vendor/bin` directory. It serves as a thin Composer wrapper around the Go binary, providing multi-platform support with checksum verification.

## Commands

### Running Tests
```bash
composer test
```

### Running Tests with Coverage
```bash
composer test-coverage
```

### Code Formatting
```bash
# Format all code
composer format

# Check formatting without modifying files
composer format:check

# Or use Pint directly
vendor/bin/pint
```

### Rector
```bash
# Apply Rector rules
composer rector

# Check without modifying files
composer rector:check
```

### Test Commands Locally
```bash
php artisan l-codegen:install              # Download lcodegen binary
php artisan l-codegen:generate openapi.yml # Generate code from OpenAPI spec
```

## Architecture

### Package Structure

- **Service Provider** (`LaravelCodegenServiceProvider`): Registers Artisan commands for console use
- **Install Command** (`Console/InstallCommand`): Downloads and installs the lcodegen binary from GitHub releases
- **Generate Command** (`Console/GenerateCommand`): Runs `vendor/bin/lcodegen` against an OpenAPI spec to generate Laravel code
- **Tests** (`tests/`): Integration tests using Orchestra Testbench

### Binary Installation Flow

1. Command detects OS (Linux/Darwin/Windows) and architecture (x86_64/arm64/i386)
2. Reads version via `Composer\InstalledVersions::getPrettyVersion()`
3. Constructs GitHub release URL: `https://github.com/grnsv/lcodegen/releases/download/v{VERSION}/{BINARY}_{OS}_{ARCH}.{EXTENSION}`
4. Downloads archive and checksum file from GitHub releases
5. Verifies archive SHA256 checksum against checksums.txt
6. Extracts binary from archive (ZIP for Windows, tar.gz for Unix)
7. Installs to `vendor/bin/lcodegen` with executable permissions (755 on Unix)

### Platform Detection

The `detectPlatform()` method normalizes:
- **OS**: Maps PHP_OS_FAMILY to GitHub release naming (Windows/Darwin/Linux)
- **Architecture**: Maps php_uname('m') output to standardized names (x86_64/arm64/i386)
- **Archive format**: Determines .zip for Windows, .tar.gz for Unix systems

### Checksum Verification

Downloads `{BINARY}_{VERSION}_checksums.txt` from GitHub releases and parses it to find the expected SHA256 hash for the downloaded archive. Fails if checksum file is unavailable (use `--skip-checksum` to bypass).

### Archive Extraction

- **ZIP archives** (Windows): Uses `ZipArchive` to extract binary, tries both `lcodegen.exe` and `lcodegen` filenames
- **tar.gz archives** (Unix): Uses `PharData` to iterate and extract the `lcodegen` binary

## Testing Notes

Tests use Orchestra Testbench to simulate a Laravel environment. The test suite:
- Verifies command registration in Artisan
- Tests downloading and installing the binary
- Validates checksum verification (valid, invalid, unavailable)
- Tests `--skip-checksum` flag
- Tests dev-version error handling
- Ensures service provider auto-discovery works

Tests clean up the `vendor/bin/lcodegen` binary in setUp/tearDown to ensure clean test state.

## CI/CD

### Code Style (`.github/workflows/code-style.yml`)
Uses Laravel Pint to enforce formatting. Runs `pint --test` on push/PR.

### Integration Tests (`.github/workflows/integration.yml`)
Matrix-based testing across platforms and Laravel versions:
- **OS**: Ubuntu, Windows, macOS
- **Laravel**: 11.x, 12.x
- **PHP**: 8.2, 8.3, 8.4

Each combination creates a fresh Laravel project, installs the package via path repository, runs `l-codegen:install`, and verifies the binary exists.

## Code Formatting

Run `composer format` to format code or `composer format:check` to check without modifying

## Version Management

The binary version is determined at runtime via `Composer\InstalledVersions::getPrettyVersion()`. The `version` field in `composer.json` is kept for path-repository usage during local development. When tagging a release, the Git tag becomes the version source. Dev versions (e.g., `dev-main`) are rejected since they don't correspond to a GitHub release.
