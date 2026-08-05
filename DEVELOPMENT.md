[<img src="https://otgh-static-assets.s3.otgh.cloud/branding/logos/otgh_cloud_2024.png" alt="OTGH Cloud" width="200px" />](https://github.com/otghcloud/laravel-edgelink)

# Development

## Package-Local CLI

Run live RTU checks directly from this package repo without a separate Laravel app.

## Quick Start

You can run live RTU commands directly from this package repo:

```bash
php development/rtu-cli.php --help
```

Or via Composer scripts:

```bash
composer rtu:cli -- --help
```

## Local Config

Set connection values either with flags or environment variables:

```bash
export EDGELINK_BASE_URL="https://192.168.1.10"
export EDGELINK_PASSWORD="your-password"
```

For repeat local use, copy [development/config.php.dist](development/config.php.dist) to `development/config.php` and set local defaults there.
That local config file is gitignored to prevent accidental credential commits.

```bash
cp development/config.php.dist development/config.php
```

## Common Commands

Examples:

```bash
# Version check
composer rtu:cli -- version

# List tags with envelope output
composer rtu:cli -- tags:list --response-mode=envelope

# Read a single tag
composer rtu:cli -- tags:read --name=TestUserTagOne

# Write a tag value
composer rtu:cli -- tags:value --name=TestUserTagOne --value=1

# IO read
composer rtu:cli -- io:read --type=ai --slot=0 --channel=0

# IO write
composer rtu:cli -- io:write --type=do --slot=0 --channel=0 --value=0

# Quick probe suite
composer rtu:probe -- --response-mode=data

# Dual-profile end-to-end harness (read-only by default)
composer rtu:e2e -- --profiles=latest

# Dual-profile harness with value-preserving write checks enabled
composer rtu:e2e:writes -- --profiles=legacy,latest

# Format code
composer format

# Static analysis
composer analyse

# Full test suite
composer test

# Coverage report (local)
composer test-coverage
```

## Dual-RTU End-to-End Harness

Use the E2E harness to run the same compatibility checks against multiple RTU profiles and collect failures in one report set.

### Profile Setup

Copy [development/profiles.php.dist](development/profiles.php.dist) to `development/profiles.php` and configure each profile:

```bash
cp development/profiles.php.dist development/profiles.php
```

Notes:

- `development/profiles.php` is gitignored.
- Define at least `legacy` and `latest` with `base_url` and `password`.
- Adjust `io_targets` if your channels differ from slot/channel defaults.

### Run Modes

- Read-only default:

```bash
composer rtu:e2e -- --profiles=legacy,latest
```

- Value-preserving write checks (explicitly enabled):

```bash
composer rtu:e2e -- --profiles=legacy,latest --allow-write=true
```

- Fail shell/CI on any failing check:

```bash
composer rtu:e2e -- --profiles=legacy,latest --fail-on-failures=true
```

### Outputs

Each run generates:

- Console summary table (pass/fail/skip per profile)
- JSON artifact (full structured check details)
- Markdown artifact (triage-friendly summary)

By default artifacts are written under `development/reports`.

## Quality Policy

- CI enforces formatting, static analysis, and PHPUnit.
- Coverage is generated in CI and must meet the configured minimum threshold.
- PRs should keep canonical endpoint API usage in code examples and docs.

## Troubleshooting

- `composer rtu:cli -- version` exits with code `2`: check that credentials are available via flags, env vars, or `development/config.php`.
- Auth errors such as `Unable to authenticate before request`: verify base URL, password, referer, and RTU reachability.
