<?php

namespace Jurager\Teams;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

class Teams
{

	/**
	 * The user model that should be used by Teams.
	 *
	 * @var object
	 */
	public static $userModel;

	/**
	 * The ability model that should be used by Teams.
	 *
	 * @var object
	 */
	public static $abilityModel;

    /**
     * The capability model that should be used by Teams.
     *
     * @var object
     */
    public static $capabilityModel;

    /**
     * The role model that should be used by Teams.
     *
     * @var object
     */
    public static $roleModel;

    /**
     * The group model that should be used by Teams.
     *
     * @var object
     */
    public static $groupModel;

	/**
	 * The permission model that should be used by Teams.
	 *
	 * @var object
	 */
	public static $permissionModel;

	/**
	 * The team model that should be used by Teams.
	 *
	 * @var object
	 */
	public static $teamModel;

	/**
	 * The membership model that should be used by Teams.
	 *
	 * @var object
	 */
	public static $membershipModel;

	/**
	 * The team invitation model that should be used by Teams.
	 *
	 * @var object
	 */
	public static $invitationModel;

    /**
     * Set passed model that will be used by package
     *
     * @param string $model
     * @param $namespace
     * @return void
     */
    static function setModel(string $model, $namespace): void
    {
        self::${$model.ucfirst('model')} = $namespace;
    }

    /**
     * Return model that will be used by package
     *
     * @param string $model
     * @return void
     */
    static function getModel(string $model)
    {
        return self::${$model.ucfirst('model')};
    }
}