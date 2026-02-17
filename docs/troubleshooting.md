---
title: Troubleshooting
weight: 150
---

# Troubleshooting

## Middleware Always Denies

- Verify `team_id` is present in the route parameters or request input.
- Check that `teams.middleware.handling` is set correctly (`abort` vs `redirect`).
- Confirm the user has the expected role or group permissions in the selected team.
- If using `ability` middleware, make sure the entity ID is also present in the route or request.

> [!NOTE]
> Run `php artisan route:list` to confirm that middleware aliases are registered and applied to the correct routes.

## Permission Seems Missing

- Ensure the user belongs to the same team.
- Check the `scope` used in `hasTeamPermission()` (`'role'` / `'group'` / `null` for all).
- If using wildcards, confirm `teams.wildcards.enabled = true` and the configured nodes match the stored permission codes.
- Remember: team owners always return `true` from `hasTeamPermission()`.

> [!WARNING]
> With `teams.request.cache_decisions = true`, `hasTeamPermission()` returns cached results for the lifetime of the model instance. If you recently added a permission and the check still returns `false`, disable the cache or re-instantiate the user model.

## Ability Check Returns Wrong Result

- Double-check the access level table in [Abilities](abilities.md). `GROUP_FORBIDDEN` and `USER_ALLOWED` share value 5, creating a tie resolved in favor of **granting** access.
- Verify which ability records exist: inspect `entity_ability` directly in the database.
- Confirm the entity passed to `hasTeamAbility()` is the same instance (same ID and model class) used when the ability was created.

## Invitations Not Working

- Ensure `teams.invitations.enabled = true`.
- Ensure `teams.invitations.routes.register = true` (or register the route manually).
- Verify your mail transport (`MAIL_*` env vars) and `APP_URL` are correctly set — signed invitation URLs depend on `APP_URL`.
- If `inviteAccept()` throws "Invited user not found", the user with that email does not exist in the `users` table yet. The invitation system requires the invitee to already have an account.

> [!WARNING]
> The `signed` middleware is commented out in the package route. Without it, anyone who knows a valid invitation ID can accept it. Enable signed URL validation in production.

## Broken Schema After Install

- Re-check `tables.*` and `foreign_keys.*` in `config/teams.php`.
- Apply config changes **before** publishing or running migrations.
- If migrations already ran with wrong names, roll back with `php artisan migrate:rollback` and re-run after correcting the config.

> [!WARNING]
> `teams:install` publishes with `--force`. Running it on an existing project may overwrite `config/teams.php`, migrations, views, and stub files. Always create a backup first.

## RuntimeException in addUser / updateUser / deleteUser

These methods throw `RuntimeException` for predictable invalid inputs:

| Method        | Throws when                                              |
|---------------|----------------------------------------------------------|
| `addUser()`   | User is owner, user already in team, role not found      |
| `updateUser()`| User is owner, user not in team, role not found          |
| `deleteUser()`| User is owner, user not in team                          |
| `inviteUser()`| User already in team, role not found                     |
| `inviteAccept()`| Invitation not found, invited user not found           |

Wrap calls in `try/catch` when processing user input to return meaningful error messages.
