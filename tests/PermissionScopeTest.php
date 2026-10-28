<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Jurager\Teams\Models\Group;
use Jurager\Teams\Models\Team;
use Jurager\Teams\Tests\Fixtures\ScopedGroup;
use Jurager\Teams\Tests\Fixtures\ScopedRole;
use Jurager\Teams\Tests\Fixtures\ScopedUser;
use Jurager\Teams\Tests\Fixtures\User;

enum QueriedPermission: string
{
    case Edit = 'articles.edit';
}

it('queries the same users as team-wide member checks', function (string $permission, bool $wildcards) {
    config(['teams.wildcards.enabled' => $wildcards]);
    $team = team();
    member($team, ['articles.view']);
    member($team, ['articles.*']);
    member($team, ['*']);
    $groupMember = member($team);
    $team->groups()->create(['code' => 'editors'])->grant('articles.edit')->add($groupMember);
    $team->groups()->create(['code' => 'reviewers'])->grant('articles.edit')->add($groupMember);
    role($team, 'unassigned', ['articles.*']);

    $stranger = user();
    Group::create(['code' => 'support'])->grant('articles.*')->add($stranger);
    $team->groups()->create(['code' => 'raw-assignment'])->grant('articles.*')->members()->attach($stranger);
    member(team(), ['articles.*']);

    $expected = User::all()->filter(fn (User $user): bool => $team->member($user)->can($permission))->values()->modelKeys();
    DB::enableQueryLog();

    expect(User::permission($permission, $team))->toBeInstanceOf(Builder::class)
        ->and(User::permission($permission, $team)->orderBy('id')->get()->modelKeys())->toBe($expected)
        ->and(DB::getQueryLog())->toHaveCount(1);
})->with(['articles.view', 'articles.edit', 'articles.comments.edit', 'missing'])->with([false, true]);

it('preserves filters, pagination and relationship constraints', function () {
    $team = team();
    $first = member($team, ['articles.edit']);
    $second = member($team, ['articles.edit']);
    $query = User::whereKey([$first->id, $second->id])->permission(QueriedPermission::Edit, $team);
    $page = $query->orderBy('id')->paginate(1);

    expect($page->total())->toBe(2)
        ->and($page->first()->is($first))->toBeTrue()
        ->and(User::whereKey($first)->permission('articles.edit', $team)->where('email', 'missing@example.com')->count())->toBe(0)
        ->and($team->members()->permission('articles.edit', $team)->get()->modelKeys())->toEqualCanonicalizing([$first->id, $second->id]);
});

it('uses only the role assigned in the requested team', function () {
    $team = team();
    $other = team();
    $user = member($team);
    $other->add($user)->as(role($other, 'editor', ['articles.*']));
    role($team, 'unused', ['articles.*']);

    expect(User::whereKey($user)->permission('articles.edit', $team)->exists())->toBeFalse()
        ->and(User::whereKey($user)->permission('articles.edit', $other)->exists())->toBeTrue()
        ->and(User::permission('articles.edit', new Team(['user_id' => $user->id]))->exists())->toBeFalse();
});

it('preserves constraints on custom permission and group relationships', function () {
    config(['teams.models.role' => ScopedRole::class, 'teams.models.group' => ScopedGroup::class]);
    $team = team();
    $user = ScopedUser::create(['email' => 'scoped@example.com']);
    $team->add($user)->as(role($team, 'reader', ['visible.role', 'hidden.role']));
    $team->groups()->create(['code' => 'visible'])->grant('visible.group', 'hidden.group')->add($user);
    $team->groups()->create(['code' => 'hidden'])->grant('hidden.group')->add($user);

    foreach (['visible.role', 'hidden.role', 'visible.group', 'hidden.group'] as $permission) {
        expect(ScopedUser::whereKey($user)->permission($permission, $team)->exists())
            ->toBe($team->member($user)->can($permission));
    }
});
