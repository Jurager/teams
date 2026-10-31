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

## External Records

You may assign and check permissions for records from external services without an Eloquent model. Simply provide a resource type and ID:

```php
$member = $team->member($user);

$member->allow('products.update')->on('catalog.product', 42);
$team->role('reviewer')->forbid('products.delete')->on('catalog.product', 42);

$member->can('products.update', 'catalog.product', 42);
$member->canAny(['products.view', 'products.update'], 'catalog.product', 42);
$member->inspect('products.update', 'catalog.product', 42);

$member->forget('products.update')->on('catalog.product', 42);
```

Resource types may be any non-empty string, while IDs must be non-negative integers or numeric strings.

Since external records are not loaded, your application is responsible for ensuring they belong to the correct team. All other permission behavior remains the same.

For Eloquent models, continue passing the model instance to ensure proper team scoping.

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

You may retrieve a record's permission rules using the `rules` method:

```php
$rules = $team->rules($article)->get();

foreach ($rules as $rule) {
    $rule->permission->code;
    $rule->recipient; // User, role, or group
    $rule->forbidden; // Whether access is denied
    $rule->expires_at; // Expiration date or null
}
```

Each rule belongs to a specific recipient. Since `rules` returns an Eloquent query builder, you may filter, sort, or paginate the results:

```php
$rules = $team->rules($article)
    ->whereRelation('permission', 'code', 'articles.edit')
    ->whereMorphedTo('recipient', $user)
    ->paginate(15);
```

For external records, provide the resource type and ID:

```php
$rules = $team->rules('catalog.product', 42)->get();
```

You may also query rules for multiple records or recipients using `forRecords` and `forRecipients`:

```php
$rules = $team->abilities()
    ->forRecords($articles)
    ->forRecipients([$user, $role, $group])
    ->with(['permission', 'recipient'])
    ->get();
```

Expired rules remain available until explicitly removed.

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
