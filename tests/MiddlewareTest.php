<?php

namespace Jurager\Teams\Tests;

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Route;
use Jurager\Teams\Models\Team;
use Jurager\Teams\Tests\Fixtures\Article;

class MiddlewareTest extends TestCase
{
    public function test_route_team_cannot_be_overridden_with_request_input(): void
    {
        $protected = $this->team();
        $owned = $this->team();
        Route::get('/teams/{team_id}/protected', fn () => 'ok')->middleware('permission:articles.edit');
        $this->actingAs($owned->owner)->getJson('/teams/'.$protected->id.'/protected?team_id='.$owned->id)->assertForbidden();
    }

    public function test_explicit_team_parameter_name_literal_id_and_boolean_flags(): void
    {
        $team = $this->team();
        $user = $this->member($team, ['articles.view']);
        Route::get('/teams/{team}/named', fn () => 'ok')->middleware('permission:articles.view,team');
        Route::get('/literal', fn () => 'ok')->middleware('permission:articles.view,'.$team->id);
        Route::get('/all', fn () => 'ok')->middleware('permission:articles.view|articles.edit,'.$team->id.',require');
        Route::get('/any', fn () => 'ok')->middleware('permission:articles.view|articles.edit,'.$team->id.',false');
        $this->actingAs($user)->getJson('/teams/'.$team->id.'/named?team=999')->assertOk();
        $this->getJson('/literal')->assertOk();
        $this->getJson('/literal?'.$team->id.'=999')->assertOk();
        $this->getJson('/all')->assertForbidden();
        $this->getJson('/any')->assertOk();
    }

    public function test_role_middleware_resolves_bound_team_models(): void
    {
        $team = $this->team();
        $user = $this->member($team);
        $role = $user->teamRole($team);
        Route::model('team', Team::class);
        Route::get('/teams/{team}/role', fn () => 'ok')
            ->middleware([SubstituteBindings::class, 'role:'.$role->code.',team']);
        $this->actingAs($user)->getJson('/teams/'.$team->id.'/role')->assertOk();
    }

    public function test_ability_middleware_resolves_bound_models_route_ids_and_input_ids(): void
    {
        $team = $this->team();
        $user = $this->member($team, ['articles.edit']);
        $article = Article::create(['team_id' => $team->id]);
        Route::model('article', Article::class);
        $middleware = 'ability:articles.edit,'.Article::class.',article';
        Route::get('/teams/{team_id}/articles/{article}', fn () => 'ok')->middleware([SubstituteBindings::class, $middleware]);
        Route::get('/teams/{team_id}/input', fn () => 'ok')->middleware($middleware);
        $this->actingAs($user)->getJson('/teams/'.$team->id.'/articles/'.$article->id)->assertOk();
        $this->getJson('/teams/'.$team->id.'/input?article='.$article->id)->assertOk();
        $this->getJson('/teams/'.$team->id.'/input')->assertForbidden();
        $this->getJson('/teams/'.$team->id.'/input?article=999')->assertForbidden();
        $this->getJson('/teams/'.$team->id.'/input?article[]=1')->assertForbidden();
    }

    public function test_ability_middleware_rejects_entities_from_another_team_even_for_owners(): void
    {
        $team = $this->team();
        $other = $this->team();
        $article = Article::create(['team_id' => $other->id]);
        Route::get('/teams/{team_id}/articles/{article_id}', fn () => 'ok')
            ->middleware('ability:articles.edit,'.Article::class.',article_id');
        $this->actingAs($team->owner)->getJson('/teams/'.$team->id.'/articles/'.$article->id)->assertForbidden();
    }

    public function test_guest_and_unknown_team_fail_without_server_errors(): void
    {
        Route::get('/teams/{team_id}/articles/{article_id}', fn () => 'ok')
            ->middleware('ability:articles.edit,'.Article::class.',article_id');
        $this->getJson('/teams/999/articles/999')->assertForbidden();
        $this->actingAs($this->user())->getJson('/teams/999/articles/999')->assertNotFound();
    }

    public function test_ability_middleware_passes_with_any_of_the_listed_abilities(): void
    {
        $team = $this->team();
        $user = $this->member($team, ['articles.delete']);
        $article = Article::create(['team_id' => $team->id]);
        Route::get('/teams/{team_id}/any/{article_id}', fn () => 'ok')
            ->middleware('ability:articles.edit|articles.delete,'.Article::class.',article_id');
        Route::get('/teams/{team_id}/one/{article_id}', fn () => 'ok')
            ->middleware('ability:articles.edit,'.Article::class.',article_id');
        $this->actingAs($user)->getJson('/teams/'.$team->id.'/any/'.$article->id)->assertOk();
        $this->getJson('/teams/'.$team->id.'/one/'.$article->id)->assertForbidden();
    }
}
