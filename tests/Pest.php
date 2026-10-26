<?php

use Illuminate\Database\Eloquent\Model;
use Jurager\Teams\Tests\Fixtures\Article;
use Jurager\Teams\Tests\Fixtures\User;
use Jurager\Teams\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function user(): User
{
    return User::create(['email' => uniqid('user', true).'@example.com']);
}

function team(): Model
{
    return user()->teams()->create(['name' => 'Acme']);
}

function role(Model $team, string $code, array $permissions = []): Model
{
    return $team->roles()->create(['code' => $code])->grant($permissions);
}

/**
 * Add a new user to the team with a role of their own.
 */
function member(Model $team, array $permissions = []): User
{
    $user = user();

    $team->add($user)->as(role($team, 'member-'.$user->getKey(), $permissions));

    return $user;
}

function article(Model $team): Article
{
    return Article::create([config('teams.foreign_keys.team_id') => $team->getKey()]);
}
