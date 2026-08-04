---
title: Installation
---

# Installation

## Requirements

- PHP 8.3+
- Laravel 13+

## Install Package

```bash
composer require otghcloud/laravel-edgelink
```

## Publish Configuration

```bash
php artisan vendor:publish --tag="laravel-edgelink-config"
```

This creates:

- `config/edgelink.php`

## Initial Environment Variables

```dotenv
EDGELINK_BASE_URL=https://192.168.1.10
EDGELINK_PASSWORD=your-password
EDGELINK_REFERER=https://192.168.1.10
EDGELINK_VERIFY_TLS=false
EDGELINK_TIMEOUT_SECONDS=10
```

Next:

- [Configuration](configuration.md)
- [Usage Examples](usage-examples.md)
