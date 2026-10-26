---
title: Database Schema
weight: 35
---

# Database Schema

- [Tables](#tables)
- [Deleting Records](#deleting-records)

## Tables

| Table               | Contents                                  |
|---------------------|-------------------------------------------|
| `teams`             | Teams and their owners                    |
| `team_user`         | Team members and their roles              |
| `invitations`       | Pending invitations                       |
| `roles`             | Team roles                                |
| `permissions`       | Permission codes of a team                |
| `abilities`         | A permission applied to one record        |
| `groups`            | Team groups and global groups             |
| `group_user`        | Group members                             |
| `entity_permission` | Permissions of roles and groups           |
| `entity_ability`    | Ability rules of users, roles, and groups |

See the [migration files](https://github.com/jurager/teams/tree/main/database/migrations) for the full definitions.

## Deleting Records

| Deleted       | Result                                                                   |
|---------------|--------------------------------------------------------------------------|
| Team          | Rejected while it has members. Use [`purge()`](teams.md#deleting-teams). |
| Role          | Rejected while it has members.                                           |
| User          | Rejected while they are a team member.                                   |
| Role or group | Its permissions and ability rules are removed.                           |
