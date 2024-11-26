<p>
  <a href="https://scramble.dedoc.co" target="_blank">
    <img src="./.github/gh-img.png?v=1" alt="Scramble – Laravel API documentation generator"/>
  </a>
</p>

<p align="center">
    <a href="https://github.com/dedoc/scramble/actions"><img src="https://github.com/dedoc/scramble/actions/workflows/run-tests.yml/badge.svg" alt="Build Status"></a>
    <a href="https://packagist.org/packages/dedoc/scramble"><img src="https://img.shields.io/packagist/dt/dedoc/scramble" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/dedoc/scramble"><img src="https://img.shields.io/packagist/v/dedoc/scramble" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/dedoc/scramble"><img src="https://img.shields.io/packagist/l/dedoc/scramble" alt="License"></a>
</p>

# Scramble

Scramble generates API documentation for Laravel project. Without requiring you to manually write PHPDoc annotations. Docs are generated in OpenAPI 3.1.0 format.

> **Warning**
> Package is in early stage. It means there may be bugs and API is very likely to change. Create an issue if you spot a bug. Ideas are welcome.

## Documentation

You can find full documentation at [scramble.dedoc.co](https://scramble.dedoc.co).

## Introduction

The main motto of the project is generating your API documentation without requiring you to annotate your code.

This allows you to focus on code and avoid annotating every possible param/field as it may result in outdated documentation. By generating docs automatically from the code your API will always have up-to-date docs which you can trust.

## Installation
You can install the package via composer:
```shell
composer require dedoc/scramble
```

## Usage
After install you will have 2 routes added to your application:

- `/docs/api` - UI viewer for your documentation
- `/docs/api.json` - Open API document in JSON format describing your API.

By default, these routes are available only in `local` environment. You can change this behavior by defining `viewApiDocs` gate.

## Contributing

We appreciate your feedback and contributions to this library! Before you get started, please review following guidelines.

### Reporting Issues or Bugs
If you've encountered a bug or have suggestions for improvement, [please raise an issue](https://github.com/dedoc/scramble/issues). Provide as much detail as possible to help us address the issue efficiently.

### Submitting Changes
To contribute code changes or enhancements, follow the guidelines in the [Contribution Guide](./.github/CONTRIBUTING.md). This ensures your contributions align with the pr

---

<p>
  <a href="https://savelife.in.ua/en/donate-en/" target="_blank">
    <img src="./.github/gh-promo.svg?v=1" alt="Donate"/>
  </a>
</p> 
