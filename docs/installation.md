---
title: Installation & setup
weight: 1
---
You can install the package via composer:

```shell
composer require dedoc/${packageName}
```

When the package is installed, 2 routes will be added to your application.

- `/docs/api` - UI viewer for your documentation
- `/docs/api.json` - Open API document in JSON format describing your API.

## Models Definition generation support

To support automatic inferring of model attributes/relations types in JSON resources you need to have `doctrine/dbal` package installed.

If it is not installed, install it via composer:

```shell
composer require doctrine/dbal
```
