---
title: Abilities
weight: 70
---

# Abilities

A permission applies to every record in the team. An ability allows or forbids an action on one record.

## Checking a Record

To check a permission on a record, pass the record to `can`. The team is taken from the record's `team_id` column:

```php
$user->can('articles.edit', $article);
```

When the record has no rules, the user's role and group permissions decide. A record of another team is always denied.

When selecting columns, include `team_id`, or your configured team foreign key. Checks deny records whose team key is missing or `null`, including checks by the team or record owner.

## Allowing and Forbidding Actions

Rules are added to a member, a role, or a group, and the `on` method names the record they apply to:

```php
$team->member($user)->allow('articles.edit')->on($article);

$team->role('editor')->forbid('articles.delete')->on($article);

$team->group('reviewers')->allow('articles.publish')->on($article);
```

To remove a rule, use the `forget` method:

```php
$team->member($user)->forget('articles.edit')->on($article);
```

Instead of a model, you may pass its class and key:

```php
$team->member($user)->allow('articles.edit')->on(Article::class, $articleId);
```

## Temporary Rules

Call `until` before `on` to give an allow or forbid rule a deadline:

```php
$team->member($user)
    ->allow('reports.download')
    ->until(now()->addDay())
    ->on($report);

$team->role('reviewer')->forbid('reports.edit')->until(now()->addHour())->on($report);
```

Each user's, role's, or group's assignment has its own deadline. At that time the rule stops deciding access and the remaining rules and permissions apply. Expiration is checked during authorization, including checks on preloaded rules.

Repeating the assignment with `until` replaces its deadline. Calling `allow` or `forbid` without `until` makes the assignment permanent.

## Listing Record Rules

The `rules` method queries the team's explicit rules on a record, with the permission and recipients already loaded:

```php
$rules = $team->rules($article)->get();

foreach ($rules as $rule) {
    $rule->permission->code;
    $rule->users;
    $rule->roles;
    $rule->groups;
}
```

Each recipient has a `pivot->forbidden` value: `true` forbids and `false` allows the permission. Definitions without recipients are omitted. The query supports filters and pagination:

```php
$rules = $team->rules($article)
    ->whereRelation('permission', 'code', 'articles.edit')
    ->paginate(15);
```

The recipient's `pivot->expires_at` is a Carbon instance, or `null` for a permanent rule. Expired assignments remain in this list until removed with `forget`.

## Pruning Unused Abilities

Removing a rule leaves its ability definition available for reuse. To delete definitions that have no assignments, schedule Laravel's `model:prune` command:

```php
use Jurager\Teams\Models\Ability;

Schedule::command('model:prune', ['--model' => [Ability::class]])->daily();
```

Use your configured ability class if you have a custom model. Assignments are checked again under a lock before deletion, so an ability assigned during pruning is kept. Permission definitions are retained.

To preview the candidates, run `php artisan model:prune --model="Jurager\Teams\Models\Ability" --pretend`.

## Record Owners

If a model has an `isOwner` method, a user for whom it returns `true` always passes:

```php
class Article extends Model
{
    public function isOwner(User $user): bool
    {
        return $this->user_id === $user->id;
    }
}
```

## Checking Many Records

Each record check runs a few queries. When checking a list of records, load the member's role, groups, and rules for all of them at once with the `load` method. Checks on the loaded records then run no queries:

```php
$member = $team->member($user)->load($articles);

foreach ($articles as $article) {
    if ($member->can('articles.edit', $article)) {
        // ...
    }
}
```

The loaded data isn't refreshed, so load it right before the checks.

## How Access Is Decided

The team owner, the record owner, and members of a matching [global group](groups.md#global-groups) always pass.
For everyone else in the team, the first step that has a rule or a permission decides:

1. The member's own rules.
2. The rules and permissions of the member's groups.
3. The rules and permissions of the member's role.

Within a step, a forbid wins over an allow. When no step matches, access is denied.
