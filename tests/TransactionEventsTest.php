<?php

namespace Jurager\Teams\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Jurager\Teams\Events\TeamCreated;
use Jurager\Teams\Events\TeamDeleted;
use Jurager\Teams\Events\TeamMemberAdded;
use Jurager\Teams\Events\TeamMemberInvited;
use Jurager\Teams\Events\TeamMemberRemoved;
use Jurager\Teams\Events\TeamMemberUpdated;
use Jurager\Teams\Events\TeamUpdated;
use Jurager\Teams\Models\Team;
use Jurager\Teams\Tests\Fixtures\User;
use PHPUnit\Framework\Attributes\DataProvider;

class TransactionEventsTest extends TestCase
{
    #[DataProvider('completedEvents')]
    public function test_completed_events_wait_for_the_outermost_commit(string $operation, string $event): void
    {
        [$team, $user] = $this->scenario($operation);
        $levels = [];
        Event::listen($event, function () use ($team, &$levels) {
            $levels[] = $team->getConnection()->transactionLevel();
        });
        $connection = $team->getConnection();
        $connection->beginTransaction();

        try {
            $this->mutate($operation, $team, $user);
            $this->assertSame([], $levels);
            Mail::assertNothingSent();
            $connection->commit();
            $this->assertSame([0], $levels);
        } finally {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack(0);
            }
        }
    }

    #[DataProvider('completedEvents')]
    public function test_completed_events_are_discarded_on_outer_rollback(string $operation, string $event): void
    {
        [$team, $user] = $this->scenario($operation);
        $dispatched = 0;
        Event::listen($event, function () use (&$dispatched) {
            $dispatched++;
        });
        $connection = $team->getConnection();
        $connection->beginTransaction();

        try {
            $this->mutate($operation, $team, $user);
            $this->assertSame(0, $dispatched);
        } finally {
            $connection->rollBack(0);
        }

        $this->assertSame(0, $dispatched);
        Mail::assertNothingSent();
    }

    public function test_permission_checks_see_changes_inside_a_transaction_and_after_rollback(): void
    {
        config(['teams.request.cache_decisions' => true]);
        $team = $this->team();
        $user = $this->member($team, ['articles.edit']);
        $role = $user->teamRole($team);
        $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));
        $connection = $team->getConnection();
        $connection->beginTransaction();

        try {
            $team->updateRole($role->id, []);
            $this->assertFalse($user->hasTeamPermission($team, 'articles.edit'));
            $team->updateRole($role->id, ['articles.edit']);
            $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));
            $team->updateRole($role->id, []);
        } finally {
            $connection->rollBack(0);
        }

        $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));
    }

    public function test_after_commit_listeners_see_current_permissions(): void
    {
        config(['teams.request.cache_decisions' => true]);
        $team = $this->team();
        $team->addRole('editor', ['articles.edit']);
        $user = $this->user();
        $this->assertFalse($user->hasTeamPermission($team, 'articles.edit'));
        $allowed = null;
        Event::listen(TeamMemberAdded::class, function () use ($user, $team, &$allowed) {
            $allowed = $user->hasTeamPermission($team, 'articles.edit');
        });
        $team->getConnection()->transaction(fn () => $team->addUser($user, 'editor'));

        $this->assertTrue($allowed);
    }

    public function test_team_update_listeners_see_revoked_owner_permissions_without_a_transaction(): void
    {
        config(['teams.request.cache_decisions' => true]);
        $team = $this->team();
        $owner = $team->owner;
        $this->assertTrue($owner->hasTeamPermission($team, 'articles.edit'));
        $allowed = null;
        Event::listen(TeamUpdated::class, function () use ($owner, $team, &$allowed) {
            $allowed = $owner->hasTeamPermission($team, 'articles.edit');
        });
        $team->update(['user_id' => $this->user()->id]);

        $this->assertFalse($allowed);
    }

    public static function completedEvents(): array
    {
        return [
            'team created' => ['create-team', TeamCreated::class],
            'team updated' => ['update-team', TeamUpdated::class],
            'team deleted' => ['delete-team', TeamDeleted::class],
            'member added' => ['add-member', TeamMemberAdded::class],
            'member updated' => ['update-member', TeamMemberUpdated::class],
            'member removed' => ['remove-member', TeamMemberRemoved::class],
            'member invited' => ['invite-member', TeamMemberInvited::class],
        ];
    }

    private function scenario(string $operation): array
    {
        Mail::fake();
        $team = $this->team();
        $team->addRole('reader', ['articles.view']);
        $team->addRole('editor', ['articles.*']);
        $user = $this->user();

        if (in_array($operation, ['update-member', 'remove-member'], true)) {
            $team->addUser($user, 'reader');
        }

        return [$team, $user];
    }

    private function mutate(string $operation, Team $team, User $user): void
    {
        match ($operation) {
            'create-team' => $this->team(),
            'update-team' => $team->update(['name' => 'Renamed']),
            'delete-team' => $team->delete(),
            'add-member' => $team->addUser($user, 'editor'),
            'update-member' => $team->updateUser($user, 'editor'),
            'remove-member' => $team->deleteUser($user),
            'invite-member' => $team->inviteUser($user->email, 'editor'),
        };
    }
}
