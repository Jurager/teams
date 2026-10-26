# Changelog

## 4.0.0

Teams 4.0 brings a fluent API, native Gate support, and safer invitations.

```php
$team = $owner->teams()->create(['name' => 'Acme']);

$team->roles()->create(['code' => 'editor'])->grant('articles.view', 'articles.edit');
$team->add($user)->as('editor');
$team->role('editor')->forbid('articles.delete')->on($article);

$user->can('articles.edit', $team);      // true
$user->can('articles.delete', $article); // false
```

This release contains breaking changes. See the [upgrade guide](https://docs.gerassimov.me/teams/upgrade-guide/).

### New

- Fluent API for teams, members, roles, groups, permissions, and record rules.
- `$user->can()`, `@can`, `Gate::authorize()`, and the `can` middleware understand team permissions. Your gates and policies are asked first.
- `$team->member($user)` gives a user's role, permissions, and checks in one team.
- Global groups have permissions of their own.
- Invitation links expire after 7 days.
- "After" events are dispatched once the transaction commits and support queued listeners.

### Changed

- Requires PHP 8.3+ and Laravel 10.30+.
- The `permission`, `ability`, and `role` middleware are replaced by Laravel's `can`.
- The invitation route requires the invited user to be signed in.
- Custom record models must extend the package models.
- The decision cache and the `teams:install` command are removed.

### Fixed

- Records of another team are always denied, even to the team owner.
- Membership rows are validated: the owner cannot be a member, and the role must belong to the team.
- Removing a member also removes their groups and record rules in that team.
- Resending an invitation updates it instead of creating a duplicate.
- Removing a record rule no longer creates permission or ability records.
- Configured table names, foreign keys, and morph aliases are used everywhere.
