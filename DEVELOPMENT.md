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
```

## Troubleshooting

- `composer rtu:cli -- version` exits with code `2`: check that credentials are available via flags, env vars, or `development/config.php`.
- Auth errors such as `Unable to authenticate before request`: verify base URL, password, referer, and RTU reachability.
