<?php

namespace Jurager\Teams\Tests;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Jurager\Teams\Support\Facades\Teams;
use RuntimeException;

class CustomMembershipTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('teams.models.membership', RestrictedMembership::class);
    }

    protected function setUp(): void
    {
        parent::setUp();
        RestrictedMembership::observe(RestrictedMembershipObserver::class);
    }

    public function test_custom_membership_validation_runs_on_creation_and_role_changes(): void
    {
        $team = $this->team();
        $team->addRole('reader', []);
        $team->addRole('blocked', []);
        $user = $this->user();

        try {
            $team->addUser($user, 'blocked');
            $this->fail('Expected custom membership validation to reject the role.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Blocked role.', $exception->getMessage());
            $this->assertFalse($team->hasUser($user));
        }

        $team->addUser($user, 'reader');
        $this->assertInstanceOf(RestrictedMembership::class, $user->teams()->firstOrFail()->membership);

        try {
            $team->updateUser($user, 'blocked');
            $this->fail('Expected custom membership validation to reject the role change.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Blocked role.', $exception->getMessage());
            $this->assertSame('reader', $user->teamRole($team)->code);
        }
    }
}

class RestrictedMembership extends Pivot
{
    protected $table = 'team_user';

    public $incrementing = true;
}

class RestrictedMembershipObserver
{
    public function saving(Pivot $membership): void
    {
        if ($membership->belongsTo(Teams::model('role'), 'role_id')->where('code', 'blocked')->exists()) {
            throw new RuntimeException('Blocked role.');
        }
    }
}
