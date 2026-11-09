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

## Authorizing API Tokens

Sometimes a token should be able to do less than the user who owns it. For example, an integration may only need to edit articles. You may limit a token to a list of permissions. The user keeps their own role, and the token can use only the permissions that both of them have. If the user loses a permission, the token loses it too.

### Sanctum

The abilities of a Sanctum token are plain strings, so you may store permission codes in them. For example, let the user choose the permissions of a token from the permissions of a team:

```php
$permissions = $team->permissions()
    ->whereIn('code', $request->input('permissions', []))
    ->pluck('code');

$token = $request->user()->createToken('Article integration', $permissions->all());
```

Next, define the limit using the `scope` method of the `Teams` facade. You may call this method from the `boot` method of your `AppServiceProvider`. The callback receives the authenticated user and returns the `Scope` of the current request, or `null` when the request has no limit:

```php
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Jurager\Teams\Facades\Teams;
use Jurager\Teams\Scope;
use Laravel\Sanctum\PersonalAccessToken;

Teams::scope(function (Authenticatable $user): ?Scope {
    $token = $user instanceof User ? $user->currentAccessToken() : null;

    if (! $token instanceof PersonalAccessToken || $token->can('*')) {
        return null;
    }

    return Scope::to($token->abilities);
});
```

Requests without a token, such as sessions, and tokens with the `*` ability have no limit. A token without permissions can do nothing.

The `to` method accepts permission codes, enums, and arrays of them. A code such as `articles.*` covers every permission under `articles`.

Your routes don't change. The `can` middleware works for limited tokens, unlimited tokens, and sessions alike:

```php
Route::put('/articles/{article}', [ArticleController::class, 'update'])
    ->middleware('auth:sanctum')
    ->can('articles.edit', 'article');
```

> [!NOTE]
> The limit applies to the [permission checks](#checking-permissions) that go through the Gate. Checks made with the `member` methods, such as `$team->member($user)->can('articles.edit')`, aren't limited.

### Passport

Passport tokens carry [scopes](https://laravel.com/docs/passport#defining-scopes). Register the codes of your permissions as scopes using the `Passport::tokensCan` method, then pass the scopes of the access token to the `to` method:

```php
use App\Models\User;
use Laravel\Passport\AccessToken;

Teams::scope(function (Authenticatable $user): ?Scope {
    $token = $user instanceof User ? $user->currentAccessToken() : null;

    return $token instanceof AccessToken && ! $token->can('*')
        ? Scope::to($token->oauth_scopes)
        : null;
});
```

With Passport 12, use `$user->token()` and `$token->scopes` instead.

### Other Providers

With JWT or any other provider, build the `Scope` from the permissions of the verified token. Return `null` only for requests that have no limit.

### Limiting to a Team or a Record

By default, a token works in every team of its user. To limit it to one team, use the `in` method. It accepts a team or its key:

```php
Scope::to($permissions)->in($team);
```

To limit a token to one record, use the `on` method:

```php
Scope::to($permissions)->on($article);
```

The token then passes only the checks on that team and its records, or on that record. Store the team or the record with the token, and pass it to the scope in your callback.

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

## Querying Users by Permission

Use the `permission` scope to query users who have a team-wide permission:

```php
$users = User::permission('invoices.approve', $team)
    ->orderBy('email')
    ->paginate(15);
```

The scope matches `$team->member($user)->can($permission)`: it includes the owner and members whose assigned role or team groups grant the permission, including wildcards. Global groups and record rules apply to record checks and aren't included in this query.

The query returns each user once, and accepts string-backed permission enums. Application gates and policies aren't evaluated.

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
