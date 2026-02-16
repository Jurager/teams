---
title: Schema
weight: 35
---

# Schema

The package stores team access data in dedicated tables.

## Core Tables

- `teams`
- `team_user`
- `roles`
- `permissions`
- `groups`
- `group_user`
- `abilities`
- `entity_permission`
- `entity_ability`
- `invitations` (when enabled)

## Diagram

![Schema](https://raw.githubusercontent.com/jurager/teams/main/schema.png "Database Schema")

> [!NOTE]
> If you change `teams.tables.*` or `teams.foreign_keys.*`, apply it before publishing/running migrations.
