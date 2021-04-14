<?php

namespace App\Providers;

use App\Actions\Teams\AddTeamMember;
use App\Actions\Teams\CreateTeam;
use App\Actions\Teams\DeleteTeam;
use App\Actions\Teams\DeleteUser;
use App\Actions\Teams\InviteTeamMember;
use App\Actions\Teams\RemoveTeamMember;
use App\Actions\Teams\UpdateTeamName;
use Illuminate\Support\ServiceProvider;
use Jurager\Teams\Teams;

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
        $this->configurePermissions();

	    Teams::useMembershipModel('/App/Models/Membership');
	    Teams::useTeamModel('/App/Models/Team');
	    Teams::useUserModel('/App/Models/User');

        Teams::createTeamsUsing(CreateTeam::class);
        Teams::updateTeamNamesUsing(UpdateTeamName::class);
        Teams::addTeamMembersUsing(AddTeamMember::class);
        Teams::inviteTeamMembersUsing(InviteTeamMember::class);
        Teams::removeTeamMembersUsing(RemoveTeamMember::class);
        Teams::deleteTeamsUsing(DeleteTeam::class);
        Teams::deleteUsersUsing(DeleteUser::class);
    }

    /**
     * Configure the roles and permissions that are available within the application.
     *
     * @return void
     */
    protected function configurePermissions()
    {
	    Teams::defaultApiTokenPermissions(['read']);

	    Teams::role('admin', __('Administrator'), ['create', 'read', 'update', 'delete' ])
		    ->description(__('Administrator users can perform any action.'));

	    Teams::role('editor', __('Editor'), [ 'read', 'create', 'update'])
		    ->description(__('Editor users have the ability to read, create, and update.'));
    }
}
