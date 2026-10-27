---
title: Authorization
weight: 50
---

# Authorization

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

## Inspecting Access

Use `inspect` to get Laravel's `Illuminate\Auth\Access\Response`, including the reason and code of the decision:

```php
$response = $team->member($user)->inspect('articles.edit', $article);

$response->allowed(); // false
$response->message(); // 'Forbidden by a personal rule.'
$response->code();    // 'user_forbidden'
```

Omit the record to inspect a team-wide permission. Call `$response->authorize()` to throw an authorization exception with the denial reason.

To expose the reason through `Gate::inspect` and `Gate::authorize`, return the response from your gate or policy. This example uses the article's `team` relationship:

```php
Gate::define('articles.edit', fn (User $user, Article $article) => $article->team
    ->member($user)->inspect('articles.edit', $article));
```

The automatic Gate integration grants matching permissions and leaves other decisions to Laravel. Application gates and policies keep their own responses.

| Code                                                  | Reason                                |
|-------------------------------------------------------|---------------------------------------|
| `team_owner`, `record_owner`                          | The user owns the team or record      |
| `global_group`                                        | A global group grants access          |
| `user_allowed`, `group_allowed`, `role_allowed`       | A record rule allows access           |
| `user_forbidden`, `group_forbidden`, `role_forbidden` | A record rule forbids access          |
| `group_permission`, `role_permission`                 | A group or role grants the permission |
| `not_member`                                          | The user isn't in the team            |
| `team_mismatch`                                       | Missing or different team             |
| `missing_permission`                                  | No rule or permission grants access   |

`can` and `inspect` use the same rules and [precedence](abilities.md#how-access-is-decided). Both work with [preloaded records](abilities.md#checking-many-records).

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
