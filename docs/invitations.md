---
title: Invitations
weight: 100
---

# Invitations

## Sending Invitations

To invite someone to a team, call the `invite` method with their email and pass the role to `as`:

```php
$invitation = $team->invite('jane@example.com')->as('editor');
```

The user receives an email with a signed link. Inviting the same email again updates the role and sends the email again.

Pending invitations are available through `$team->invitations`.

Inviting the owner or an existing member throws a `RuntimeException`.

## Limiting Invitations

Rate limiting is disabled by default. Enable it in `config/teams.php` to limit new invitations and resends:

```php
'invitations' => [
    // ...
    'rate_limit' => [
        'enabled' => true,
        'team' => ['max_attempts' => 10, 'decay_seconds' => 3600],
        'email' => ['max_attempts' => 1, 'decay_seconds' => 60],
    ],
],
```

These values allow 10 attempts per team per hour and one attempt per email within that team per minute. Both windows start with their first attempt. Use positive integers for the allowances and durations. Email addresses share a limit regardless of case or surrounding whitespace. Teams on different database connections have separate limits.

An exhausted limit throws Laravel's `ThrottleRequestsException`, which renders as HTTP `429` with a `Retry-After` header in seconds. The rejected attempt does not change the invitation, send mail, dispatch invitation events, or consume either allowance.

The limiter uses Laravel's `cache.limiter` store, falling back to the default cache store. Configure a shared store for applications running on multiple servers.

Attempts count after checking the role and existing membership, before writing the invitation. Cache counters are independent of database transactions: a later rollback, cancelled save, or mail failure does not restore the allowance. Emails and completion events still wait for the database transaction to commit.

## Accepting Invitations

The link in the email leads to the built-in route, which adds the user to the team with the invited role when:

- the user is signed in with the invited email;
- the link hasn't been changed and is not older than `invitations.expire`, 7 days by default.

Otherwise, the route returns a `403` response. After accepting, the user is redirected to `/`.

> [!WARNING]
> Keep `auth` in `invitations.routes.middleware`. It stops mail scanners, which open links automatically, from accepting invitations.

> [!NOTE]
> The invited user must already have an account. A user who is already in the team keeps their role.

## Listing a User's Invitations

The `invitations` relationship of a user returns the invitations sent to their email, for example to show them after the user signs in:

```php
foreach ($user->invitations as $invitation) {
    $invitation->team->name;
    $invitation->role->name;
}
```

To accept or decline an invitation yourself, use the `accept` and `decline` methods:

```php
$invitation->accept();

$invitation->decline();
```

## Pruning Expired Invitations

Expired invitations stay in the database. To delete them, schedule Laravel's `model:prune` command for the invitation model:

```php
use Jurager\Teams\Models\Invitation;

Schedule::command('model:prune', ['--model' => [Invitation::class]])->daily();
```

An invitation is pruned once `invitations.expire` has passed since its email was last sent. When `expire` is `null`, invitations are never pruned.

## Using Your Own Route

To handle invitations yourself, set `invitations.routes.register` to `false` and define a route with the same name. The email will link to your route:

```php
Route::get('/invitations/{invitation_id}', function (Request $request, int $invitation_id) {

    $invitation = Invitation::findOrFail($invitation_id);

    abort_unless($invitation->user?->is($request->user()), 403);

    $invitation->accept();

    return redirect('/dashboard');
    
})->middleware(['auth', 'signed'])->name('teams.invitations.accept');
```

## Customizing the Email

To change the email, publish the views:

```shell
php artisan vendor:publish --tag=teams-views
```

Then edit `resources/views/vendor/teams/emails/invitation.blade.php`.

The view receives the `$invitation` and the link as `$url`.
