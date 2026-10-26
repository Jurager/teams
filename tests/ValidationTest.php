<?php

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Jurager\Teams\Models\Group as GroupModel;
use Jurager\Teams\Rules\Group;
use Jurager\Teams\Rules\Invitable;
use Jurager\Teams\Rules\Member;
use Jurager\Teams\Rules\Role;

function validates(mixed $value, ValidationRule $rule): bool
{
    return Validator::make(['value' => $value], ['value' => [$rule]])->passes();
}

it('accepts role codes of the team', function () {
    $team = team();
    role($team, 'editor');
    role(team(), 'admin');

    expect(validates('editor', new Role($team)))->toBeTrue()
        ->and(validates('admin', new Role($team)))->toBeFalse()
        ->and(validates(['editor'], new Role($team)))->toBeFalse();
});

it('accepts group codes of the team', function () {
    $team = team();
    $team->groups()->create(['code' => 'reviewers']);
    team()->groups()->create(['code' => 'editors']);
    GroupModel::create(['code' => 'support']);

    expect(validates('reviewers', new Group($team)))->toBeTrue()
        ->and(validates('editors', new Group($team)))->toBeFalse()
        ->and(validates('support', new Group($team)))->toBeFalse()
        ->and(validates(['reviewers'], new Group($team)))->toBeFalse();
});

it('accepts members of the team', function () {
    $team = team();
    $member = member($team);

    expect(validates($member->id, new Member($team)))->toBeTrue()
        ->and(validates((string) $member->id, new Member($team)))->toBeTrue()
        ->and(validates(member(team())->id, new Member($team)))->toBeFalse()
        ->and(validates(user()->id, new Member($team)))->toBeFalse()
        ->and(validates([$member->id], new Member($team)))->toBeFalse();
});

it('accepts the owner only when asked to', function () {
    $team = team();

    expect(validates($team->owner->id, new Member($team)))->toBeFalse()
        ->and(validates($team->owner->id, new Member($team, owner: true)))->toBeTrue()
        ->and(validates(user()->id, new Member($team, owner: true)))->toBeFalse();
});

it('accepts emails that are not used in the team', function () {
    $team = team();

    expect(validates('new@example.com', new Invitable($team)))->toBeTrue()
        ->and(validates(member(team())->email, new Invitable($team)))->toBeTrue()
        ->and(validates($team->owner->email, new Invitable($team)))->toBeFalse()
        ->and(validates(member($team)->email, new Invitable($team)))->toBeFalse()
        ->and(validates(['new@example.com'], new Invitable($team)))->toBeFalse();
});

it('explains why a value is rejected', function (string $rule, mixed $value, string $message) {
    $team = team();

    expect(Validator::make(['value' => $value], ['value' => [new $rule($team)]])->errors()->first('value'))->toBe($message);
})->with([
    'role' => [Role::class, 'missing', 'The value must be a valid role.'],
    'group' => [Group::class, 'missing', 'The value must be a valid group.'],
    'member' => [Member::class, 0, 'The selected value is not a member of the team.'],
    'invitable' => [Invitable::class, ['missing'], 'The value is already used by a member of the team.'],
]);
