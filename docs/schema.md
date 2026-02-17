---
title: Schema
weight: 35
---

# Schema

The package stores team access data in dedicated tables.

## Core Tables

| Table               | Purpose                                              |
|---------------------|------------------------------------------------------|
| `teams`             | Team records with `user_id` (owner reference)        |
| `team_user`         | Membership pivot with `role_id`                      |
| `roles`             | Team roles (code, name, description)                 |
| `permissions`       | Permission codes scoped to a team                    |
| `groups`            | User groups (`team_id = null` for global groups)     |
| `group_user`        | Group membership pivot                               |
| `abilities`         | Entity-level ability definitions                     |
| `entity_permission` | Polymorphic junction: roles/groups ↔ permissions     |
| `entity_ability`    | Polymorphic junction: roles/groups/users ↔ abilities (with `forbidden` flag) |
| `invitations`       | Pending team invitations (when `invitations.enabled = true`) |

## Diagram

![Schema](https://raw.githubusercontent.com/jurager/teams/main/schema.png "Database Schema")

> [!NOTE]
> If you change `teams.tables.*` or `teams.foreign_keys.*` in config, apply those changes **before** publishing or running migrations. Post-migration changes require manual schema alterations.

> [!WARNING]
> The `invitations` table is always created by the migration regardless of the `invitations.enabled` config value. The config flag only controls route registration and the `inviteUser()` / `inviteAccept()` behavior at the application level.
