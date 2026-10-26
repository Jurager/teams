# Changelog

## 4.0.0

This release focuses on safer invitations, correct permission caching under Octane and queue workers, and a long list of fixes around membership, roles, and abilities. It contains breaking changes, so please read [Upgrading from 3.x](#upgrading-from-3x) before updating.

### Breaking changes

- PHP 8.3+ and Laravel 10.30+ are required. Laravel 8 and 9 are no longer supported.
- The `teams:install` command and its stubs are gone. Invitations are accepted by a controller that ships with the package, and authorization policies live in your application.
- The invitation route requires a signed-in user whose email matches the invitation. The default route middleware is `['web', 'auth']`.
- A missing role or invitation throws `ModelNotFoundException` instead of `RuntimeException`.
- `TeamCreating` receives the team being created instead of its owner.
- The `Owner` role has a `null` ID instead of `1`, so it can no longer collide with a stored role.
- `updateGroup()` keeps the current permissions when the permissions argument is omitted. Pass `[]` to remove them.
- Ability methods use camelCase argument names: `actionEntity`, `actionEntityId`, `targetEntity`.
- Model arguments and return values are typed as Eloquent `Model` instead of `object`.
- A custom membership model must extend Eloquent's `Pivot`.
- The base `Teams` middleware is abstract and no longer has an `authorization()` method. This only matters if you extended it.

### New

- Invitation links expire after 7 days. Change this with `teams.invitations.expire`, or set it to `null` to keep links valid forever.
- The `ability` middleware accepts several abilities: `ability:articles.edit|articles.delete,...` passes when the user has any of them.
- Team and member "after" events (`TeamCreated`, `TeamMemberAdded`, and others) are dispatched only after the database transaction commits, and are skipped on rollback.
- Events can be handled by queued listeners.
- `updateRole()` and `updateGroup()` accept named arguments and change only what you pass. Passing `description: null` clears a role description.

### Improvements

- The decision cache (`request.cache_decisions`) is scoped to one HTTP request, Octane request, or queued job, and is cleared whenever roles, groups, permissions, or memberships change.
- Resending an invitation updates the pending one instead of creating a duplicate, and the email is sent only after the invitation is saved.
- Removing a member also removes their groups and personal ability rules in that team.
- Relations are loaded only when needed, which cuts down queries on large teams.
- Roles and groups are deleted in a transaction together with their permissions and ability rules.
- The invitation route reads its prefix from `teams.invitations.routes.prefix`.

### Fixes

- A request field can no longer override the team taken from a route parameter in middleware.
- The `ability` middleware no longer ignores every ability after the first one.
- Ability middleware rejects records that belong to another team, even for team owners.
- Middleware handles guests, bound models, and the `require` flag consistently.
- Role and group codes that look like numbers are checked correctly for duplicates.
- Global groups grant abilities without team membership.
- Deleting an ability rule no longer creates missing permission or ability records.
- Morph map aliases are resolved the same way for model instances and class names.
- Accepting an invitation for an existing member deletes the invitation without changing their role.
- Membership rows are validated: the owner cannot be a member, and the role must belong to the same team.
- `Role::users()` uses the configured membership model.
- The owner role includes `code` when serialized to JSON.
- `allUsers()` no longer returns `null` when the owner account has been deleted.
- The `Role` validation rule rejects arrays and other non-string input.
- Invitation URLs include the required route parameter.
- Configured table names and foreign keys are used everywhere, including migration rollback.
- Custom team models that use `HasMembers` without extending the package `Team` model are fully supported.

## Upgrading from 3.x

1. **Add the new unique constraints.** Create a migration in your application that adds these indexes, after removing any duplicate rows. Use your configured table names and team foreign key.

    | Table               | Unique columns                                         |
    |---------------------|--------------------------------------------------------|
    | `abilities`         | `team_id`, `permission_id`, `entity_type`, `entity_id` |
    | `entity_ability`    | `ability_id`, `entity_type`, `entity_id`               |
    | `entity_permission` | `permission_id`, `entity_type`, `entity_id`            |
    | `group_user`        | `group_id`, `user_id`                                  |

2. **Remove the published install stubs.** Delete the `InviteController` copied by `teams:install`. Keep your own `TeamPolicy` and register it with `Gate::policy()`.

3. **Check the invitation route.** If you changed `teams.invitations.routes.middleware`, add `auth` to it. Links sent before the upgrade have no expiry and stay valid until accepted.

4. **Update your code:**
    - Listeners for `TeamCreating` read `$event->team` instead of `$event->owner`.
    - Named arguments `action_entity:`, `action_entity_id:`, and `target_entity:` become `actionEntity:`, `actionEntityId:`, and `targetEntity:`.
    - Code that catches `RuntimeException` for a missing role or invitation should catch `ModelNotFoundException`.
    - Calls such as `updateGroup('support', name: 'Support')` keep the group's permissions. Pass `[]` to clear them.

5. **Delete roles and groups with `deleteOrFail()`** when you delete them directly instead of through `deleteRole()` and `deleteGroup()`, so their permissions are removed in the same transaction.
