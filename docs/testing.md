---
title: Testing
weight: 140
---

# Testing

## Model Factories

The package models come with factories, so you may create teams, roles, groups, and invitations in your application's tests:

```php
use Jurager\Teams\Models\Group;
use Jurager\Teams\Models\Invitation;
use Jurager\Teams\Models\Role;
use Jurager\Teams\Models\Team;

$team = Team::factory()->create();

$role = Role::factory()->for($team)->grant('articles.view', 'articles.edit')->create();

$group = Group::factory()->for($team)->grant('articles.publish')->create();

$invitation = Invitation::factory()->for($team)->create();
```

A team's owner is created with your `User` model's factory, so the model must use the `HasFactory` trait.

To add members, use the `members` method. It creates the given number of users and adds them with the given role, creating the role if it doesn't exist:

```php
$team = Team::factory()->members(3, 'editor')->create();
```

To create a [global group](groups.md#global-groups), use the `global` method:

```php
$group = Group::factory()->global()->grant('tickets.*')->create();
```

The factories always create the models configured in the [configuration](configuration.md#models), so they also work with your custom models.
