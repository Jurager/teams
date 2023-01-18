<?php

namespace Jurager\Teams;

use Illuminate\Support\Collection;
use Jurager\Teams\Contracts\AddsTeamMembers;
use Jurager\Teams\Contracts\CreatesTeams;
use Jurager\Teams\Contracts\DeletesTeams;
use Jurager\Teams\Contracts\DeletesUsers;
use Jurager\Teams\Contracts\InvitesTeamMembers;
use Jurager\Teams\Contracts\RemovesTeamMembers;
use Jurager\Teams\Contracts\UpdatesTeamNames;

class Teams
{

	/**
	 * The permissions that exist within the application.
	 *
	 * @var Collection
	 */
	public static Collection $permissions;

	/**
	 * The user model that should be used by Teams.
	 *
	 * @var string
	 */
	public static $userModel = 'App\\Models\\User';

	/**
	 * The ability model that should be used by Teams.
	 *
	 * @var string
	 */
	public static $abilityModel = \Jurager\Teams\Models\Ability::class;

    /**
     * The capability model that should be used by Teams.
     *
     * @var string
     */
    public static $capabilityModel = \Jurager\Teams\Models\Capability::class;

    /**
     * The role model that should be used by Teams.
     *
     * @var string
     */
    public static $roleModel = \Jurager\Teams\Models\Role::class;

    /**
     * The group model that should be used by Teams.
     *
     * @var string
     */
    public static $groupModel = \Jurager\Teams\Models\Group::class;

	/**
	 * The permission model that should be used by Teams.
	 *
	 * @var string
	 */
	public static $permissionModel = \Jurager\Teams\Models\Permission::class;

	/**
	 * The team model that should be used by Teams.
	 *
	 * @var string
	 */
	public static $teamModel = \Jurager\Teams\Models\Team::class;

	/**
	 * The membership model that should be used by Teams.
	 *
	 * @var string
	 */
	public static $membershipModel = \Jurager\Teams\Models\Membership::class;

	/**
	 * The team invitation model that should be used by Teams.
	 *
	 * @var string
	 */
	public static $invitationModel = \Jurager\Teams\Models\Invitation::class;

    /**
     * Determine if any permissions have been registered with Teams.
     *
     * @return bool
     */
    public static function hasPermissions()
    {
        return static::$permissions->count() > 0;
    }

    /**
     * Define the available API token permissions.
     *
     * @param  Collection  $permissions
     * @return static
     */
    public static function permissions(Collection $permissions)
    {
        static::$permissions = $permissions;

        return new static();
    }

    /**
     * Return the permissions in the given list that are actually defined permissions for the application.
     *
     * @param  array  $permissions
     * @return array
     */
    public static function validPermissions(array $permissions)
    {
        return array_values(array_intersect($permissions, static::$permissions));
    }

	/**
	 * Find a user instance by the given ID.
	 *
	 * @param int $id
	 * @return mixed
	 */
	public static function findUserByIdOrFail(int $id)
	{
		return static::newUserModel()->where('id', $id)->firstOrFail();
	}

	/**
	 * Find a user instance by the given email address or fail.
	 *
	 * @param  string  $email
	 * @return mixed
	 */
	public static function findUserByEmailOrFail(string $email)
	{
		return static::newUserModel()->where('email', $email)->firstOrFail();
	}

	/**
	 * Get the name of the user model used by the application.
	 *
	 * @return string
	 */
	public static function userModel()
	{
		return static::$userModel;
	}

	/**
	 * Get a new instance of the user model.
	 *
	 * @return mixed
	 */
	public static function newUserModel()
	{
		$model = static::userModel();

		return new $model;
	}

	/**
	 * Specify the user model that should be used by Teams.
	 *
	 * @param  string  $model
	 * @return static
	 */
	public static function useUserModel(string $model)
	{
		static::$userModel = $model;

		return new static;
	}


	/**
	 * Specify the ability model that should be used by Teams.
	 *
	 * @param  string  $model
	 * @return static
	 */
	public static function useAbilityModel(string $model)
	{
		static::$abilityModel = $model;

		return new static;
	}

    /**
     * Specify the capability model that should be used by Teams.
     *
     * @param  string  $model
     * @return static
     */
    public static function useCapabilityModel(string $model)
    {
        static::$capabilityModel = $model;

        return new static;
    }

	/**
	 * Specify the ability model that should be used by Teams.
	 *
	 * @param  string  $model
	 * @return static
	 */
	public static function usePermissionModel(string $model)
	{
		static::$permissionModel = $model;

		return new static;
	}

	/**
	 * Get the name of the team model used by the application.
	 *
	 * @return string
	 */
	public static function teamModel()
	{
		return static::$teamModel;
	}

	/**
	 * Get the name of the ability model used by the application.
	 *
	 * @return string
	 */
	public static function abilityModel()
	{
		return static::$abilityModel;
	}

	/**
	 * Get the name of the permission model used by the application.
	 *
	 * @return string
	 */
	public static function permissionModel()
	{
		return static::$permissionModel;
	}

	/**
	 * Get a new instance of the team model.
	 *
	 * @return mixed
	 */
	public static function newTeamModel()
	{
		$model = static::teamModel();

		return new $model;
	}

	/**
	 * Specify the team model that should be used by Teams.
	 *
	 * @param  string  $model
	 * @return static
	 */
	public static function useTeamModel(string $model)
    {
		static::$teamModel = $model;

		return new static;
	}

    /**
     * Specify the role model that should be used by Teams.
     *
     * @param  string  $model
     * @return static
     */
    public static function useRoleModel(string $model)
    {
        static::$roleModel = $model;

        return new static;
    }

    /**
     * Specify the group model that should be used by Teams.
     *
     * @param  string  $model
     * @return static
     */
    public static function useGroupModel(string $model)
    {
        static::$groupModel = $model;

        return new static;
    }


    /**
	 * Get the name of the membership model used by the application.
	 *
	 * @return string
	 */
	public static function membershipModel()
	{
		return static::$membershipModel;
	}

	/**
	 * Specify the membership model that should be used by Teams.
	 *
	 * @param  string  $model
	 * @return static
	 */
	public static function useMembershipModel(string $model)
	{
		static::$membershipModel = $model;

		return new static;
	}

	/**
	 * Get the name of the team invitation model used by the application.
	 *
	 * @return string
	 */
	public static function invitationModel()
	{
		return static::$invitationModel;
	}

	/**
	 * Specify the team invitation model that should be used by Teams.
	 *
	 * @param  string  $model
	 * @return static
	 */
	public static function useInvitationModel(string $model)
	{
		static::$invitationModel = $model;

		return new static;
	}

	/**
	 * Register a class / callback that should be used to create teams.
	 *
	 * @param  string  $class
	 * @return void
	 */
	public static function createTeamsUsing(string $class)
	{
		return app()->singleton(CreatesTeams::class, $class);
	}

	/**
	 * Register a class / callback that should be used to update team names.
	 *
	 * @param  string  $class
	 * @return void
	 */
	public static function updateTeamNamesUsing(string $class)
	{
		return app()->singleton(UpdatesTeamNames::class, $class);
	}

	/**
	 * Register a class / callback that should be used to add team members.
	 *
	 * @param  string  $class
	 * @return void
	 */
	public static function addTeamMembersUsing(string $class)
	{
		return app()->singleton(AddsTeamMembers::class, $class);
	}

	/**
	 * Register a class / callback that should be used to add team members.
	 *
	 * @param  string  $class
	 * @return void
	 */
	public static function inviteTeamMembersUsing(string $class)
	{
		return app()->singleton(InvitesTeamMembers::class, $class);
	}

	/**
	 * Register a class / callback that should be used to remove team members.
	 *
	 * @param  string  $class
	 * @return void
	 */
	public static function removeTeamMembersUsing(string $class)
	{
		return app()->singleton(RemovesTeamMembers::class, $class);
	}

	/**
	 * Register a class / callback that should be used to delete teams.
	 *
	 * @param  string  $class
	 * @return void
	 */
	public static function deleteTeamsUsing(string $class)
	{
		return app()->singleton(DeletesTeams::class, $class);
	}

	/**
	 * Register a class / callback that should be used to delete users.
	 *
	 * @param  string  $class
	 * @return void
	 */
	public static function deleteUsersUsing(string $class)
	{
		return app()->singleton(DeletesUsers::class, $class);
	}
}
