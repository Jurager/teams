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
        'entity_ability' => 'entity_ability',
    ],

    'foreign_keys' => [
        'team_id' => 'team_id',
    ],

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | Middleware registration and unauthorized access handling.
    |
    */

    'middleware' => [
        'register' => true,
        'handling' => 'abort',

        'handlers' => [
            'abort' => [
                'code' => 403,
                'message' => 'User does not have any of the necessary access rights.',
            ],

            'redirect' => [
                'url' => '/home',
                'message' => [
                    'key' => 'error',
                    'content' => '',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Request
    |--------------------------------------------------------------------------
    |
    | Permission decision caching within a single execution context.
    |
    */

    'request' => [
        'cache_decisions' => false,
    ],

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

        'routes' => [
            'register' => true,
            'url' => '/invitation/{invitation_id}/accept',
            'prefix' => '/',
            'middleware' => ['web', 'auth'],
        ],
    ],

];
