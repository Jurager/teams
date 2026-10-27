<?php

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Jurager\Teams\Models\Ability;
use Jurager\Teams\Models\Role;

class SoftAbility extends Ability
{
    use SoftDeletes;
}

it('prunes only abilities without assignments to any recipient', function () {
    $team = team();
    $user = member($team);
    $member = $team->member($user);
    $role = $member->role();
    $group = $team->groups()->create(['code' => 'editors']);
    $article = article($team);
    $member->allow('user')->on($article);
    $role->forbid('role')->on($article);
    $group->allow('group')->on($article);
    $role->allow('unused')->on($article);
    $role->forget('unused')->on($article);
    $unused = $team->abilities()->whereRelation('permission', 'code', 'unused')->firstOrFail();

    expect((new Ability)->prunable()->get()->modelKeys())->toBe([$unused->id]);

    $this->artisan('model:prune', ['--model' => [Ability::class]])->assertSuccessful();

    expect($unused->fresh())->toBeNull()
        ->and($team->abilities()->count())->toBe(3)
        ->and($team->permissions()->count())->toBe(4)
        ->and($member->can('user', $article))->toBeTrue()
        ->and($member->can('role', $article))->toBeFalse();
});

it('supports previewing pruning without deleting abilities', function () {
    $team = team();
    $role = role($team, 'editor');
    $article = article($team);
    $role->allow('articles.edit')->on($article);
    $role->forget('articles.edit')->on($article);

    $this->artisan('model:prune', ['--model' => [Ability::class], '--pretend' => true])->assertSuccessful();

    expect($team->abilities()->count())->toBe(1);
});

it('keeps an ability assigned after pruning candidates were selected', function () {
    $team = team();
    $member = $team->member(member($team));
    $article = article($team);
    $member->allow('articles.edit')->on($article);
    $member->forget('articles.edit')->on($article);
    $assign = true;

    Event::listen('eloquent.retrieved: '.Ability::class, function () use (&$assign, $member, $article) {
        if ($assign) {
            $assign = false;
            $member->allow('articles.edit')->on($article);
        }
    });

    (new Ability)->pruneAll();

    expect($team->abilities()->count())->toBe(1)
        ->and($member->can('articles.edit', $article))->toBeTrue();
});

it('does not use recipient scopes to decide whether an ability is unused', function () {
    $team = team();
    $role = role($team, 'editor');
    $role->allow('articles.edit')->on(article($team));
    Role::addGlobalScope('hidden', fn (Builder $query) => $query->whereRaw('1 = 0'));

    try {
        expect((new Ability)->prunable()->count())->toBe(0)
            ->and($team->abilities()->firstOrFail()->prune())->toBeFalse()
            ->and($team->abilities()->count())->toBe(1);
    } finally {
        Role::clearBootedModels();
    }
});

it('keeps assignments with an unknown recipient type', function () {
    $team = team();
    $role = role($team, 'editor');
    $article = article($team);
    $role->allow('articles.edit')->on($article);
    $ability = $team->abilities()->firstOrFail();
    DB::table(config('teams.tables.entity_ability'))->update(['entity_type' => 'legacy']);

    expect((new Ability)->prunable()->count())->toBe(0)
        ->and($ability->prune())->toBeFalse();
});

it('runs Eloquent deletion events and rolls back pruning when a listener fails', function () {
    $team = team();
    $role = role($team, 'editor');
    $article = article($team);
    $role->allow('articles.edit')->on($article);
    $role->forget('articles.edit')->on($article);
    $ability = $team->abilities()->firstOrFail();
    Event::listen('eloquent.deleting: '.Ability::class, fn () => throw new RuntimeException('Deletion failed.'));

    expect(fn () => $ability->prune())->toThrow(RuntimeException::class, 'Deletion failed.')
        ->and($ability->fresh())->not->toBeNull();
});

it('honors native pruning of soft-deleted ability subclasses', function () {
    Schema::table(config('teams.tables.abilities'), fn (Blueprint $table) => $table->softDeletes());
    config(['teams.models.ability' => SoftAbility::class]);
    $team = team();
    $role = role($team, 'editor');
    $article = article($team);
    $role->allow('articles.edit')->on($article);
    $role->forget('articles.edit')->on($article);
    $ability = $team->abilities()->firstOrFail();
    $ability->delete();

    expect(SoftAbility::withTrashed()->count())->toBe(1);
    (new SoftAbility)->pruneAll();
    expect(SoftAbility::withTrashed()->count())->toBe(0);
});
