# Jurager/Teams
[![Latest Stable Version](https://poser.pugx.org/jurager/teams/v/stable)](https://packagist.org/packages/jurager/teams)
[![Total Downloads](https://poser.pugx.org/jurager/teams/downloads)](https://packagist.org/packages/jurager/teams)
[![PHP Version Require](https://poser.pugx.org/jurager/teams/require/php)](https://packagist.org/packages/jurager/teams)
[![License](https://poser.pugx.org/jurager/teams/license)](https://packagist.org/packages/jurager/teams)

A Laravel package for managing teams and user permissions, supporting multi-tenant dynamic roles, role groups, and team-specific permissions.

Users can be organized into groups within teams, each with custom permissions and abilities. Permissions assigned to a user group override individual user permissions within a team.

Additionally, users can be added to a global group to grant them access across all teams with the group's permissions. This feature is ideal for scenarios like providing support across multiple teams without the need to add users to each team individually.

Features:

- Assign roles to team members, each with its own set of permissions
- Wildcard permission matching — e.g. `posts.*` covers all post-related actions
- Organize members into groups that can grant or restrict access beyond their role
- Control access to individual records — allow or forbid actions on specific model instances
- Global groups for users who need access across all teams (e.g. support staff)
- Protect routes with built-in `role`, `permission`, and `ability` middleware
- Invite users to a team by email via signed links
- Events fired on team and membership changes for easy extensibility

## Requirements

- PHP >= 8.1
- Laravel 8+

## Installation

To install, configure and learn how to use please go to the [Documentation](https://docs.gerassimov.me/teams/).

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).
