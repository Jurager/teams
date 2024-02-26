# Jurager/Teams
[![Latest Stable Version](https://poser.pugx.org/jurager/teams/v/stable)](https://packagist.org/packages/jurager/teams)
[![Total Downloads](https://poser.pugx.org/jurager/teams/downloads)](https://packagist.org/packages/jurager/teams)
[![PHP Version Require](http://poser.pugx.org/jurager/teams/require/php)](https://packagist.org/packages/jurager/teams)
[![License](https://poser.pugx.org/jurager/teams/license)](https://packagist.org/packages/jurager/teams)

Laravel package to manage teams and operate with user permissions, abilities, supporting multi-tenant dynamic roles, roles groups, capabilities, and permissions for each team.

Users in teams can be combined into groups, with their own abilities, access rights given to a user group overrides the rights granted to a user in a team.


> Documentation for the package is in the process of being written, for now use this readme 
> 
* [Requirements](#requirements)
* [Installation](#installation)
* [Actions](#actions)
* [Team](#team)
* [Users](#users)
* [Group](#group)
* [Invitations](#invitations)
  * [Actions](#invitation-actions) 
  * [Mail](#invitation-mail)
* [Roles/Permissions](#roles--permissions)
    * [Authorization](#authorization)
* [Abilities](#abilities)
* [Middlewares](#middlewares)
  * [Configuration](#middleware-configuration)
  * [Routes](#middleware-routes)
  * [Usage](#middleware-usage)
* [License](#license)

Requirements
-------------------------------------------
`PHP >= 8.1` and `Laravel >= 8.x`

Installation
-------------------------------------------

```sh
composer require jurager/teams
```

Always **do backups**, next command **may overwrite your actual data.**

```sh
php artisan teams:install
```

Then, add the `HasTeams` trait to your existing `User` model.

```php
<?php 

namespace App\Providers;

use Jurager\Teams\Traits\HasTeams;

class User extends Model {

    use HasTeams;
}
```
To complete the installation process add the `TeamPolicy` to your existing `AuthServiceProvider`

```php
<?php 

namespace App\Providers;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        \Jurager\Teams\Models\Team::class => \App\Policies\TeamPolicy::class,
    ];
}
```

Actions
-------------------------------------------

Actions are ready-made code that allows you to quickly start using the package.

They can be invoked from `app/Actions/Teams` when their corresponding task is performed by the user. You can create or modify these actions as you need.

Team
-------------------------------------------
Team can be accessed via `$user->team` it provides methods for inspecting the team's attributes and relations:

```php
// Access the team's owner...
$team->owner

// Get all the abilities belong to the team.
$team->abilities()

// Get all the team's users, excluding owner
$team->users()

// Get all the team's users, including the owner...
$team->allUsers()

// Get all the team's roles.
$team->roles()

// Add new role to the team
$team->addRole(string $name, array $capabilities)

// Update the role in the team
$team->updateRole(string $name, array $capabilities)

// Deletes the given role from team
$team->deleteRole(string $name)

// Get all groups of the team.
$team->groups()

// Get team group by its name
$team->group(string $name)

// Add new group to the team
$team->addGroup(string $name)

// Delete group from the team
$team->deleteGroup(string $name)

// Get the role from the team by role id 
$team->findRole(int $id)

// Return the user role object from the team
$team->userRole($user)

// Determine if the given user is a team member...
$team->hasUser($user)

// Determine if the team has a member with the given email address...
$team->hasUserWithEmail(array $emailAddress)

// Determine if the given user is a team member with the given permission...
$team->userHasPermission($user, string|array $permission, bool $require = false)

// Determine if the team has a member with the given email address...
$team->invitations()

// Remove the given user from the team.
$team->deleteUser();
```

Users
-------------------------------------------

`Jurager\Teams\Traits\HasTeams` provides methods to inspect a user's teams

```php
// Access the team's that a user belongs to...
$user->teams : Illuminate\Database\Eloquent\Collection

// Access all of a user's owned teams...
$user->ownedTeams : Illuminate\Database\Eloquent\Collection

// Access all the team's (including owned teams) that a user belongs to...
$user->allTeams() : Illuminate\Database\Eloquent\Collection

// Determine if a user owns a given team...
$user->ownsTeam($team) : bool

// Determine if a user belongs to a given team...
$user->belongsToTeam($team) : bool

// Get the role that the user is assigned on the team...
$user->teamRole($team) : \Jurager\Teams\Role

// Determine if the user has the given role on the given team...
$user->hasTeamRole($team, 'admin') : bool

// Access an array of all permissions a user has for a given team...
$user->teamPermissions($team) : array

// Determine if a user has a given team permission...
$user->hasTeamPermission($team, 'server:create') : bool

// Get list of abilities or forbidden abilities for users on certain model
$user->teamAbilities($team, \App\Models\Server $server) : mixed

// Determine if a user has a given ability on certain model...
$user->hasTeamAbility($team, 'server:edit', \App\Models\Server $server) : bool

// Add an ability for user to action on certain model, if permission is not found, will create a new one
$user->allowTeamAbility($team, 'server:edit', \App\Models\Server $server) : bool

// Forbid an ability for user to action on certain model, used in case if global permission or role allowing this action
$user->forbidTeamAbility($team, 'server:edit', \App\Models\Server $server) : bool
```

Group
-------------------------------------------

Users within teams can be organized into groups with their own set of permissions.

> Access rights granted to a group of users takes precedence over rights granted to a user in a team.

> At the moment there is a restriction, a team user can only belong to one group.

Examples of use of this behavior:

 * The user has permission to `server:edit` within the team, but is part of a group that is restricted from `server:edit` for certain entities.

 * The user lacks `server:edit` permission but is in a group permitted to `server:edit` certain entities.

`Jurager\Teams\Traits\HasTeams` provides methods to inspect a user's team groups

```php
// Add new group to the team
$team->addGroup(string $name)

// Delete group from the team
$team->deleteGroup(string $name)

// Get all groups of the team.
$team->groups();

// Get team group by its name
$team->group(string $name);

// Get all group users
$team->group(string $name)->users();

// Attach users or user to a group
$team->group(string $name)->attachUser(Collection|Model $user);

// Detach users or user from group
$team->group(string $name)->detachUser(Collection|Model $user);
```

You can work with permissions within a group using the following methods

```php
// Add an ability for user to action on certain model within team group, if permission is not found, will create a new one
$user->allowTeamAbility( Model $team, string 'server:edit', Model $server, Model $group));

// Forbid an ability for user to action on certain model within team group
$user->forbidTeamAbility(Model $team, string 'server:edit', Model $server, Model $group);

// Delete user ability to action on certain model within team group
$user->deleteTeamAbility(Model $team, string 'server:edit', Model $server, Model $group);
```

> Team groups work together with abilities. This means that you should use ability checking methods to determine if users have specific access rights within groups.

We check if the user has permissions within the team and belongs to the team group. 

Since a user can only belong to one team group at a time, we don't need the `$group` variable

```php
// Determinate if user can perform an action
$user->hasTeamAbility(Model $team, string 'server:edit', Model $server)
```

Middleware `ability` is used to check the user's rights within the team group, during requests to your application.

Refer to the [middlewares](#middlewares) section in the documentation for more information.

Invitations
-------------------------------------------

Many applications prefer to send invitation emails to users who are invited to teams. If the user does not have an account, the invitation email may tell them to create an account and accept the invitation. Or, if the user already has an account, they can choose to accept or ignore the invitation.

#### Invitation Actions
When a user is invited to the team, application's `App\Actions\Teams\InviteTeamMember` action will be invoked with the team that the new user is invited to, email address of the invited user, and the role that should be assigned to the user once they join the team.
#### Invitation Mail

Before using the team invitation feature, you should ensure that your Laravel application is configured to [send emails](https://laravel.com/docs/mail) .

Otherwise, Laravel will be unable to send team invitation emails to your application's users.


 Roles / Permissions
-------------------------------------------

Each team member added to a team may be assigned a given role, and each role is assigned a set of permissions.

Roles and permissions are stored in your application's database. This allows flexibility in the use of roles and permissions, e.g. you can implement role and permission management in your app's administration pages.

For example to create new `Team` and attach to it some `Role` and `Permission`


```php
$team = new Team();

$team->name = 'Example Team';
$team->code = 'example_team';

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

The second argument for `$team->addRole` is array of `capabilities`, they are stored in the database and determine the capabilities of your entire application that will be available for attaching to roles

### Authorization

The application will need to understand that incoming requests initiated by a team member can actually be performed by that user.

The permissions of a user's team can be checked using the `hasTeamPermission` method, available through the trait `Jurager\Teams\Traits\HasTeams`.

> **You usually don't need to check a user's role. You just need to verify that the user has a specific granular permission.** Roles are simply a presentational concept used to group granular permissions. Typically, you will execute calls to this method within your application's [authorization policies](https://laravel.com/docs/authorization#creating-policies)

```php
return $user->hasTeamPermission($server->team, 'server:update');
```

Abilities
-------------------------------------------

Adding abilities to users is simple. You don't need to create a role or an ability beforehand. Just pass the name of the ability, and the package will create it if it's not already existing.

For example, to add the ability to edit an article in team for certain user, we need to pass the entity, at this example - article object, a team object

```php
User::allowTeamAbility(Model $team, string 'edit', Model $article);
```

For example, to check this ability in feature, use:
    
```php
User::hasTeamAbility(Model $team, string 'edit', Model $article);
```

To forbid user from some ability (in case if role abilities is allowing this ability)

```php
User::forbidTeamAbility(string 'edit', Model $article, Model $team);
```

To create abilities without attaching it to user, use the Ability model which is published during install
    
```php
Ability::firstOrCreate([ 'name' => 'edit', 'title' => 'Edit' ]);
```
 

Middlewares
-----------------------------------------

### Middleware Configuration

The middleware is registered automatically as `role`, `permission`, `ability`.

If you want to use your own customized middlewares, change the `middleware.register` in `config/teams.php`, then implement your own middlewares, and register them.

### Middleware Routes

You can use a middleware to filter routes and route groups by permission or role:

```php
Route::group(['prefix' => 'admin', 'middleware' => ['role:admin,#team_id#']], function() {
    Route::get('/', 'CommonController@commonIndex');
    Route::get('/users', ['middleware' => ['permission:views-users,#team_id#'], 'uses' => 'CommonController@commonUsers']);
});
```

Where `#team_id#` is your actual ID of the team in database. 

If you want to change or customize the name of this variable, go to your `config/teams.php` and set the `foreign_keys.team_id` value to follow your database structure.

Note, that middleware logic may be varied on how you pass the `team_id` variable:

You can pass the `team_id` variable as route param:
 
```php
Route::get('/{team_id}/users', ['middleware' => ['permission:views-users'], 'uses' => 'CommonController@commonUsers']);
```

You can pass the `team_id` variable directly as middleware option
    
```php
'middleware' => ['role:admin|root,#team_id#']
```

You can pass the `team_id` variable with each GET/POST/PUT or other type requests.

### Middleware Usage

If you want to use OR operation use the pipe symbol:

```php
'middleware' => ['role:admin|root,{team_id}']
// $user->hasTeamRole($team, ['admin', 'root']);

'middleware' => ['permission:edit-post|edit-user']
// $user->hasTeamPermission($team, ['edit-post', 'edit-user']);
```

If you want to use AND functionality you can do:

```php
'middleware' => ['role:admin|root,{team_id},require']
// $user->hasTeamRole($team, ['admin', 'root'], '{team_id}', true);

'middleware' => ['permission:edit-post|edit-user,{team_id},require']
// $user->hasTeamPermission($team, ['edit-post', 'edit-user'], '{team_id}', true);
```

To check the `ability` to action on certain model item you can use `ability` middleware:
    
```php
'middleware' => ['ability:edit,App\Models\Article,atricle_id']
// $user->hasTeamAbility($team, 'edit', $article);
```

In this case you need to pass `atricle_id` as `request param` or `route param` to allow package identify model object

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).
