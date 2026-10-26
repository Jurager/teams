---
title: Invitations
weight: 100
---

# Invitations

- [Sending Invitations](#sending-invitations)
- [Accepting Invitations](#accepting-invitations)
- [Using Your Own Route](#using-your-own-route)
- [Customizing the Email](#customizing-the-email)

## Sending Invitations

```php
$team->inviteUser('jane@example.com', 'editor');
```

The user receives an email with a signed link. Inviting the same email again updates the role and resends the email. Pending invitations are available as `$team->invitations`.

## Accepting Invitations

The link leads to the built-in route. It adds the user to the team with the invited role when:

- the user is signed in with the invited email;
- the link is unchanged and not older than `invitations.expire` (7 days by default).

Otherwise, it responds with `403`. After accepting, the user is redirected to `/`.

> [!WARNING]
> Keep `auth` in `invitations.routes.middleware`. It stops mail scanners that open links automatically from accepting invitations.

> [!NOTE]
> The invited user must already have an account.

## Using Your Own Route

Set `invitations.routes.register` to `false` and define a route with the same name. The email links to it:

```php
Route::get('/invitations/{invitation_id}', function (Request $request, int $invitation_id) {
    $invitation = Invitation::findOrFail($invitation_id);

    abort_unless($invitation->user?->is($request->user()), 403);

    $invitation->team->inviteAccept($invitation->id);

    return redirect('/dashboard');
})->middleware(['auth', 'signed'])->name('teams.invitations.accept');
```

## Customizing the Email

```shell
php artisan vendor:publish --tag=teams-views
```

Edit `resources/views/vendor/teams/emails/invitation.blade.php`. The template receives `$invitation` and the link as `$url`.
