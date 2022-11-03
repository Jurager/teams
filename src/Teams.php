<?php

namespace Jurager\Teams;

use Illuminate\Support\Arr;
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
	 * The default permissions that should be available to new entities.
	 *
	 * @var array
	 */
	public static $defaultPermissions = [];

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
	public static $abilityModel = 'App\\Models\\Ability';

    /**
     * The capability model that should be used by Teams.
     *
     * @var string
     */
    public static $capabilityModel = 'App\\Models\\Capability';

    /**
     * The role model that should be used by Teams.
     *
     * @var string
     */
    public static $roleModel = 'App\\Models\\Role';

    /**
     * The group model that should be used by Teams.
     *
     * @var string
     */
    public static $groupModel = 'App\\Models\\Group';

	/**
	 * The permission model that should be used by Teams.
	 *
	 * @var string
	 */
	public static $permissionModel = 'App\\Models\\Permission';

	/**
	 * The team model that should be used by Teams.
	 *
	 * @var string
	 */
	public static $teamModel = 'App\\Models\\Team';

	/**
	 * The membership model that should be used by Teams.
	 *
	 * @var string
	 */
	public static $membershipModel = 'App\\Models\\Membership';

	/**
	 * The team invitation model that should be used by Teams.
	 *
	 * @var string
	 */
	public static $invitationModel = 'App\\Models\\Invitation';

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
