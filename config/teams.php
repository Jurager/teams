<?php

use Jurager\Teams\Features;

return [

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Some of Team's features are optional. You may disable the features
    | by removing them from this array. You're free to only remove some of
    | these features or you can even remove all of these if you need to.
    |
    */

    'features' => [
        Features::accountInvitation(),
        Features::accountDeletion(),
    ],

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
		 * Define if the teams middleware are registered automatically in the service provider
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
				'message' => 'User does not have any of the necessary access rights.'
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
					'content' => ''
				]
			]
		]
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
		'user'            => \App\Models\User::class,
		'team'            => \App\Models\Team::class,
		'membership'      => \App\Models\Membership::class,
		'invitation'      => \App\Models\Invitation::class,
		'ability'         => \App\Models\Ability::class,
		'permission'      => \App\Models\Permission::class,
	],

	/*
    |--------------------------------------------------------------------------
    | Tables
    |--------------------------------------------------------------------------
    |
    | List of models bound to package models
    |
    */
	'tables' => [
		'users'            => 'users',
		'teams'            => 'teams',
		'team_user'        => 'team_user',
		'invitations'      => 'invitations',
		'permissions'      => 'permissions',
		'abilities'        => 'abilities',
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
		'team_id'         => 'team_id',
		'current_team_id' => 'current_team_id'
	],
];
