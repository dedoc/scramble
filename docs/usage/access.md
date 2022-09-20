---
title: Docs authorization
weight: 5
---

Scramble exposes docs at the `/docs/api` URI. By default, you will only be able to access this route in the `local` environment.

If you need to allow access to docs in `production` environment, implement gate called `viewApiDocs`:

```php
Gate::define('viewApiDocs', function (User $user) {
    return in_array($user->email, ['admin@app.com']);
});
```
