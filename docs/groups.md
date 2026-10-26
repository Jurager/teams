---
title: Groups
weight: 80
---

# Groups

A group gives extra permissions to some members of a team, on top of their role. A user may belong to several groups.

## Creating Groups

Groups work just like [roles](roles-permissions.md). They are created using the team's `groups` relationship and found by their code:

```php
$team->groups()->create(['code' => 'reviewers'])->grant('articles.publish');

$group = $team->group('reviewers');

$group->revoke('articles.publish');

$group->delete();
```

## Adding and Removing Members

To add users to a group or remove them, use the `add` and `remove` methods. Both accept a user or a collection of users:

```php
$team->group('reviewers')->add($user);

$team->group('reviewers')->remove($user);
```

Users who aren't in the team are skipped. The users of a group are available through `$group->members`.

## Global Groups

A group without a team is global.

Its permissions pass [record checks](abilities.md) in every team, even for users who aren't members. Global groups are useful for staff such as support agents:

```php
use Jurager\Teams\Models\Group;

Group::create(['code' => 'support'])->grant('tickets.*')->add($agent);

$agent->can('tickets.reply', $ticket); // true, in any team
```

> [!NOTE]
> Global groups only affect record checks. They don't give membership, a role, or team-wide permissions, and they can't have [ability rules](abilities.md).
