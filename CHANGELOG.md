# Changelog

## 4.2.1

### New

- `User::permission($permission, $team)` queries users granted a team-wide permission by ownership, their assigned role, or team groups, including wildcards and enum codes.
- `$team->add($users)->as($role)` and `$team->remove($users)` accept arrays, collections, and iterables. Batches are atomic, preserve membership observers, and dispatch events per distinct user after commit.
- `allow` and `forbid` rules accept `until($date)`. Deadlines belong to individual assignments, are exposed as Carbon instances on the pivot, and are checked on every authorization, including preloaded rules. `AbilityUpdated` reports deadline changes through `previousExpiresAt` and `expiresAt`.
- A published migration adds a nullable `expires_at` column to existing record rule assignments.

### Fixed

- The database diagram matches the migration columns, nullability, relationships, and unique constraints.

## 4.2.0

### New

- `Member::inspect` returns Laravel's authorization response with the source and reason of an access decision. The response can be returned from gates and policies or authorized directly.
- `PermissionsUpdated`, `GroupMembersUpdated`, and `AbilityUpdated` events report actual assignment changes after commit.
- String-backed enums are accepted for role, group, and permission codes, including mixed arrays in permission methods.
- `$user->accessible()` queries owned and joined teams in one SQL statement, with filtering, sorting, and pagination.
- `$team->rules($record)` queries explicit record rules with their permissions and user, role, and group recipients.
- Unassigned ability definitions can be removed with Laravel's `model:prune`, with assignments rechecked under a lock before deletion.

### Fixed

- Gate integration preserves application authorization responses, typed after callbacks, and grants from later after callbacks.
- Record checks deny models with a missing or null team key, including partially selected models.
- Cancelling role or group deletion preserves their permissions and record rules. Cancelling a team purge preserves its members.
- Accessible team queries and record authorization use the model's database connection.
- Ownership transfers use the current locked team state, including transfers from stale model instances.
- Cancelled membership changes, ownership transfers, and invitation writes roll back without dispatching completion events or sending invitations.
- Invitations reject role models from another team before saving or sending email.
- Ownership transfers clear loaded member and ownership relationships for the team and both owners.

## 4.1.0

### New

- `$team->transfer($user)` makes another user the team owner. Pass a role to keep the previous owner as a member: `$team->transfer($user, 'admin')`.
- `TeamTransferring` and `TeamTransferred` events.
- `Group`, `Member`, and `Invitable` validation rules.
- `$user->invitations` returns the invitations sent to the user's email, and `$invitation->decline()` declines one.
- Expired invitations can be deleted with Laravel's `model:prune` command.
- Factories for teams, roles, groups, and invitations.
- `$team->member($user)->load($articles)` loads the rules for many records at once, so checks on them run no extra queries.
- `grant` and `sync` accept `code => name` pairs to name permissions.

### Changed

- New permissions are named after their code, so `articles.edit` is named `Articles Edit`.


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
