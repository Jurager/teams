---
title: API Reference
weight: 140
---

# API Reference

## Team (`HasMembers`)

- Members: `addUser`, `updateUser`, `deleteUser`, `hasUser`, `hasUserWithEmail`
- Roles: `hasRole`, `getRole`, `addRole`, `updateRole`, `deleteRole`
- Groups: `hasGroup`, `getGroup`, `addGroup`, `updateGroup`, `deleteGroup`
- Invitations: `inviteUser`, `inviteAccept`, `invitations`
- Permissions: `userHasPermission`, `getPermissionIds`
- Misc: `owner`, `users`, `allUsers`, `abilities`, `roles`, `groups`, `purge`

## User (`HasTeams`)

- Team relations: `ownedTeams`, `teams`, `allTeams`, `belongsToTeam`, `ownsTeam`
- Roles/permissions: `teamRole`, `hasTeamRole`, `teamPermissions`, `hasTeamPermission`
- Abilities: `abilities`, `teamAbilities`, `hasTeamAbility`, `allowTeamAbility`, `forbidTeamAbility`, `deleteTeamAbility`
- Groups: `groups`

## Middleware

- `role:<roles>,<team_id>[,require]`
- `permission:<permissions>,<team_id>[,require]`
- `ability:<permission>,<modelClass>,<entityIdParam>`
