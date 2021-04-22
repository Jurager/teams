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
    | Models
    |--------------------------------------------------------------------------
    |
    | List of models bound to package entities
    |
    */
	'models' => [
		'user'            => 'App\\Models\\User',
		'team'            => 'App\\Models\\Team',
		'membership'      => 'App\\Models\\Membership',
		'invitation'      => 'App\\Models\\Invitation',
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
	],

	/*
    |--------------------------------------------------------------------------
    | Keys
    |--------------------------------------------------------------------------
    |
    | List of model's keys by package entities
    |
    */
	'keys' => [
		'team_id'         => 'team_id',
		'current_team_id' => 'current_team_id'
	],
];
