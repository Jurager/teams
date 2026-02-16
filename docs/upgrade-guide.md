---
title: Upgrade Guide
weight: 160
---

# Upgrade Guide

## Recommended Flow

1. Back up database and code.
2. Update package version via Composer.
3. Review `config/teams.php` for new/changed options.
4. Publish new migrations/config if required.
5. Run tests around auth, middleware, team membership, and invitations.

## Compatibility Notes

- Package supports Laravel 8 through 12.
- Validate custom model overrides after upgrade.
