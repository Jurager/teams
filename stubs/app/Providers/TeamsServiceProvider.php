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

	    Teams::useMembershipModel(config('teams.models.membership', '/App/Models/Membership'));
	    Teams::useTeamModel(config('teams.models.team', '/App/Models/Team'));
	    Teams::useUserModel(config('teams.models.user', '/App/Models/user'));

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

	    Teams::role('admin', __('Administrator'), ['read', 'create', 'update', 'delete' ])
		    ->description(__('Administrator users can perform any action.'));

	    Teams::role('manager', __('Manager'), [ 'read', 'create', 'update'])
		    ->description(__('Manager users have the ability to read, create, and update.'));

	    Teams::role('user', __('Manager'), [ 'read'])
		    ->description(__('Regular users have the ability only  to read.'));
    }
}
