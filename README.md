Introduction
-------------------------------------------

"Teams" is a Laravel package to manage team functionality and operate with user permissions and abilities, supporting multi tenancy, dynamic roles and permissions for each team.

Users within a team can be combined into groups with their own rights and permissions, the access rights given to a user group overrides the rights granted to a user in a team


> Documentation for the package is in the process of being written, for now use this readme 

* [Support](#support)
* [Installation](#installation)
* [Actions](#actions)
* [Users](#users)
* [Team](#team)
* [Member Management](#member-management)
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
-------------------------------------------

Package was tested on Laravel 8.x and 9.x

Installation
-------------------------------------------

```sh
composer require jurager/teams
```

To complete the installation you need to run `artisan:publish` command to add configs and additional files for package to work.

> Running the following commands **may overwrite your actual directories and files**, please consider doing a backup beforehand.

```sh
php artisan teams:install
```

> If you also want to add pre-configured `User` and `Team` models, pass the `--models` option to command above, otherwise you need to extend your own models.

This package is supporting package discovery but, after running `artisan:publish` command, you need to put the `App\Providers\TeamsServiceProvider::class` to app.php config in providers section, this file was publised from stub, and needed for extensibility

Actions
-------------------------------------------

Team creation and deletion and other logic may be customized by modifying the relevant action classes within your `app/Actions/Teams` directory. 

These actions include `CreateTeam`, `UpdateTeamName`, and `DeleteTeam`. Each of these actions is invoked when their corresponding task is performed by the user. You are free to modify these actions as required based on your application's needs.

Users
-------------------------------------------
Package provide `Jurager\Teams\Traits\HasTeams` trait, that  applied to your application's `App\Models\User` model during installation and provides methods to inspect a user's teams


```php
// Access all of the team's (including owned teams) that a user belongs to...
$user->teams : Illuminate\Support\Collection

// Access all of a user's owned teams...
$user->ownedTeams : Illuminate\Database\Eloquent\Collection

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

// Add new group to the team
$team->addGroup(string $name)

// Delete group from the team
$team->deleteGroup(string $name)

// Get the role from the team by role id 
$team->findRole(string $id)

// Return the user role object from the team
$team->userRole($user)

// Determine if the given user is a team member...
$team->hasUser($user)

// Determine if the team has a member with the given email address...
$team->hasUserWithEmail($emailAddress)

// Determine if the given user is a team member with the given permission...
$team->userHasPermission($user, $permission)

// Determine if the team has a member with the given email address...
$team->invitations()

// Remove the given user from the team.
$team->removeUser();
```

Member Management
-------------------------------------------

Only owners can manage team membership, that restriction is defined in the `App\Policies\TeamPolicy`. Naturally, you are free to modify this policy as you see fit.


Like the customization process for other package features, team member addition logic may be customized by modifying the `App\Actions\Teams\AddTeamMember` action class. The class' `add` method is invoked with the currently authenticated user, the `Jurager\Teams\Team` instance, the email address of the user being added to the team, and the role (if applicable) of the user being added to the team.

This action is responsible for validating that the user can actually be added to the team and then adding the user to the team. You are free to customize this action based on the needs of your particular application.

Team **member removal** may be customized by modifying the action `App\Actions\Teams\RemoveTeamMember`.


Invitations
-------------------------------------------

By default, package will simply add any existing application user that you specify to your team. However, many applications choose to send invitation emails to users that are invited to teams. If the user does not have an account, the invitation email can instruct them to create an account and accept the invitation. Or, if the user already has an account, they can accept or ignore the invitation.

Thankfully, package allows you to enable team member invitations for your application with just a few lines of code. To get started, pass the `invitations` option to configuration.

Once you have enabled invitations feature, users that are invited to teams will receive an invitation email with a link to accept the team invitation. Users will not be full members of the team until the invitation is accepted.

#### Invitation Actions

When a user is invited to the team, your application's `App\Actions\Teams\InviteTeamMember` action will be invoked with the currently authenticated user, the team that the new user is invited to, the email address of the invited user, and, optionally, the role that should be assigned to the user once they join the team. You are free to review this action or modify it based on the needs of your own application.

#### Invitation Mail

Before using the team invitation feature, you should ensure that your Laravel application is configured to [send emails](https://laravel.com/docs/mail) . Otherwise, Laravel will be unable to send team invitation emails to your application's users.


 Roles / Permissions
-------------------------------------------

Each team member added to a team may be assigned a given role, and each role is assigned a set of permissions.

Roles and permissions are stored in your application's database. This allows extensive use of roles and permissions, e.g. you can impliment management of roles and permissions in your application administration pages.

You can use `App/Models/Role` and `App/Model/Permission`, published with install command, to create new roles and permissions, get them from database, and work with them as usual models. 

However, you can work with roles and permissions directly from `App\Models\Team`

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

The second argument for `$team->addRole` function is array of `capabilities`, they are stored in the database and determine the capabilities of your entire application that will be available for attaching to roles

### Authorization

Of course, you will need a way to authorize that incoming requests initiated by a team member may actually be performed by that user. A user's team permissions may be inspected using the `hasTeamPermission` method available via the `Jurager\Teams\Traits\HasTeams` trait.

**There is typically not a need to inspect a user's role. You only need to inspect that the user has a given granular permission.** Roles are simply a presentational concept used to group granular permissions. Typically, you will execute calls to this method within your application's [authorization policies](https://laravel.com/docs/authorization) :

```php
return $user->hasTeamPermission($server->team, 'server:update');
```

Abilities
-------------------------------------------

Adding abilities to users is made easy. You do not have to create a role or an ability in advance. Simply pass the name of the ability, and package will create it if it doesn't exist.

Let's give the ability to edit an article in team for certain user, we need to pass the entity, at this example - article object, an team object

```php
User::allowTeamAbility('edit', $article, $team));
```

For example, to check this ability in feature, use:
    
```php
User::hasTeamAbility('edit', $article, $team);
```

To forbid user from some ability (in case if role abilities is allowing this ability)

```php
User::forbidTeamAbility('edit', $article, $team);
```

To create abilities without attaching it to user, use the Ability model which is published during install
    
```php
Ability::firstOrCreate([ 'name' => 'edit', 'title' => 'Edit' ]);
```
 

Middlewares
-----------------------------------------

### Middleware Configuration

The middleware is registered automatically as `role`, `permission`, `ability`.
If you want to change or customize them, go to your `config/teams.php` and set the `middleware.register` value to `false` and add the following to the `routeMiddleware` array in `app/Http/Kernel.php`:

```php
'role'       => \Jurager\Teams\Middleware\Role::class, 
'permission' => \Jurager\Teams\Middleware\Permission::class,
'ability'    => \Jurager\Teams\Middleware\Ability::class,
```

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
