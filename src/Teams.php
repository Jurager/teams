<?php

namespace Jurager\Teams;

class Teams
{
    /**
     * The user model that should be used by Teams.
     */
    public static mixed $userModel;

    /**
     * The ability model that should be used by Teams.
     */
    public static mixed $abilityModel;

    /**
     * The capability model that should be used by Teams.
     */
    public static mixed $capabilityModel;

    /**
     * The role model that should be used by Teams.
     */
    public static mixed $roleModel;

    /**
     * The group model that should be used by Teams.
     */
    public static mixed $groupModel;

    /**
     * The permission model that should be used by Teams.
     */
    public static mixed $permissionModel;

    /**
     * The team model that should be used by Teams.
     */
    public static mixed $teamModel;

    /**
     * The membership model that should be used by Teams.
     */
    public static mixed $membershipModel;

    /**
     * The team invitation model that should be used by Teams.
     */
    public static mixed $invitationModel;

    /**
     * Set passed model that will be used by package
     */
    public static function setModel(string $model, $namespace): void
    {
        self::${$model.ucfirst('model')} = $namespace;
    }

    /**
     * Return model that will be used by package
     */
    public static function getModel(string $model): mixed
    {
        return self::${$model.ucfirst('model')};
    }
}
