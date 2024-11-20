<?php

use App\Http\Controllers\InviteController;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;

Route::middleware(Config::get('teams.invitations.routes.middleware'))
    ->get(Config::get('teams.invitations.routes.url'), [InviteController::class, 'inviteAccept'])
    ->name('teams.invitations.accept');