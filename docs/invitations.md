---
title: Invitations
weight: 100
---

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
