---
title: API Reference
weight: 140
---

# API Reference

## Team (`HasMembers` trait)

### Member management

| Method | Signature | Returns |
|--------|-----------|---------|
| `addUser` | `addUser(object $user, string $role_keyword): void` | — |
| `updateUser` | `updateUser(object $user, string $role_keyword): void` | — |
| `deleteUser` | `deleteUser(object $user): void` | — |
| `hasUser` | `hasUser(object $user): bool` | bool |
| `hasUserWithEmail` | `hasUserWithEmail(string $email): bool` | bool |
| `users` | `users(): BelongsToMany` | relation |
| `allUsers` | `allUsers(): Collection` | Collection |
| `owner` | `owner(): BelongsTo` | relation |
| `userRole` | `userRole(object $user): mixed` | Role\|null |

> [!WARNING]
> `addUser()`, `updateUser()`, `deleteUser()` throw `RuntimeException` for invalid input. Wrap in `try/catch` when handling user requests.

### Roles

| Method | Signature | Returns |
|--------|-----------|---------|
| `roles` | `roles(): HasMany` | relation |
| `hasRole` | `hasRole(string\|int\|null $keyword): bool` | bool |
| `getRole` | `getRole(string\|int $keyword): Role\|null` | Role\|null |
| `addRole` | `addRole(string $code, array $permissions, ?string $name, ?string $description): Role` | Role |
| `updateRole` | `updateRole(string\|int $keyword, array $permissions, ?string $name, ?string $description): void` | — |
| `deleteRole` | `deleteRole(string\|int $keyword): void` | — |

### Groups

| Method | Signature | Returns |
|--------|-----------|---------|
| `groups` | `groups(): HasMany` | relation |
| `hasGroup` | `hasGroup(string\|int\|null $keyword): bool` | bool |
| `getGroup` | `getGroup(string\|int $keyword): Group\|null` | Group\|null |
| `addGroup` | `addGroup(string $code, array $permissions, ?string $name): Group` | Group |
| `updateGroup` | `updateGroup(string\|int $keyword, array $permissions, ?string $name): void` | — |
| `deleteGroup` | `deleteGroup(string\|int $keyword): void` | — |

### Invitations

| Method | Signature | Returns |
|--------|-----------|---------|
| `inviteUser` | `inviteUser(string $email, string\|int $keyword): void` | — |
| `inviteAccept` | `inviteAccept(int $invitation_id): void` | — |
| `invitations` | `invitations(): HasMany` | relation |

### Misc

| Method | Signature | Returns |
|--------|-----------|---------|
| `abilities` | `abilities(): HasMany` | relation |
| `userHasPermission` | `userHasPermission(object $user, array $permissions, bool $require): bool` | bool |
| `getPermissionIds` | `getPermissionIds(array $codes): array` | array |
| `purge` | `purge(): void` | — |

---

## User (`HasTeams` trait)

### Team relations

| Method | Signature | Returns |
|--------|-----------|---------|
| `ownedTeams` | `ownedTeams(): HasMany` | relation |
| `teams` | `teams(): BelongsToMany` | relation (pivot: `membership`) |
| `allTeams` | `allTeams(): Collection` | Collection sorted by name |
| `belongsToTeam` | `belongsToTeam(object $team): bool` | bool |
| `ownsTeam` | `ownsTeam(object $team): bool` | bool |

### Roles & permissions

| Method | Signature | Returns |
|--------|-----------|---------|
| `teamRole` | `teamRole(object $team): mixed` | Role\|Owner\|null |
| `hasTeamRole` | `hasTeamRole(object $team, string\|array $roles, bool $require = false): bool` | bool |
| `teamPermissions` | `teamPermissions(object $team, ?string $scope = null): array` | string[] |
| `hasTeamPermission` | `hasTeamPermission(object $team, string\|array $permissions, bool $require = false, ?string $scope = null): bool` | bool |

### Abilities

| Method | Signature | Returns |
|--------|-----------|---------|
| `abilities` | `abilities(): MorphToMany` | relation |
| `teamAbilities` | `teamAbilities(object $team, object $entity): Collection` | Collection |
| `hasTeamAbility` | `hasTeamAbility(object $team, string $ability, object $entity): bool` | bool |
| `allowTeamAbility` | `allowTeamAbility(object $team, string $ability, object $entity, ?object $target = null): void` | — |
| `forbidTeamAbility` | `forbidTeamAbility(object $team, string $ability, object $entity, ?object $target = null): void` | — |
| `deleteTeamAbility` | `deleteTeamAbility(object $team, string $ability, object $entity): void` | — |

### Groups

| Method | Signature | Returns |
|--------|-----------|---------|
| `groups` | `groups(): BelongsToMany` | relation |

---

## Middleware

| Alias | Signature | Description |
|-------|-----------|-------------|
| `role` | `role:<roles>,<team_id>[,require]` | Check user role in team |
| `permission` | `permission:<permissions>,<team_id>[,require]` | Check user permission in team |
| `ability` | `ability:<permission>,<modelClass>,<entityIdParam>` | Check entity-level ability |

> [!NOTE]
> Use pipe (`|`) between values for OR logic. Add `require` as the third argument for AND logic.

---

## Facade / Service

| Method | Signature | Returns |
|--------|-----------|---------|
| `Teams::model` | `model(string $key): string` | FQCN of bound model |
| `Teams::instance` | `instance(string $key): object` | New unsaved model instance |

> [!WARNING]
> Both methods throw `RuntimeException` for unknown keys or missing classes.

---

## Validation

| Rule | Description |
|------|-------------|
| `Jurager\Teams\Rules\Role` | Validates that the value matches a role code in the given team |
