---
title: Database Schema
weight: 35
---

# Database Schema

## Tables

The package creates the following tables. See the [migration files](https://github.com/jurager/teams/tree/main/database/migrations) for their full definitions.

| Table               | Contents                                    |
|---------------------|---------------------------------------------|
| `teams`             | Teams and their owners                      |
| `team_user`         | Team members and their roles                |
| `invitations`       | Pending invitations                         |
| `roles`             | Team roles                                  |
| `permissions`       | Permission codes of teams and global groups |
| `abilities`         | Record rules, one row per recipient         |
| `groups`            | Team groups and global groups               |
| `group_user`        | Group members                               |
| `entity_permission` | Permissions of roles and groups             |

## Record Rules

An `abilities` row contains the team key, `permission_id`, the target record (`entity_type`, `entity_id`), the recipient (`recipient_type`, `recipient_id`), `forbidden`, and nullable `expires_at`, plus timestamps. The team key defaults to `team_id` and can be configured.

The six identity columns are unique together. Changing the decision or deadline updates that row. `forget` deletes it. There are no shared ability definitions or assignment pivots.

`Ability::entity()` and `Ability::recipient()` are polymorphic relationships. The recipient is a user, role, or group. Both use Laravel's morph map when configured. `team()` and `permission()` are ordinary `BelongsTo` relationships; a team's deletion or a permission's deletion cascades to its rules.

User, role, and group `abilities()` relationships are `MorphMany`. Role and group permissions still use `entity_permission` for team-wide grants.

## Relationships

Solid lines are foreign keys. Dotted lines are polymorphic or application relationships; `records` represents your application models. `users` is supplied by the application. Column names below use the default team key.

```mermaid
erDiagram
    users ||..o{ teams : owns
    teams ||--o{ roles : contains
    teams |o--o{ groups : contains
    teams |o--o{ permissions : defines
    teams ||--o{ team_user : contains
    users ||--o{ team_user : joins
    roles ||--o{ team_user : assigns
    groups ||--o{ group_user : contains
    users ||--o{ group_user : joins
    teams ||--o{ invitations : sends
    roles ||--o{ invitations : assigns
    teams ||--o{ abilities : contains
    permissions ||--o{ abilities : applies
    permissions ||--o{ entity_permission : grants
    roles ||..o{ entity_permission : receives
    groups ||..o{ entity_permission : receives
    users ||..o{ abilities : recipient
    roles ||..o{ abilities : recipient
    groups ||..o{ abilities : recipient
    records ||..o{ abilities : entity
    teams {
        bigint id PK
        bigint user_id "indexed, no foreign key"
        string name
        timestamp created_at "nullable"
        timestamp updated_at "nullable"
    }
    roles {
        bigint id PK
        bigint team_id FK "unique with code"
        string code
        string name "nullable"
        string description "nullable"
    }
    groups {
        bigint id PK
        bigint team_id FK "nullable, unique with code"
        string code
        string name
        timestamp created_at "nullable"
        timestamp updated_at "nullable"
    }
    permissions {
        bigint id PK
        bigint team_id FK "nullable, unique with code"
        string code
        string name "nullable"
    }
    abilities {
        bigint id PK
        bigint team_id FK "identity"
        bigint permission_id FK "identity"
        string entity_type "identity"
        bigint entity_id "identity"
        string recipient_type "identity"
        bigint recipient_id "identity"
        boolean forbidden "default false"
        timestamp expires_at "nullable"
        timestamp created_at "nullable"
        timestamp updated_at "nullable"
    }
    team_user {
        bigint id PK
        bigint team_id FK "unique with user_id"
        bigint user_id FK
        bigint role_id FK
        timestamp created_at "nullable"
        timestamp updated_at "nullable"
    }
    group_user {
        bigint id PK
        bigint group_id FK "unique with user_id"
        bigint user_id FK
        timestamp created_at "nullable"
        timestamp updated_at "nullable"
    }
    entity_permission {
        bigint id PK
        bigint permission_id FK "unique with entity_type and entity_id"
        string entity_type
        bigint entity_id
    }
    invitations {
        bigint id PK
        bigint team_id FK "unique with email"
        bigint role_id FK
        string email
        timestamp created_at "nullable"
        timestamp updated_at "nullable"
    }
```

## Deleting Records

Some records can't be deleted while other records depend on them:

| Deleted       | Result                                                                 |
|---------------|------------------------------------------------------------------------|
| Team          | Rejected while it has members. Use [`purge`](teams.md#deleting-teams). |
| Role          | Rejected while it has members.                                         |
| User          | Rejected while they are a team member.                                 |
| Role or group | Its permissions and ability rules are deleted with it.                 |
