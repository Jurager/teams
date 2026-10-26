---
title: Authorization
weight: 50
---

## Checking Permissions

Team permissions are part of Laravel's [authorization](https://laravel.com/docs/authorization) features. To check a permission, pass the team, or a model with a `team_id` column, to the user's `can` method:

```php
$user->can('articles.edit', $team);

$user->can('articles.edit', $article);
```

To check several permissions at once, pass an array to `can`, which requires all of them, or to `canAny`, which requires at least one:

```php
$user->can(['articles.edit', 'articles.publish'], $team);

$user->canAny(['articles.edit', 'articles.publish'], $team);
```

Since the checks go through the Gate, the `@can` directive, the `can` middleware, and `Gate::authorize` work as well:

```blade
@can('articles.publish', $article)
    <button>Publish</button>
@endcan
```

```php
Route::put('/articles/{article}', [ArticleController::class, 'update'])
    ->can('articles.edit', 'article');

Gate::authorize('articles.delete', $article);
```

A member's permissions come from their role and their groups in the team. The team owner passes every check, and users outside the team fail every check.

> [!NOTE]
> Your own gates and policies are always asked first, and the package only answers when they don't. This means a policy can always deny access. To turn the integration off, set the `gate` [configuration](configuration.md#gate) option to `false`.

## Member Checks

The `member` method returns the user's role and permissions in a single team:

```php
$member = $team->member($user);

$member->role();                // Role, Owner, or null
$member->is('editor', 'admin'); // true if the user has any of the roles
$member->permissions();         // ['articles.view', 'articles.edit']

$member->can('articles.edit');
$member->can('articles.edit', $article);
$member->canAny(['articles.edit', 'articles.publish']);
```

Member checks don't use the Gate, so they also work in queued jobs and console commands, where there is no authenticated user.

## Protecting Team Changes

Methods such as `add` and `remove` don't check who calls them. You should authorize these actions yourself, for example with a policy:

```php
class TeamPolicy
{
    public function addMember(User $user, Team $team): bool
    {
        return $user->can('members.add', $team);
    }
}
```

Register the policy in the `boot` method of your `AppServiceProvider`:

```php
Gate::policy(config('teams.models.team'), TeamPolicy::class);
```

Then authorize the action before changing the team:

```php
Gate::authorize('addMember', $team);

$team->add($user)->as('editor');
```
