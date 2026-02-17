---
title: Introduction
weight: 10
---

# Introduction

Jurager/Teams is a Laravel package for team-based access control with dynamic roles, groups, permissions, and entity-level abilities.

## Concepts

- **Team** — isolated workspace with members, roles, and groups.
- **Role** — baseline permission set assigned to a user inside a team (stored in `team_user.role_id`).
- **Permission** — dot-notation code (e.g., `articles.edit`) attached to a role or group.
- **Group** — optional permission layer for subsets of users within a team (or globally).
- **Ability** — allow/forbid rule on a **specific model instance**, evaluated on top of role/group permissions.

## Access Layers

Permissions are resolved in the following order:

1. **Role permissions** — applied to all team members with the role.
2. **Group permissions** — overrides role access for members of the group.
3. **Global group permissions** — cross-team permissions for members of global groups.
4. **Entity abilities** — instance-level allow/forbid rules (role → group → user precedence).

> [!NOTE]
> Group and user entity rules can override role-level access for specific records. Only `hasTeamAbility()` evaluates all four layers. `hasTeamPermission()` only evaluates layers 1–2.

## Requirements

- PHP >= 8.1
- Laravel 8+
- Composer

> [!NOTE]
> The package is tested against Laravel 8 through 12. Older Laravel versions are not supported.
