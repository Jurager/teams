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
