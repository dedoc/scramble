---
title: Restricting access to docs
weight: 4
---

The access to docs in non-production environment is enabled by default.

If you need to allow access to docs in production environment, implement gate called `viewApiDocs`:

```php
Gate::define('viewApiDocs', function (User $user) {
    return in_array($user->email, ['admin@app.com']);
});
```
