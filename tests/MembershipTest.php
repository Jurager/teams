<?php

namespace Jurager\Teams\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Jurager\Teams\Events\TeamMemberRemoved;
use Jurager\Teams\Models\Membership;
use Jurager\Teams\Support\Facades\Teams;
use Jurager\Teams\Tests\Fixtures\Article;
use RuntimeException;

class MembershipTest extends TestCase
{
    public function test_member_removal_rolls_back_membership_groups_and_rules_together(): void
    {
        config(['teams.request.cache_decisions' => true]);
        $team = $this->team();
        $user = $this->member($team);
        $group = $team->addGroup('editors', ['articles.edit']);
        $group->attachUser($user);
        $article = Article::create(['team_id' => $team->getKey()]);
        $user->allowTeamAbility($team, 'articles.edit', $article);
        $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));
        $removed = 0;
        Event::listen(TeamMemberRemoved::class, function () use (&$removed) {
            $removed++;
        });
        Event::listen('eloquent.deleted: '.Membership::class, fn () => throw new RuntimeException('Deletion failed.'));

        try {
            $team->deleteUser($user);
            $this->fail('Expected the membership deletion listener to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Deletion failed.', $exception->getMessage());
        }

        $this->assertTrue($team->hasUser($user));
        $this->assertTrue($group->users()->whereKey($user->getKey())->exists());
        $this->assertCount(1, $user->teamAbilities($team, $article));
        $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));
        $this->assertSame(0, $removed);
    }

    public function test_pivot_insertion_rejects_a_role_from_another_team(): void
    {
        $team = $this->team();
        $other = $this->team();
        $role = $other->addRole('editor', []);
        $user = $this->user();

        try {
            $team->users()->attach($user, ['role_id' => $role->getKey()]);
            $this->fail('Expected the membership to reject an unrelated role.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('role does not belong', $exception->getMessage());
        }

        $this->assertFalse($team->hasUser($user));
    }

    public function test_pivot_updates_preserve_the_role_when_the_new_role_is_invalid(): void
    {
        $team = $this->team();
        $user = $this->member($team);
        $role = $user->teamRole($team);
        $other = $this->team()->addRole('editor', []);

        try {
            $team->users()->updateExistingPivot($user->getKey(), ['role_id' => $other->getKey()]);
            $this->fail('Expected the membership to reject an unrelated role.');
        } catch (RuntimeException) {
            $this->assertTrue($user->teamRole($team)->is($role));
        }
    }

    public function test_the_owner_cannot_be_inserted_into_the_membership_pivot(): void
    {
        $team = $this->team();
        $role = $team->addRole('editor', []);

        try {
            $team->users()->attach($team->owner, ['role_id' => $role->getKey()]);
            $this->fail('Expected an owner membership to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('owner cannot be a member', $exception->getMessage());
        }

        $this->assertSame(0, $team->users()->count());
    }

    public function test_changing_the_team_does_not_validate_against_the_old_pivot_parent(): void
    {
        $team = $this->team();
        $user = $this->member($team);
        $other = $this->team();
        $membership = $team->users()->whereKey($user->getKey())->firstOrFail()->membership;
        $membership->setAttribute(config('teams.foreign_keys.team_id'), $other->getKey());

        try {
            $membership->save();
            $this->fail('Expected the role to be validated against the new team.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('role does not belong', $exception->getMessage());
        }

        $this->assertTrue($team->hasUser($user));
        $this->assertFalse($other->hasUser($user));
    }

    public function test_validation_uses_the_current_owner_when_the_pivot_parent_is_stale(): void
    {
        $team = $this->team();
        $role = $team->addRole('editor', []);
        $user = $this->user();
        $team->fresh()->update(['user_id' => $user->getKey()]);

        try {
            $team->users()->attach($user, ['role_id' => $role->getKey()]);
            $this->fail('Expected the current team owner to be rejected.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('owner cannot be a member', $exception->getMessage());
        }

        $this->assertSame(0, $team->users()->count());
    }

    public function test_a_membership_exposes_its_team_user_and_role(): void
    {
        $team = $this->team();
        $user = $this->member($team);
        $membership = $user->teams()->firstOrFail()->membership;

        $this->assertInstanceOf(Membership::class, $membership);
        $this->assertTrue($membership->team->is($team));
        $this->assertTrue($membership->user->is($user));
        $this->assertTrue($membership->role->is($user->teamRole($team)));
    }

    public function test_custom_membership_must_be_an_eloquent_pivot(): void
    {
        config(['teams.models.membership' => InvalidMembership::class]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must extend Eloquent Pivot');

        Teams::model('membership');
    }
}

class InvalidMembership extends Model {}
