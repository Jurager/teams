<?php

use App\Models\User;
use Jurager\Teams\Models\Ability;
use Jurager\Teams\Models\Group;
use Jurager\Teams\Models\Invitation;
use Jurager\Teams\Models\Membership;
use Jurager\Teams\Models\Permission;
use Jurager\Teams\Models\Role;
use Jurager\Teams\Models\Team;

return [

    /*
    |--------------------------------------------------------------------------
    | Models
    |--------------------------------------------------------------------------
    |
    | Eloquent models used by the teams package.
    |
    */

    'models' => [
        'user' => User::class,
        'team' => Team::class,
        'membership' => Membership::class,
        'invitation' => Invitation::class,
        'role' => Role::class,
        'permission' => Permission::class,
        'ability' => Ability::class,
        'group' => Group::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    |
    | Table names and foreign keys used by the package.
    |
    */

    'tables' => [
        'teams' => 'teams',
        'team_user' => 'team_user',
        'invitations' => 'invitations',
        'roles' => 'roles',
        'permissions' => 'permissions',
        'abilities' => 'abilities',
        'groups' => 'groups',
        'group_user' => 'group_user',
        'entity_permission' => 'entity_permission',
        // Used only by historical migrations and the migration to recipient abilities.
        'entity_ability' => 'entity_ability',
    ],

    'foreign_keys' => [
        'team_id' => 'team_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Gate
    |--------------------------------------------------------------------------
    |
    | Use Laravel's Gate for team permissions.
    |
    */

    'gate' => true,

    /*
    |--------------------------------------------------------------------------
    | Wildcard Permissions
    |--------------------------------------------------------------------------
    |
    | Permission patterns that grant unrestricted team access.
    |
    */

    'wildcards' => [
        'enabled' => false,

        'nodes' => [
            '*',
            '*.*',
            'all',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Invitations
    |--------------------------------------------------------------------------
    |
    | Team invitations, expiration and route registration.
    |
    */

    'invitations' => [
        'enabled' => true,
        'expire' => 7 * 24 * 60,

        'rate_limit' => [
            'enabled' => false,
            'team' => ['max_attempts' => 10, 'decay_seconds' => 3600],
            'email' => ['max_attempts' => 1, 'decay_seconds' => 60],
        ],

        'routes' => [
            'register' => true,
            'url' => '/invitation/{invitation_id}/accept',
            'prefix' => '/',
            'middleware' => ['web', 'auth'],
        ],
    ],

];
