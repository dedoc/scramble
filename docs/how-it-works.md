---
title: How it works
weight: 2
---
Scramble is a package for Laravel that generates API documentation using static code analysis and Laravel conventions.

Other packages usually require you to write PHPDoc annotations in your code, which is not always convenient. It may result in:

- More work to keep annotations up to date with code changes.
- Code duplication, where information is redundantly stated in annotations and the code itself.
- Challenges during code refactoring, potentially leading to outdated documentation if annotations are not updated accordingly.

Scramble gives you the power to use annotations only when you want more control over your docs' generation. You can add descriptions to parameters or tweak response types for your API routes, etc.

After installation, Scramble adds two routes to your application. `/docs/api` route is the UI. It triggers `/docs/api.json` route and shows the documentation in a nice UI.

The `/docs/api.json` route generates the OpenAPI document describing your API. Here is what happens behind the scenes.

## Gathering API Routes
First of all, Scramble gathers your API routes by retrieving all routes from the application and then filtering them using `api` middleware.

You can customize this behavior by either publishing the package's configuration file or providing your own route filter function.

Next, Scramble analyzes each API route's corresponding controller method. It aims to determine both the request type and response type for the route.

## Route to Request Documentation
To describe the request, Scramble analyzes validation rules and route parameters. 

When `FormRequest` is used for the request, Scramble will analyze `rules` method. 

If a custom request class is not used, Scramble will look for a call to `validate` in a controller's method and will use rules from there **by evaluating** the rules' array code.

## Route's Responses Documentation
To document responses, Scramble analyzes the return type of the controller's method using static code analysis.

It then proceeds to document the controller's method return type as a route's response. Thanks to being in Laravel context, Scramble does a lot of things automatically, such as documenting JSON API resources, resources collections, etc.

For instance, if you return a `PostResource` (a JSON API resource) from your controller's method, Scramble performs the following actions:

- Identifies the return type of your method as `PostResource`.
- Analyzes the `toArray` method of the `PostResource` class and documents its attributes as response fields.

Scramble takes into consideration other scenarios to cover not only successful responses:

- It accounts for cases when `validate` or `FormRequest` is used, which may result in a 422 response.
- It handles situations where `authorize` or `Gate` is used, leading to a possible 403 response.
- Scramble also considers the usage of `abort`, `abort_if`, and `abort_unless`, which could lead to 4xx or 5xx responses.
- Furthermore, any exceptions thrown during the process are accounted for, which may also result in 4xx or 5xx responses.

## Putting It All Together
After analyzing all routes, Scramble merges all gathered information into a single OpenAPI document.
