<?php

namespace Jurager\Teams\Tests;

use Illuminate\Database\Eloquent\Model;
use Jurager\Teams\Models\Team;
use Jurager\Teams\Tests\Fixtures\Article;
use Jurager\Teams\Tests\Fixtures\User;
use RuntimeException;

class StandaloneModelsTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('teams.models.role', PlainRole::class);
        $app['config']->set('teams.models.group', PlainGroup::class);
        $app['config']->set('teams.models.permission', PlainPermission::class);
        $app['config']->set('teams.models.ability', PlainAbility::class);
    }

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([PlainRole::class, PlainGroup::class] as $class) {
            $class::resolveRelationUsing('team', fn (Model $model) => $model->belongsTo(Team::class, 'team_id'));
            $class::resolveRelationUsing('permissions', fn (Model $model) => $model->morphToMany(PlainPermission::class, 'entity', 'entity_permission', 'entity_id', 'permission_id'));
            $class::resolveRelationUsing('abilities', fn (Model $model) => $model->morphToMany(PlainAbility::class, 'entity', 'entity_ability', 'entity_id', 'ability_id')->withPivot('forbidden')->withTimestamps());
        }

        PlainRole::resolveRelationUsing('users', fn (Model $model) => $model->belongsToMany(User::class, 'team_user', 'role_id', 'user_id'));
        PlainGroup::resolveRelationUsing('users', fn (Model $model) => $model->belongsToMany(User::class, 'group_user', 'group_id', 'user_id'));
        PlainPermission::resolveRelationUsing('team', fn (Model $model) => $model->belongsTo(Team::class, 'team_id'));
        PlainPermission::resolveRelationUsing('roles', fn (Model $model) => $model->morphedByMany(PlainRole::class, 'entity', 'entity_permission', 'permission_id', 'entity_id'));
        PlainPermission::resolveRelationUsing('groups', fn (Model $model) => $model->morphedByMany(PlainGroup::class, 'entity', 'entity_permission', 'permission_id', 'entity_id'));
        PlainAbility::resolveRelationUsing('team', fn (Model $model) => $model->belongsTo(Team::class, 'team_id'));
        PlainAbility::resolveRelationUsing('entity', fn (Model $model) => $model->morphTo('entity'));

        foreach (['users' => User::class, 'roles' => PlainRole::class, 'groups' => PlainGroup::class] as $relation => $class) {
            PlainAbility::resolveRelationUsing($relation, fn (Model $model) => $model->morphedByMany($class, 'entity', 'entity_ability', 'ability_id', 'entity_id')->withPivot('forbidden')->withTimestamps());
        }
    }

    public function test_record_models_need_only_the_documented_eloquent_relationships(): void
    {
        $team = $this->team();
        $role = $team->addRole('reader', ['articles.view']);
        $group = $team->addGroup('editors', ['articles.edit']);
        $user = $this->user();
        $team->addUser($user, 'reader');
        $group->users()->attach($user);
        $article = Article::create(['team_id' => $team->getKey()]);

        $this->assertInstanceOf(PlainRole::class, $role);
        $this->assertInstanceOf(PlainGroup::class, $group);
        $this->assertTrue($user->hasTeamPermission($team, ['articles.view', 'articles.edit'], true));
        $user->forbidTeamAbility($team, 'articles.edit', $article, targetEntity: $group);
        $this->assertFalse($user->hasTeamAbility($team, 'articles.edit', $article));
        $user->allowTeamAbility($team, 'articles.edit', $article);
        $this->assertTrue($user->hasTeamAbility($team, 'articles.edit', $article));
        $this->assertInstanceOf(PlainAbility::class, $user->teamAbilities($team, $article)->first());
        $this->assertTrue($user->teamAbilities($team, $article)->first()->entity->is($article));
        $user->deleteTeamAbility($team, 'articles.edit', $article);
        $this->assertFalse($user->hasTeamAbility($team, 'articles.edit', $article));

        try {
            $team->deleteRole('reader');
            $this->fail('Expected the occupied role to be preserved.');
        } catch (RuntimeException) {
            $this->assertModelExists($role);
        }

        $team->deleteUser($user);
        $team->deleteRole('reader');
        $team->deleteGroup('editors');
        $this->assertModelMissing($role);
        $this->assertModelMissing($group);
        $this->assertEmpty($user->abilities()->get());
    }
}

class PlainRole extends Model
{
    protected $table = 'roles';

    protected $guarded = [];

    public $timestamps = false;
}

class PlainGroup extends Model
{
    protected $table = 'groups';

    protected $guarded = [];
}

class PlainPermission extends Model
{
    protected $table = 'permissions';

    protected $guarded = [];

    public $timestamps = false;
}

class PlainAbility extends Model
{
    protected $table = 'abilities';

    protected $guarded = [];
}
