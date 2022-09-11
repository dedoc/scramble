<p>
  <a href="https://savelife.in.ua/en/donate-en/" target="_blank">
    <img src="./.github/gh-promo.svg?_=1" alt="Donate"/>
  </a>
</p> 

# Scramble

Scramble generates docs for your API in OpenAPI 3.1.0 format automatically from your code.

The main motto of the project is generating your API documentation without requiring you to annotate your code.

This allows you to focus on code and avoid annotating every possible param/field as it may result in outdated documentation. By generating docs automatically from the code your API will always have up-to-date docs which you can trust.

# Installation
You can install the package via composer:
```shell
composer require dedoc/scramble
```

# Usage
After install you will have 2 routes added to your application:

- `/docs/api` - UI viewer for your documentation
- `/docs/api.json` - Open API document in JSON format describing your API.

