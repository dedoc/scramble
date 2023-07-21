---
title: Installation & setup
weight: 1
---

Scramble requires:
- PHP 8.1 or higher
- Laravel 8.x or higher

You can install Scramble via composer:

```shell
composer require dedoc/scramble
```

When Scramble is installed, 2 routes are added to your application:

- `/docs/api` - UI viewer for your documentation
- `/docs/api.json` - Open API document in JSON format describing your API.

## Models attributes support

To support model attributes/relations types in JSON resources you need to have the `doctrine/dbal` package installed.

If it is not installed, install it via composer:

```shell
composer require doctrine/dbal
```

Without the `doctrine/dbal` package, Scramble won't know the types of model attributes, so they will be shown as `string` in resulting docs.

### `Unknown database type` exception

If you get this exception, simply add a type mapping into database config, as shown in this comment: https://github.com/dedoc/scramble/issues/98#issuecomment-1374444083

## Publishing config

Optionally, you can publish the package's config file:

```sh
php artisan vendor:publish --provider="Dedoc\Scramble\ScrambleServiceProvider" --tag="scramble-config"
```

This will allow you to customize the OpenAPI document info and add custom extensions.
