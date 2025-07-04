<p>
  <a href="https://scramble.dedoc.co" target="_blank">
    <img src="./.github/gh-img.png?v=1" alt="Scramble – Laravel API documentation generator"/>
  </a>
</p>

# Scramble

Scramble generates API documentation for Laravel project. Without requiring you to manually write PHPDoc annotations. Docs are generated in OpenAPI 3.1.0 format.

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

By default, these routes are available only in `local` environment. You can change this behavior [by defining `viewApiDocs` gate](https://scramble.dedoc.co/usage/getting-started#docs-authorization).

---

<p>
  <a href="https://savelife.in.ua/en/donate-en/" target="_blank">
    <img src="./.github/gh-promo.svg?v=1" alt="Donate"/>
  </a>
</p> 
