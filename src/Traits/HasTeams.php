<?php

namespace Jurager\Teams\Traits;

use Jurager\Teams\Models\Ability;
use Jurager\Teams\Owner;
use Jurager\Teams\Teams;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

trait HasTeams
{
	/**
	 * Determine if the given team is the current team.
	 *
	 * @param  $team
	 * @return bool
	 */
	public function isCurrentTeam($team): bool
	{
		return $team->id === $this->currentTeam->id;
	}

	/**
	 * Get the current team of the user's context.
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
	public function currentTeam(): \Illuminate\Database\Eloquent\Relations\BelongsTo
	{
		return $this->belongsTo(Teams::teamModel(), config('teams.foreign_keys.current_team_id', 'current_team_id'));
	}

	/**
	 * Switch the user's context to the given team.
	 *
	 * @param  $team
	 * @return bool
	 */
	public function switchTeam($team): bool
	{
		if (! $this->belongsToTeam($team)) {
			return false;
		}

		$this->forceFill([ config('teams.foreign_keys.current_team_id', 'current_team_id') => $team->id ])->save();

		$this->setRelation('currentTeam', $team);

		return true;
	}

	/**
	 * Get all of the teams the user owns or belongs to.
	 *
	 * @return \Illuminate\Support\Collection
	 */
	public function allTeams(): \Illuminate\Support\Collection
	{
		return $this->ownedTeams->merge($this->teams)->sortBy('name');
	}

	/**
	 * Get all of the teams the user owns.
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\HasMany
	 */
	public function ownedTeams(): \Illuminate\Database\Eloquent\Relations\HasMany
	{
		return $this->hasMany(Teams::teamModel());
	}

	/**
	 * Get all of the teams the user belongs to.
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
	 */
	public function teams()
	{
		return $this->belongsToMany(Teams::teamModel(), Teams::membershipModel())
			->withPivot('role')
			->withTimestamps()
			->as('membership');
	}

	/**
	 * Determine if the user owns the given team.
	 *
	 * @param $team
	 * @return bool
	 */
	public function ownsTeam($team): bool
	{
		if (is_null($team)) {
			return false;
		}

		return $this->id == $team->{$this->getForeignKey()};
	}

	/**
	 * Determine if the user belongs to the given team.
	 *
	 * @param  $team
	 * @return bool
	 */
	public function belongsToTeam($team): bool
	{
		return $this->teams->contains(function ($t) use ($team) {
				return $t->id === $team->id;
			}) || $this->ownsTeam($team);
	}

	/**
	 * Get the role that the user has on the team.
	 *
	 * @param  $team
	 * @return Owner|\Jurager\Teams\Role|void|null
	 */
	public function teamRole($team)
	{
		if ($this->ownsTeam($team)) {
			return new Owner;
		}

		if (! $this->belongsToTeam($team)) {
			return;
		}

		return Teams::findRole($team->users->where(
			'id', $this->id
		)->first()->membership->role);

	}

	/**
	 * Determine if the user has the given role on the given team.
	 *
	 * @param  $team
	 * @param string|array $role
	 * @param bool $require
	 * @return bool
	 */
	public function hasTeamRole($team, string|array $role, bool $require = false): bool
	{
		if ($this->ownsTeam($team)) {
			return true;
		}

		if (is_array($role)) {
			if (empty($role)) {
				return true;
			}

			foreach ($role as $roleName) {
				$hasRole = $this->hasTeamRole($team, $roleName);

				if ($hasRole && !$require) {
					return true;
				} elseif (!$hasRole && $require) {
					return false;
				}
			}

			// If we've made it this far and $requireAll is FALSE, then NONE of the roles were found.
			// If we've made it this far and $requireAll is TRUE, then ALL of the roles were found.
			// Return the value of $requireAll.
			return $require;
		}

		return $this->belongsToTeam($team) && optional(Teams::findRole($team->users->where( 'id', $this->id )->first()->membership->role))->key === $role;
	}

	/**
	 * Get the user's permissions for the given team.
	 *
	 * @param  $team
	 * @return array
	 */
	public function teamPermissions($team): array
	{
		if ($this->ownsTeam($team)) {
			return ['*'];
		}

		if (! $this->belongsToTeam($team)) {
			return [];
		}

		return $this->teamRole($team)->permissions;
	}

	/**
	 * Determinate if user can perform an action
	 *
	 * @param $team
	 * @param $ability
	 * @param $entity
	 * @param bool $require
	 * @return bool
	 */
	public function hasTeamAbility($team, $ability, $entity, bool $require = false): bool
	{
		// Get an ability
		//
		$ability = Teams::abilityModel()::where(['name' => $ability, 'entity_id' => $entity->id, 'entity_type' => $entity::class, 'team_id' => $team->id])->first();

		if($ability) {

			$permission = Teams::permissionModel()::where([
				'team_id'       => $team->id,
				'ability_id'    => $ability->id,
				'entity_id'     => $this->id,
				'entity_type'   => get_class($this),
				'forbidden'     => 0
			])->first();

			if($permission) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Allow user to perform an ability
	 *
	 * @param $team
	 * @param string|array $ability
	 * @param $entity
	 * @return bool
	 */
	public function allowTeamAbility($team, string|array $ability, $entity): bool
	{

		// Get an ability to perform an action on specific entity object inside team
		//
		$ability = Teams::abilityModel()::where(['name' => $ability, 'entity_id' => $entity->id, 'entity_type' => $entity::class, 'team_id' => $team->id])->first();

		if($ability) {

			// Create a new permission for user entity
			//
			$permission = Teams::permissionModel()::firstOrNew(
				[
					'team_id'     => $team->id,
					'ability_id'  => $ability->id,
					'entity_id'   => $this->id,
					'entity_type' => get_class($this),
					'forbidden'   => 0
				],
				[
					'team_id'     => $team->id,
					'entity_id'   => $this->id,
					'entity_type' => get_class($this)
				]
			);

			if($permission->save()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get all users abilities to specific entity
	 *
	 * @param $team
	 * @param $entity
	 * @return mixed
	 */
	public function teamAbilities($team, $entity)
	{
		return Teams::permissionModel()::where('team_id', $team->id)->with([ 'ability' => function($query) use ($entity) {
			$query->where(['entity_id' => $entity->id, 'entity_type' => $entity::class]);
		}])->get();
	}

	/**
	 * Forbid user to perform an ability
	 *
	 * @param $team
	 * @param string|array $ability
	 * @param $entity
	 * @return bool
	 */
	public function forbidTeamAbility($team, string|array $ability, $entity) {

		// Get an ability to perform an action on specific entity object inside team
		//
		$ability = Teams::abilityModel()::where(['name' => $ability, 'entity_id' => $entity->id, 'entity_type' => $entity::class, 'team_id' => $team->id])->first();

		if($ability) {

			// Create a new permission for user entity
			//
			$permission = Teams::permissionModel()::firstOrNew(
				[
					'team_id'     => $team->id,
					'ability_id'  => $ability->id,
					'entity_id'   => $this->id,
					'entity_type' => get_class($this),
					'forbidden'   => 1
				],
				[
					'team_id'     => $team->id,
					'entity_id'   => $this->id,
					'entity_type' => get_class($this)
				]
			);

			if($permission->save()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine if the user has the given permission on the given team.
	 *
	 * @param  $team
	 * @param string|array $permission
	 * @param bool $require
	 * @return bool
	 */
	public function hasTeamPermission($team, string|array $permission, bool $require = false): bool
	{
		if ($this->ownsTeam($team)) {
			return true;
		}

		if (! $this->belongsToTeam($team)) {
			return false;
		}

		if (is_array($permission)) {
			
			if (empty($permission)) {
				return true;
			}

			foreach ($permission as $permissionName) {

				$hasPermission = $this->hasTeamPermission($team, $permissionName);

				if ($hasPermission && !$require) {
					return true;
				} elseif (!$hasPermission && $require) {
					return false;
				}
			}

			// If we've made it this far and $requireAll is FALSE, then NONE of the perms were found.
			// If we've made it this far and $requireAll is TRUE, then ALL of the perms were found.
			// Return the value of $requireAll.
			return $require;
		}

		$permissions = $this->teamPermissions($team);

		$calculated  = [];
		$abilities 	 = explode('.', $permission);

		for($i=1; $i < count($abilities); $i++) {
			$calculated[] = implode('.', array_slice($abilities, 0, $i)).'.*';
		}


		$calculated[] = $permission;
		

		foreach($calculated as $item) {
			if(in_array($item, $permissions)) {
				return true;
			}
		}

		return false;


		//return in_array($permission, $permissions) ||
		//	in_array('*', $permissions) ||
		//	(Str::endsWith($permission, ':create') && in_array('*:create', $permissions)) ||
		//	(Str::endsWith($permission, ':update') && in_array('*:update', $permissions));
	}
}