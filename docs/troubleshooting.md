---
title: Troubleshooting
weight: 150
---

# Troubleshooting

## Middleware Always Denies

- Verify `team_id` is available in route or request.
- Check `teams.middleware.handling` and handler config.
- Confirm user has expected role/group permissions in the selected team.

## Permission Seems Missing

- Ensure the user belongs to the same team.
- Check `scope` used in `hasTeamPermission()` (`role`/`group`/all).
- If using wildcards, confirm `teams.wildcards.enabled` and configured nodes.

## Invitations Not Working

- Ensure `teams.invitations.enabled` is true.
- Ensure invitation routes are registered.
- Verify your mail transport and app URL settings.

## Broken Schema After Install

- Re-check custom `tables` and `foreign_keys` values.
- Apply config changes before publishing/running migrations.
