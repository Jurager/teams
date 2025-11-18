# Jurager/Teams
[![Latest Stable Version](https://poser.pugx.org/jurager/teams/v/stable)](https://packagist.org/packages/jurager/teams)
[![Total Downloads](https://poser.pugx.org/jurager/teams/downloads)](https://packagist.org/packages/jurager/teams)
[![PHP Version Require](https://poser.pugx.org/jurager/teams/require/php)](https://packagist.org/packages/jurager/teams)
[![License](https://poser.pugx.org/jurager/teams/license)](https://packagist.org/packages/jurager/teams)

A Laravel package for managing teams and user permissions, supporting multi-tenant dynamic roles, role groups, and team-specific permissions.

Users can be organized into groups within teams, each with custom permissions and abilities. Permissions assigned to a user group override individual user permissions within a team.

Additionally, users can be added to a global group to grant them access across all teams with the group’s permissions. This feature is ideal for scenarios like providing support across multiple teams without the need to add users to each team individually.

> [!NOTE]
> The documentation for this package is currently being written. For now, please refer to this readme for information on the functionality and usage of the package.

- [Requirements](#requirements)
- [Schema](#schema)
- [Installation](#installation)
- [Teams](#teams)
- [Users](#users)
- [Roles & Permissions](#roles--permissions)
    - [Authorization](#authorization)
- [Abilities](#abilities)
  - [Adding an Ability](#adding-an-ability)
  - [Checking an Ability](#checking-an-ability)
    - [Access Levels](#access-levels)
    - [How Access Logic Works](#how-access-logic-works)
  - [Forbidding an Ability](#forbidding-an-ability)
- [Groups](#groups)
  - [Usage Scope](#usage-scope)
  - [Groups Managing](#groups-managing)
  - [Groups Abilities](#groups-permissions)
- [Middlewares](#middlewares)
  - [Middleware Configuration](#middleware-configuration)
  - [Middleware Routes](#middleware-routes)
  - [Middleware Usage](#middleware-usage)
- [License](#license)

Requirements
-------------------------------------------
`PHP >= 8.1` and `Laravel 8.x or higher`

Schema
-------------------------------------------
![Schema](schema.png "Title")

Installation
-------------------------------------------

```sh
composer require jurager/teams
```

Always **do backups**, next command **may overwrite your actual data.**

```sh
php artisan teams:install
```
Run the migrations

```sh
php artisan migrate
```

> [!NOTE]
> If you wish to use custom foreign keys and table names, make changes to config `config/teams.php`  before running migrations.


Then, add the `HasTeams` trait to your existing `User` model.

```php
<?php 

namespace App\Providers;

use Jurager\Teams\Traits\HasTeams;

class User extends Model {

    use HasTeams;
}
```

Teams
-------------------------------------------
A team can be accessed via `$user->team`, providing methods for inspecting the team's attributes and relations:

```php
// Access the team's owner...
$team->owner

// Get all the team's users, excluding owner
$team->users()

// Get all the team's users, including the owner...
$team->allUsers()

// Determine if the given user is a team member...
// Accepts user object
$team->hasUser($user)

// Adds a user to the team with a specified role by role ID or code
$team->addUser($user, $role_keyword)

// Update the role of a specific user within the team
$team->updateUser($user, $role_keyword)

// Remove the given user from the team.
$team->deleteUser($user);

// Create invitation for given email and send message with invitation link
$team->inviteUser($email, $role_keyword)

// Accepts the invitation by adding the user identified by the given email to the team, then deletes the invitation.
$team->inviteAccept($invitation_id)

// Get all the abilities belong to the team.
$team->abilities()

// Get all the team's roles.
$team->roles()

// Return the user role object from the team
$team->userRole($user)

// Check if the team has a specific role by ID or code or any roles at all if null
$team->hasRole($role_keyword)

// Get the role from the team by role id or code 
$team->getRole($role_keyword)

// Add new role to the team
$team->addRole($code, $permissions, $name, $description)

// Update the role in the team
// Name and description is nullable when no changes needed
$team->updateRole($role_keyword, $permissions, $name, $description)

// Deletes the given role from team
$team->deleteRole($role_keyword)

// Get all groups of the team.
$team->groups()

// Get team group by its id or code
$team->getGroup($group_keyword)

// Add new group to the team
$team->addGroup($code, $permissions, $name)

// Update the group in the team
$team->updateGroup($group_keyword, $permissions, $name)

// Delete group from the team
$team->deleteGroup($group_keyword)

// Determine if the team has a member with the given email address...
$team->hasUserWithEmail($email)

// Determine if the given user is a team member with the given permission...
// $require = true  (all permissions in the array are required)
// $require = false  (only one or more permission in the array are required)
$team->userHasPermission($user, $permissions, $require)

// Returns all team invitations
$team->invitations()
```

These methods allow you to efficiently manage and interact with teams, including roles, users, permissions, and invitations.

> [!NOTE]
> By default, the package uses the built-in model. If you want to use your own model, or specify a custom table name in the database, use the settings in the configuration file - `teams.models.team`, `teams.tables.teams`, `teams.foreign_keys.team_id`

Users
-------------------------------------------

The `Jurager\Teams\Traits\HasTeams` trait provides methods to inspect a user's teams:

```php
// Access the teams that a user belongs to...
$user->teams

// Access all of a user's owned teams...
$user->ownedTeams

// Access all the team's (including owned teams) that a user belongs to...
$user->allTeams()

// Determine if a user owns a given team...
$user->ownsTeam($team)

// Determine if a user belongs to a given team...
$user->belongsToTeam($team)

// Get the role that the user is assigned on the team...
$user->teamRole($team)

// Determine if the user has the given role (or roles if array passed) on the given team...
// $require = true  (all roles in the array are required)
// $require = false  (only one or more role in the array are required)
$user->hasTeamRole($team, 'admin', $require)

// Access an array of all permissions a user has for a given team...
// Scope identifies which model to take permissions from, by default if null - getting all permissions ( ex. 'role', 'group')
$user->teamPermissions($team, $scope)

// Determine if a user has a given team permission or permissions if array passed...
// $require = true  (all permissions in the array are required)
// $require = false  (only one or more permission in the array are required)
// $scope - identifies in which model to check permissions, by default in all ( ex. 'role', 'group')
$user->hasTeamPermission($team, 'server:create', $require, $scope)

// Get list of abilities or forbidden abilities for users on certain model
$user->teamAbilities($team, $server)

// Determine if a user has a given ability on certain model...
$user->hasTeamAbility($team, 'server:edit', $server)

// Add an ability for user to action on certain model, if not found, will create a new one
$user->allowTeamAbility($team, 'server:edit', $server)

// Forbid an ability for user to action on certain model, used in case if global ability or role allowing this action
$user->forbidTeamAbility($team, 'server:edit', $server)
```

These methods enable you to efficiently manage and inspect a user's teams, roles, permissions, and abilities within your application.

 Roles & Permissions
-------------------------------------------

> Roles and permissions offer a flexible approach to managing access control within your application. Each team member can be assigned a role, with each role tied to a specific set of permissions. These roles and permissions are stored in your application's database, allowing for dynamic and easy management of access and enables features like role and permission management through your application's admin interface.

**Example**: Creating a New Team with Roles and Permissions

```php
$team = new Team();

$team->name = 'Example Team';
$team->user_id = $user->id;

if ($team->save()) {

    $team->addRole('admin', [
        'employees.*',
        'sections.*',
        'articles.*',
        'tags.*',
        'comments.*',
        'team.edit',
        'stores.*',
        'plan.edit',
    ]);
    
    $team->addRole('user', [
        'employees.view',
        'articles.view',
        'articles.add',
        'sections.view',
        'sections.add',
        'comments.add',
        'tags.view',
        'stores.add',
        'stores.delete',
        'tags.add',
    ]);
}
```

In the above example, we create a new team and assign it two roles: "admin" and "user". Each role is associated with a set of permissions that define what actions users with that role can perform within the application.

The second argument for `$team->addRole()` is an array of permissions, which determine the actions that users with the corresponding role can perform in the application.

### Authorization

To ensure that incoming requests initiated by a team member can be executed by that user, the application needs to verify the permissions of the user's team. This verification can be done using the `hasTeamPermission` method, which is available through the `Jurager\Teams\Traits\HasTeams` trait.

> [!NOTE]  
> In most cases, checking a user's role is often unnecessary. Instead, prioritize verifying specific granular permissions, as roles mainly serve to group these permissions for organizational clarity. Typically, you’ll use this approach within your application's [authorization policies](https://laravel.com/docs/authorization#creating-policies).

**Example**: Check if a user within a team has permission to update a server

```php
return $user->hasTeamPermission($team, 'server:update');
```

### Wildcard Permissions

You can choose to enable wildcard permissions in the config. Enabling wildcards will allow you to specify permission node(s) that grants a user all access if they have that permission attached to them.
```php
/*
|--------------------------------------------------------------------------
| Wildcard Permissions
|--------------------------------------------------------------------------
| Configure wildcard permission nodes, allowing you to specify super admin
| permission node(s) that allows a user to perform all actions on a team.
*/
'wildcards' => [
    'enabled' => false,
    'nodes' => [
        '*',
        '*.*',
        'all'
    ]
]
```

In the example configuration above, users with the permission nodes of "\*" or "\*.\*" or "all" would be allowed to perform all actions on their team.

> [!NOTE]
> This configuration does not grant global team access. It only allows you to grant all permissions to a user or role in the team 

Abilities
-------------------------------------------

> Abilities - enables users to perform specific actions on application entities or models. For example, you can grant a user within a team the ability to edit posts.

### Adding an Ability

Adding abilities to users is easy — just pass the ability name, and it’ll be created automatically if it doesn’t exist.

To grant a user the ability to edit an article within a team, simply provide the relevant entities, such as the article and team objects

If `$target_entity` is null, target for ability defaults to user:

```php
$user->allowTeamAbility($team, $action, $action_entity, $target_entity)
```

### Checking an Ability

To verify if a user has a specific ability within the context of a team, based on various permission levels (role, group, user, and global), you can use the following method:
    
```php
User::hasTeamAbility($team, 'edit_post', $post);
```

This method checks if the user can perform the specified ability (e.g., 'edit_post') on the given entity (e.g., a post) within the context of the specified team. It takes into account the user's role, groups, global permissions, and any entity-specific access rules.
##### Access Levels

Permissions are governed by different access levels, which are compared to determine whether an action is allowed or forbidden. There are two key indicators:

* **allowed**: The highest permission level granted to the user.
* **forbidden**: The highest restriction applied to the user.

If the **allowed** value is greater than or equal to the **forbidden** value, the action is permitted.

| Level             | Value | Description                                                                                  |
|-------------------|-------|----------------------------------------------------------------------------------------------|
| `DEFAULT`         | 0     | Base level with no explicit permissions or restrictions.                                     |
| `FORBIDDEN`       | 1     | Base level denying access.                                                                   |
| `ROLE_ALLOWED`    | 2     | Permission granted based on the user's role in the team.                                     |
| `ROLE_FORBIDDEN`  | 3     | Restriction applied based on the user's role in the team.                                    |
| `GROUP_ALLOWED`   | 4     | Permission granted based on the user's group within the team.                                |
| `GROUP_FORBIDDEN` | 5     | Restriction applied based on the user's group within the team.                               |
| `USER_ALLOWED`    | 5     | Permission granted specifically for the user.                                                |
| `USER_FORBIDDEN`  | 6     | Restriction applied specifically to the user for this entity.                                |
| `GLOBAL_ALLOWED`  | 6     | Global permissions applicable to the user regardless of the team context.                    |


##### How Access Logic Works

1.  **Ownership Check**: If the user is the owner of the entity (via isOwner), access is immediately granted.
2.  **Team-Level Permission Check**: The method checks:
  *   Role-based permissions using **hasTeamPermission.**
  *   Group-based permissions using **hasGroupPermission.**
  *   Global permissions using **hasGlobalGroupPermissions.**
3.  **Entity-Specific Rules**: If the entity has specific rules (abilities), permissions and restrictions are evaluated for:
  *   The user's role within the team.
  *   The user's groups within the team.
  *   The specific user assigned to this entity.
4.  **Final Decision**: If the final allowed level is greater than or equal to the forbidden level, access is granted.

### Forbidding an Ability

To prevent a user from having a specific ability (even if their role allows it), use the following method.

If `$target_entity` is null, target for ability defaults to user:
```php
User::forbidTeamAbility($team, $action, $action_entity,$target_entity)
```

Groups
-------------------------------------------

> Users within teams can be organized into groups, each with its own set of abilities and permissions. Groups work together with abilities and permissions, so you should use ability and permission checking methods to determine if users have specific access rights within groups.


> [!NOTE]  
> Access rights granted to a group of users take precedence over rights granted to a user within role in a team.

### Usage Scope

* User **can** `server:edit` in the team, but is part of a group **restricted** from `server:edit` for specific entities.

* User **can't** `server:edit` in the team, but is in a group **permitted** to `server:edit` specific entities.

### Groups Managing

The `Jurager\Teams\Traits\HasTeams` trait provides methods to inspect a user's team groups:

```php
// Add new group to the team
// If $name is null, str::studly of $code will be used
$team->addGroup($code, $permissions, $name)

// Update the group in the team, if permissions is empty array all exiting permissions will be detached
// If $name is null, str::studly of $code will be used
$team->updateGroup($group_keyword, $permissions, $name)

// Delete group from the team
$team->deleteGroup($group_keyword)

// Get all groups of the team.
$team->groups();

// Check if the team has a specific group by ID or code or any groups at all if null passed
$team->hasGroup($group_keyword)

// Get team group by its code
$group = $team->getGroup($group_keyword);

// Get all group users
$group->users();

// Attach users or user to a group
$group->attachUser($user);

// Detach users or user from group
$group->detachUser($user);
```

Middlewares
-----------------------------------------

### Middleware Configuration

The middleware provided by this package is automatically registered as `role`, `permission`, and `ability`.

However, if you wish to use your own customized middlewares, you can modify the `middleware.register` in the `config/teams.php`.

### Middleware Routes

You can use middleware to filter routes and route groups based on permissions or roles. 

> [!NOTE]  
> Consider, that `team_id` represents the actual ID of the team in the database.

If you need to customize the name of this variable, adjust the `foreign_keys.team_id` value in your `config/teams.php` file to match your database structure.


```php
Route::group(['prefix' => 'admin', 'middleware' => ['role:admin,team_id']], function() {
    Route::get('/users', 'UserController@usersIndex');
    Route::get('/user/edit', ['middleware' => ['permission:edit-users,team_id'], 'uses' => 'UserController@userEdit']);
});
```

> [!NOTE]  
> Middleware logic may vary based on how you pass the `{team_id}` variable.

* You can pass the `{team_id}` variable as a route parameter:

```php
Route::get('/{team_id}/users', ['middleware' => ['permission:views-users'], 'uses' => 'CommonController@commonUsers']);
```

* You can pass the `{team_id}` variable directly as a middleware option:

```php
'middleware' => ['role:admin|root,{team_id}']
```

* You can send the `{team_id}` variable with each request type (GET/POST/PUT, etc.).

### Middleware Usage

For **OR** operations, use the pipe symbol:

```php
'middleware' => ['role:admin|root,{team_id}']
// $user->hasTeamRole($team, ['admin', 'root']);

'middleware' => ['permission:edit-post|edit-user,{team_id}']
// $user->hasTeamPermission($team, ['edit-post', 'edit-user']);
```

For **AND** functionality:

```php
'middleware' => ['role:admin|root,{team_id},require']
// $user->hasTeamRole($team, ['admin', 'root'], require: true);

'middleware' => ['permission:edit-post|edit-user,{team_id},require']
// $user->hasTeamPermission($team, ['edit-post', 'edit-user'], require: true);
```

To check the ability to perform a specific action on a specific model item, use the **ability** middleware:
    
```php
'middleware' => ['ability:edit,App\Models\Article,{article_id}']
// $user->hasTeamAbility($team, 'edit', $article);
```

In this case, pass `{article_id}` as a request parameter or route parameter to allow the package to identify the model object.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).