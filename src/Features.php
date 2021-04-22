<?php

namespace Jurager\Teams;

use Illuminate\Support\Arr;

class Features
{
    /**
     * Determine if the given feature is enabled.
     *
     * @param  string  $feature
     * @return bool
     */
    public static function enabled(string $feature)
    {
        return in_array($feature, config('teams.features', []));
    }

    /**
     * Determine if the feature is enabled and has a given option enabled.
     *
     * @param  string  $feature
     * @param  string  $option
     * @return bool
     */
    public static function optionEnabled(string $feature, string $option)
    {
        return static::enabled($feature) && config("teams-options.{$feature}.{$option}") === true;
    }

	/**
	 * Determine if the application is using any account deletion features.
	 *
	 * @return bool
	 */
	public static function hasAccountInvitationFeatures()
	{
		return static::enabled(static::accountInvitation());
	}

	/**
	 * Determine if the application is using any account deletion features.
	 *
	 * @return bool
	 */
	public static function hasAccountDeletionFeatures()
	{
		return static::enabled(static::accountDeletion());
	}

	/**
	 * Enable the account invitation feature.
	 *
	 * @return string
	 */
	public static function accountInvitation()
	{
		return 'account-invitation';
	}

	/**
	 * Enable the account deletion feature.
	 *
	 * @return string
	 */
	public static function accountDeletion()
	{
		return 'account-deletion';
	}
}
