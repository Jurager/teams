<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Middleware
    |--------------------------------------------------------------------------
    |
    | This configuration helps to customize the middleware behavior.
    |
    */
    'middleware' => [

        /**
         * Define if the team middleware are registered automatically in the service provider
         */
        'register' => true,

        /**
         * Method to be called in the middleware return case.
         * Available: abort|redirect
         */
        'handling' => 'abort',

        /**
         * Handlers for the unauthorized method in the middlewares.
         * The name of the handler must be the same as the handling.
         */
        'handlers' => [
            /**
             * Aborts the execution with a 403 code and allows you to provide the response text
             */
            'abort' => [
                'code' => 403,
                'message' => 'User does not have any of the necessary access rights.',
            ],

            /**
             * Redirects the user to the given url.
             * If you want to flash a key to the session,
             * you can do it by setting the key and the content of the message
             * If the message content is empty it won't be added to the redirection.
             */
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
    | Models
    |--------------------------------------------------------------------------
    |
    | List of models bound to package entities
    |
    */
    'models' => [
        'user' => App\Models\User::class,
        'team' => Jurager\Teams\Models\Team::class,
        'ability' => Jurager\Teams\Models\Ability::class,
        'capability' => Jurager\Teams\Models\Capability::class,
        'group' => Jurager\Teams\Models\Group::class,
        'invitation' => Jurager\Teams\Models\Invitation::class,
        'membership' => Jurager\Teams\Models\Membership::class,
        'permission' => Jurager\Teams\Models\Permission::class,
        'role' => Jurager\Teams\Models\Role::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    |
    | List of model tables bound to package models
    |
    */
    'tables' => [
        'team_user' => 'team_user',
    ],

    /*
    |--------------------------------------------------------------------------
    | Keys
    |--------------------------------------------------------------------------
    |
    | List of model's keys by package entities
    |
    */
    'foreign_keys' => [
        'team_id' => 'team_id',
    ],
];
