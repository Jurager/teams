<?php

namespace App\Providers;

use Jurager\Teams\Teams;
use App\Actions\Teams\AddTeamMember;
use App\Actions\Teams\CreateTeam;
use App\Actions\Teams\DeleteTeam;
use App\Actions\Teams\DeleteUser;
use App\Actions\Teams\InviteTeamMember;
use App\Actions\Teams\RemoveTeamMember;
use App\Actions\Teams\UpdateTeamName;
use Illuminate\Support\ServiceProvider;

class TeamsServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
	    Teams::useMembershipModel(config('teams.models.membership', '/App/Models/Membership'));
	    Teams::useTeamModel(config('teams.models.team', '/App/Models/Team'));
	    Teams::useUserModel(config('teams.models.user', '/App/Models/User'));

	    Teams::useAbilityModel(config('teams.models.ability', '/App/Models/Ability'));
	    Teams::usePermissionModel(config('teams.models.permission', '/App/Models/Permission'));

        Teams::createTeamsUsing(CreateTeam::class);
        Teams::updateTeamNamesUsing(UpdateTeamName::class);
        Teams::addTeamMembersUsing(AddTeamMember::class);
        Teams::inviteTeamMembersUsing(InviteTeamMember::class);
        Teams::removeTeamMembersUsing(RemoveTeamMember::class);
        Teams::deleteTeamsUsing(DeleteTeam::class);
        Teams::deleteUsersUsing(DeleteUser::class);
    }
}