<?php

use App\Http\Controllers\InviteController;
use Illuminate\Support\Facades\Route;

Route::middleware(config('teams.invitations.routes.middleware'))
    ->get(config('teams.invitations.routes.url'), [InviteController::class, 'inviteAccept'])
    ->name('teams.invitations.accept');