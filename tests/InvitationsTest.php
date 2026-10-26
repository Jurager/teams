<?php

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Jurager\Teams\Events\TeamMemberAdded;
use Jurager\Teams\Events\TeamMemberAdding;
use Jurager\Teams\Mail\Invitation as InvitationMail;
use Jurager\Teams\Models\Invitation;

beforeEach(fn () => Mail::fake());

it('saves, mails and resends an invitation without duplicates', function () {
    $team = team();
    role($team, 'reader');
    role($team, 'editor');

    $invitation = $team->invite('invited@example.com')->as('reader');
    $team->invite('invited@example.com')->as('editor');

    expect($invitation)->toBeInstanceOf(Invitation::class)
        ->and($team->invitations()->count())->toBe(1)
        ->and($team->invitations()->first()->role->code)->toBe('editor')
        ->and((new InvitationMail($invitation))->render())
        ->toContain("/invitation/{$invitation->id}/accept", 'signature=');

    Mail::assertSent(InvitationMail::class, 2);
});

it('does not invite the owner or members', function (Closure $email) {
    $team = team();
    role($team, 'reader');

    expect(fn () => $team->invite($email($team))->as('reader'))->toThrow(RuntimeException::class)
        ->and($team->invitations()->count())->toBe(0);
})->with([
    'owner' => fn ($team) => $team->owner->email,
    'member' => fn ($team) => member($team)->email,
]);

it('adds the user and deletes the invitation on accept', function () {
    $team = team();
    role($team, 'editor', ['articles.edit']);
    $user = user();

    $team->invite($user->email)->as('editor')->accept();

    expect($team->member($user)->can('articles.edit'))->toBeTrue()
        ->and($team->invitations()->count())->toBe(0);
});

it('keeps the role of an existing member', function () {
    $team = team();
    role($team, 'reader');
    role($team, 'editor');
    $user = user();
    $invitation = $team->invite($user->email)->as('editor');
    $team->add($user)->as('reader');
    Event::fake(TeamMemberAdded::class);

    $invitation->accept();

    expect($team->invitations()->count())->toBe(0)
        ->and($team->member($user)->is('reader'))->toBeTrue();

    Event::assertNotDispatched(TeamMemberAdded::class);
});

it('only deletes a stale invitation of the owner', function () {
    $team = team();
    $role = role($team, 'editor');

    $team->invitations()->create(['email' => $team->owner->email, 'role_id' => $role->id])->accept();

    expect($team->invitations()->count())->toBe(0)
        ->and($team->members()->count())->toBe(0);
});

it('rolls back acceptance when a listener fails before the change', function () {
    $team = team();
    role($team, 'editor');
    $user = user();
    $invitation = $team->invite($user->email)->as('editor');
    Event::listen(TeamMemberAdding::class, fn () => throw new RuntimeException('Listener failed.'));

    expect(fn () => $invitation->accept())->toThrow(RuntimeException::class, 'Listener failed.')
        ->and($user->in($team))->toBeFalse()
        ->and($invitation->fresh())->not->toBeNull();
});

it('keeps acceptance when an after commit listener fails', function () {
    $team = team();
    role($team, 'editor');
    $user = user();
    $invitation = $team->invite($user->email)->as('editor');
    Event::listen(TeamMemberAdded::class, fn () => throw new RuntimeException('Listener failed.'));

    expect(fn () => $invitation->accept())->toThrow(RuntimeException::class, 'Listener failed.')
        ->and($user->in($team))->toBeTrue()
        ->and($team->invitations()->count())->toBe(0)
        ->and($team->getConnection()->transactionLevel())->toBe(0);
});

it('does not send or accept invitations when they are disabled', function () {
    $team = team();
    $role = role($team, 'editor');
    $invitation = $team->invitations()->create(['email' => user()->email, 'role_id' => $role->id]);
    config(['teams.invitations.enabled' => false]);

    expect(fn () => $team->invite('new@example.com'))->toThrow(RuntimeException::class, 'Team invitations are disabled.')
        ->and(fn () => $invitation->accept())->toThrow(RuntimeException::class, 'Team invitations are disabled.');
});

it('accepts through the package route only for the signed-in invitee', function () {
    $team = team();
    role($team, 'editor', ['articles.edit']);
    $user = user();
    $parameters = ['invitation_id' => $team->invite($user->email)->as('editor')->id];
    $url = URL::signedRoute('teams.invitations.accept', $parameters);

    $this->getJson($url)->assertUnauthorized();
    $this->actingAs(user())->get($url)->assertForbidden();
    $this->actingAs($user)->get(route('teams.invitations.accept', $parameters))->assertForbidden();
    expect($user->in($team))->toBeFalse();

    $this->actingAs($user)->get($url)->assertRedirect('/');
    expect($team->member($user)->can('articles.edit'))->toBeTrue();

    $this->get($url)->assertNotFound();
});

it('expires the link after the configured time', function () {
    config(['teams.invitations.expire' => 60]);
    $team = team();
    role($team, 'editor');
    $user = user();
    $html = (new InvitationMail($team->invite($user->email)->as('editor')))->render();
    preg_match('/href="([^"]*\/accept[^"]*)"/', $html, $matches);
    $url = html_entity_decode($matches[1]);

    expect($url)->toContain('expires=');

    $this->travel(61)->minutes();

    $this->actingAs($user)->get($url)->assertForbidden();
    expect($user->in($team))->toBeFalse();
});

it('lists the invitations sent to a user', function () {
    $user = user();
    $team = team();
    role($team, 'editor');
    $other = team();
    role($other, 'editor');

    $team->invite($user->email)->as('editor');
    $other->invite($user->email)->as('editor');
    $team->invite('someone@example.com')->as('editor');

    expect($user->invitations)->toHaveCount(2)
        ->and($user->invitations->first()->team->is($team))->toBeTrue();
});

it('declines an invitation', function () {
    $team = team();
    role($team, 'editor');
    $user = user();

    $team->invite($user->email)->as('editor')->decline();

    expect($user->invitations()->count())->toBe(0)
        ->and($user->in($team))->toBeFalse();
});

it('prunes invitations whose links have expired', function () {
    $team = team();
    role($team, 'editor');
    $expired = $team->invite('old@example.com')->as('editor');
    $this->travel(8)->days();
    $fresh = $team->invite('new@example.com')->as('editor');

    $this->artisan('model:prune', ['--model' => [Invitation::class]])->assertSuccessful();

    $this->assertModelMissing($expired);
    $this->assertModelExists($fresh);
});

it('prunes a resent invitation from the last email', function () {
    $team = team();
    role($team, 'editor');
    $invitation = $team->invite('jane@example.com')->as('editor');
    $this->travel(6)->days();
    $team->invite('jane@example.com')->as('editor');
    $this->travel(6)->days();

    $this->artisan('model:prune', ['--model' => [Invitation::class]])->assertSuccessful();

    $this->assertModelExists($invitation);
});

it('keeps invitations when links never expire', function () {
    config(['teams.invitations.expire' => null]);
    $team = team();
    role($team, 'editor');
    $invitation = $team->invite('jane@example.com')->as('editor');
    $this->travel(1)->years();

    $this->artisan('model:prune', ['--model' => [Invitation::class]])->assertSuccessful();

    $this->assertModelExists($invitation);
});
