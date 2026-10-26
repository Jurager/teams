---
title: Facade
weight: 125
---

# Facade

The `Teams` facade returns the configured class of a model:

```php
use Jurager\Teams\Support\Facades\Teams;

Teams::model('team'); // 'App\Models\Team'
```

Use it when your code must work with whatever class is configured.

An unknown key, or a class that doesn't extend its package model, throws a `RuntimeException`.
