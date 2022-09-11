---
title: Installation & setup
weight: 1
---
You can install the package via composer:

```shell
composer require dedoc/scramble
```

When the package installed, 2 routes are added to your application:

- `/docs/api` - UI viewer for your documentation
- `/docs/api.json` - Open API document in JSON format describing your API.

## Models attributes support

To support model attributes/relations types in JSON resources you need to have `doctrine/dbal` package installed.

If it is not installed, install it via composer:

```shell
composer require doctrine/dbal
```

Without the `doctrine/dbal`, Scramble won't know the types of model attributes, so they will be shown as `string` in resulting docs.

## Publishing config

Optionally, you can publish package's config file:

```sh
php artisan vendor:publish --provider="Dedoc\Scramble\ScrambleServiceProvider" --tag="scramble-config"
```

This will allow you to add custom extensions.
