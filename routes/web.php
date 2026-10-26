<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Jurager\Teams\Http\Controllers\InviteController;

Route::get(Config::get('teams.invitations.routes.url'), [InviteController::class, 'inviteAccept'])
    ->middleware(['signed'])
    ->name('teams.invitations.accept');
