<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Jurager\Teams\Models\Group;
use Jurager\Teams\Tests\Fixtures\Article;
use Jurager\Teams\Tests\Fixtures\User;

class LimitedMembershipUser extends User
{
    protected $table = 'users';

    public function memberships(): BelongsToMany
    {
        return parent::memberships()->where('name', 'Visible');
    }
}

it('queries owned and joined teams once without duplicates', function () {
    $user = user();
    $owned = $user->teams()->create(['name' => 'Owned']);
    $joined = team();
    $joined->add($user)->as(role($joined, 'reader'));
    team();
    DB::enableQueryLog();

    expect($user->accessible())->toBeInstanceOf(Builder::class)
        ->and($user->accessible()->orderBy('id')->get()->modelKeys())->toBe([$owned->id, $joined->id])
        ->and(DB::getQueryLog())->toHaveCount(1);
});

it('uses the users database for accessible teams and record authorization', function () {
    team();
    config(['database.connections.secondary' => config('database.connections.testing')]);
    $schema = Schema::getFacadeRoot();
    Schema::swap(DB::connection('secondary')->getSchemaBuilder());

    try {
        $this->createTables();
    } finally {
        Schema::swap($schema);
    }

    $other = User::on('secondary')->create(['email' => 'other@example.com']);
    $user = User::on('secondary')->create(['email' => 'member@example.com']);
    $owned = $user->teams()->create(['name' => 'Owned on secondary']);
    $joined = $other->teams()->create(['name' => 'Joined on secondary']);
    $role = $joined->roles()->create(['code' => 'reader']);
    $role->permissions()->attach($joined->permissions()->create(['code' => 'articles.view']));
    $joined->add($user)->as($role);
    $article = Article::on('secondary')->create(['team_id' => $owned->id]);
    $record = Article::on('secondary')->create(['team_id' => $joined->id]);
    $joined->member($user)->allow('articles.edit')->on($record);

    expect($joined->member($user)->can('articles.edit', $record))->toBeTrue()
        ->and($joined->member($user)->load([$record])->can('articles.edit', $record))->toBeTrue()
        ->and(DB::table(config('teams.tables.abilities'))->count())->toBe(0)
        ->and(DB::table(config('teams.tables.permissions'))->count())->toBe(0);

    expect($user->accessible()->getConnection()->getName())->toBe('secondary')
        ->and($user->accessible()->orderBy('id')->pluck('name')->all())->toBe(['Owned on secondary', 'Joined on secondary'])
        ->and($user->can('articles.edit', $article))->toBeTrue()
        ->and(User::on('secondary')->permission('articles.view', $joined)->count())->toBe(2);
});

it('keeps subsequent filters around the entire team selection', function () {
    $user = user();
    $user->teams()->create(['name' => 'Hidden']);
    $owned = $user->teams()->create(['name' => 'Visible']);
    $joined = team();
    $joined->update(['name' => 'Visible']);
    $joined->add($user)->as(role($joined, 'reader'));
    $page = $user->accessible()->where('name', 'Visible')->orderBy('id')->paginate(1);

    expect($page->total())->toBe(2)
        ->and($page->first()->is($owned))->toBeTrue()
        ->and($user->accessible()->whereKey($joined)->count())->toBe(1);
});

it('does not treat global groups or invitations as membership', function () {
    Mail::fake();
    $team = team();
    $user = user();
    $team->invite($user->email)->as(role($team, 'reader'));
    Group::create(['code' => 'support'])->grant('articles.*')->add($user);

    expect($user->accessible()->get())->toBeEmpty()
        ->and((new User)->accessible()->get())->toBeEmpty();
});

it('preserves constraints on the users membership relationship', function () {
    $user = LimitedMembershipUser::create(['email' => 'limited@example.com']);
    $owned = $user->teams()->create(['name' => 'Owned']);
    $visible = team();
    $visible->update(['name' => 'Visible']);
    $hidden = team();

    foreach ([$visible, $hidden] as $team) {
        $team->add($user)->as(role($team, 'reader'));
    }

    expect($user->accessible()->orderBy('id')->get()->modelKeys())->toBe([$owned->id, $visible->id]);
});

it('refreshes ownership relationships and team queries after a transfer', function () {
    $team = team();
    $previous = $team->owner->load('teams');
    $next = user()->load('teams');
    $team->transfer($next);

    expect($previous->teams)->toBeEmpty()
        ->and($previous->accessible()->get())->toBeEmpty()
        ->and($next->teams->first()->is($team))->toBeTrue()
        ->and($next->accessible()->first()->is($team))->toBeTrue();
});
