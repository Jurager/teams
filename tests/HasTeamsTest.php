<?php

namespace Jurager\Teams\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Jurager\Teams\Models\Group;
use Jurager\Teams\Models\Role;
use Jurager\Teams\Tests\Fixtures\Article;
use Jurager\Teams\Tests\Fixtures\User;
use PHPUnit\Framework\Attributes\DataProvider;

class HasTeamsTest extends TestCase
{
    public function test_a_personal_grant_resolves_before_loading_lower_priority_rules(): void
    {
        $team = $this->team();
        $user = $this->member($team, ['articles.*']);
        $role = $user->teamRole($team);
        $group = $team->addGroup('editors', ['articles.edit']);
        $group->attachUser($user);
        $article = Article::create(['team_id' => $team->getKey()]);
        $user->forbidTeamAbility($team, 'articles.edit', $article, targetEntity: $role);
        $user->forbidTeamAbility($team, 'articles.edit', $article, targetEntity: $group);
        $user->allowTeamAbility($team, 'articles.edit', $article);
        DB::enableQueryLog();

        $this->assertTrue($user->hasTeamAbility($team, 'articles.edit', $article));
        $this->assertCount(4, DB::getQueryLog());
    }

    public function test_a_personal_rule_requires_team_membership(): void
    {
        $team = $this->team();
        $user = $this->user();
        $article = Article::create(['team_id' => $team->getKey()]);
        $user->allowTeamAbility($team, 'articles.edit', $article);

        $this->assertFalse($user->hasTeamAbility($team, 'articles.edit', $article));
        $team->addRole('reader', []);
        $team->addUser($user, 'reader');
        $this->assertTrue($user->hasTeamAbility($team, 'articles.edit', $article));
    }

    public function test_a_literal_star_requires_enabled_wildcard_nodes(): void
    {
        $team = $this->team();
        $user = $this->member($team, ['*']);
        $article = Article::create(['team_id' => $team->getKey()]);
        $this->assertFalse($user->hasTeamPermission($team, 'articles.edit'));
        $this->assertFalse($user->hasTeamAbility($team, 'articles.edit', $article));

        config(['teams.wildcards.enabled' => true]);
        $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));
        $this->assertTrue($user->hasTeamAbility($team, 'articles.edit', $article));
    }

    public function test_permission_queries_preserve_custom_relationship_constraints(): void
    {
        config([
            'teams.models.user' => ScopedUser::class,
            'teams.models.role' => ScopedRole::class,
            'teams.models.group' => ScopedGroup::class,
        ]);
        $team = $this->team();
        $user = ScopedUser::create(['email' => 'scoped@example.com']);
        $team->addRole('editor', ['visible.role', 'hidden.role']);
        $team->addUser($user, 'editor');
        $team->addGroup('visible', ['visible.group', 'hidden.group.permission'])->attachUser($user);
        $team->addGroup('hidden', ['hidden.group'])->attachUser($user);

        $this->assertEqualsCanonicalizing(['visible.role', 'visible.group'], $user->teamPermissions($team));
        $this->assertFalse($user->hasTeamPermission($team, 'hidden.role'));
        $this->assertFalse($user->hasTeamPermission($team, 'hidden.group'));
        $this->assertFalse($user->hasTeamPermission($team, 'hidden.group.permission'));
        $this->assertSame(['visible.role'], $user->teamPermissions($team, 'role'));
        $this->assertSame(['visible.group'], $user->teamPermissions($team, 'group'));
    }

    #[DataProvider('permissionChecks')]
    public function test_permission_checks_preserve_scopes_and_any_or_all_semantics(string|array $permissions, bool $require, ?string $scope, bool $expected): void
    {
        $team = $this->team();
        $user = $this->member($team, ['articles.*']);
        $team->addGroup('reporters', ['reports.view'])->attachUser($user);

        $this->assertSame($expected, $user->hasTeamPermission($team, $permissions, $require, $scope));
    }

    public static function permissionChecks(): array
    {
        return [
            'wildcard' => ['articles.edit', false, null, true],
            'nested wildcard' => ['articles.comments.edit', false, null, true],
            'different prefix' => ['articles2.edit', false, null, false],
            'parent is not a descendant' => ['articles', false, null, false],
            'any matching permission' => [['articles.edit', 'missing'], false, null, true],
            'all matching permissions' => [['articles.edit', 'reports.view'], true, null, true],
            'one missing permission' => [['articles.edit', 'missing'], true, null, false],
            'role scope' => ['reports.view', false, 'role', false],
            'group scope' => ['reports.view', false, 'group', true],
            'group scope excludes role' => ['articles.edit', false, 'group', false],
            'unknown scope' => ['articles.edit', false, 'missing', false],
            'empty any' => [[], false, null, false],
            'empty all' => [[], true, null, false],
        ];
    }

    #[DataProvider('abilityChecks')]
    public function test_record_rules_preserve_their_precedence(array $rolePermissions, array $groupPermissions, array $rules, bool $global, bool $expected): void
    {
        $team = $this->team();
        $user = $this->member($team, $rolePermissions);
        $group = $team->addGroup('editors', $groupPermissions);
        $group->attachUser($user);
        $article = Article::create(['team_id' => $team->getKey()]);
        $targets = ['role' => $user->teamRole($team), 'group' => $group, 'user' => $user];

        foreach ($rules as [$target, $forbidden, $permission]) {
            $method = $forbidden ? 'forbidTeamAbility' : 'allowTeamAbility';
            $user->{$method}($team, $permission, $article, targetEntity: $targets[$target]);
        }

        if ($global) {
            $group = Group::create(['code' => 'support', 'name' => 'Support']);
            $group->permissions()->sync($team->getPermissionIds(['articles.*']));
            $group->attachUser($user);
        }

        $this->assertSame($expected, $user->hasTeamAbility($team, 'articles.edit', $article));
        $this->assertSame($expected, $user->hasTeamAbility($team, 'articles.edit', Article::class, $article->getKey()));
    }

    public static function abilityChecks(): array
    {
        return [
            'no grant' => [[], [], [], false, false],
            'role permission' => [['articles.*'], [], [], false, true],
            'role rule' => [[], [], [['role', false, 'articles.edit']], false, true],
            'role deny overrides role permission' => [['articles.*'], [], [['role', true, 'articles.edit']], false, false],
            'group permission overrides role deny' => [[], ['articles.*'], [['role', true, 'articles.edit']], false, true],
            'group rule overrides role deny' => [[], [], [['role', true, 'articles.edit'], ['group', false, 'articles.edit']], false, true],
            'group deny overrides role grant' => [[], [], [['role', false, 'articles.edit'], ['group', true, 'articles.edit']], false, false],
            'user grant overrides group deny' => [[], [], [['group', true, 'articles.edit'], ['user', false, 'articles.edit']], false, true],
            'user deny overrides wildcard user grant' => [[], [], [['user', false, 'articles.*'], ['user', true, 'articles.edit']], false, false],
            'global grant overrides user deny' => [[], [], [['user', true, 'articles.edit']], true, true],
        ];
    }

    public function test_configured_wildcard_nodes_apply_to_permissions_and_record_rules(): void
    {
        $team = $this->team();
        $user = $this->member($team, ['all']);
        $article = Article::create(['team_id' => $team->getKey()]);
        $this->assertFalse($user->hasTeamPermission($team, 'articles.edit'));
        $this->assertFalse($user->hasTeamAbility($team, 'articles.edit', $article));

        config(['teams.wildcards.enabled' => true, 'teams.wildcards.nodes' => ['all']]);
        $this->assertTrue($user->hasTeamPermission($team, 'articles.edit'));
        $this->assertTrue($user->hasTeamAbility($team, 'articles.edit', $article));
    }

    public function test_entity_ownership_works_for_instances_and_classes_without_team_membership(): void
    {
        Schema::table('articles', fn (Blueprint $table) => $table->unsignedBigInteger('owner_id'));
        $team = $this->team();
        $owner = $this->user();
        $outsider = $this->user();
        $article = OwnedArticle::create(['team_id' => $team->getKey(), 'owner_id' => $owner->getKey()]);

        $this->assertFalse($owner->belongsToTeam($team));
        $this->assertTrue($owner->hasTeamAbility($team, 'articles.edit', $article));
        $this->assertTrue($owner->hasTeamAbility($team, 'articles.edit', OwnedArticle::class, $article->getKey()));
        $this->assertFalse($outsider->hasTeamAbility($team, 'articles.edit', $article));
        $this->assertFalse($outsider->hasTeamAbility($team, 'articles.edit', OwnedArticle::class, $article->getKey()));
    }
}

class OwnedArticle extends Article
{
    protected $table = 'articles';

    public function isOwner(Model $user): bool
    {
        return (string) $this->owner_id === (string) $user->getKey();
    }
}

class ScopedUser extends User
{
    protected $table = 'users';

    public function groups(): BelongsToMany
    {
        return parent::groups()->where('groups.code', 'visible');
    }
}

class ScopedRole extends Role
{
    public function permissions(): MorphToMany
    {
        return parent::permissions()->where('permissions.code', 'visible.role');
    }
}

class ScopedGroup extends Group
{
    public function permissions(): MorphToMany
    {
        return parent::permissions()->where('permissions.code', 'visible.group');
    }
}
