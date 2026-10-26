---
title: Troubleshooting
weight: 150
---

# Troubleshooting

- [Access Is Denied](#access-is-denied)
- [The Middleware Returns 404](#the-middleware-returns-404)
- [A Permission Change Is Not Visible](#a-permission-change-is-not-visible)
- [An Invitation Link Returns 403](#an-invitation-link-returns-403)

## Access Is Denied

Check what the user actually has:

```php
$team->hasUser($user);
$user->teamRole($team);
$user->teamPermissions($team);
```

For record checks, a forbid rule may apply. See [how access is decided](abilities.md#how-access-is-decided).

## The Middleware Returns 404

The team was not found. Make sure the route has a `team_id` parameter, or pass the parameter name: `permission:articles.view,team`.

## A Permission Change Is Not Visible

If the [decision cache](configuration.md#decision-cache) is enabled and you changed a pivot table directly, call `Teams::forgetCachedPermissions()`. Relations loaded earlier keep their old values until you reload them.

## An Invitation Link Returns 403

- The user must be signed in with the invited email.
- The link must not be changed or expired.
- `APP_URL` must match the domain the user opens.
