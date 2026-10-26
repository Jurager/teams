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
| `abilities`         | Permissions applied to single records       |
| `groups`            | Team groups and global groups               |
| `group_user`        | Group members                               |
| `entity_permission` | Permissions of roles and groups             |
| `entity_ability`    | Ability rules of users, roles, and groups   |

## Deleting Records

Some records can't be deleted while other records depend on them:

| Deleted       | Result                                                                 |
|---------------|------------------------------------------------------------------------|
| Team          | Rejected while it has members. Use [`purge`](teams.md#deleting-teams). |
| Role          | Rejected while it has members.                                         |
| User          | Rejected while they are a team member.                                 |
| Role or group | Its permissions and ability rules are deleted with it.                 |
