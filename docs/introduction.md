---
title: Introduction
weight: 10
---

# Introduction

Jurager/Teams is a Laravel package for team-based access control with dynamic roles, groups, permissions, and entity-level abilities.

## Concepts

- **Team**: isolated workspace with members, roles, and groups.
- **Role**: baseline permission set for a user inside a team.
- **Group**: optional permission layer for subsets of users.
- **Ability**: allow/forbid rule on a concrete model instance.

## Access Layers

1. Role permissions.
2. Group permissions.
3. Global group permissions.
4. Entity abilities (allow/forbid).

> [!NOTE]
> Group and user entity rules can override role-level access for specific records.

## Requirements

- PHP >= 8.1
- Laravel 8+
- Composer
