<?php

namespace Jurager\Teams\Tests;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Jurager\Teams\Events\TeamMemberAdded;

class HasMembersTest extends TestCase
{
    public function test_updating_a_group_name_preserves_permissions_unless_explicitly_replaced(): void
    {
        $team = $this->team();
        $team->addGroup('admins', ['articles.*']);
        $group = $team->updateGroup('admins', name: 'Administrators');
        $this->assertSame('Administrators', $group->name);
        $this->assertSame(['articles.*'], $group->permissions->pluck('code')->all());
        $this->assertSame(['articles.*'], $team->updateGroup('admins', null)->permissions->pluck('code')->all());
        $this->assertSame(['articles.view'], $team->updateGroup('admins', ['articles.view'])->permissions->pluck('code')->all());
        $this->assertEmpty($team->updateGroup('admins', [])->permissions);
    }

    public function test_role_updates_distinguish_an_omitted_description_from_explicit_null(): void
    {
        $team = $this->team();
        $team->addRole('editor', ['articles.edit'], description: 'Edits articles');
        $role = $team->updateRole('editor', name: 'Editor');
        $this->assertSame('Edits articles', $role->description);
        $this->assertSame(['articles.edit'], $role->permissions->pluck('code')->all());
        $role = $team->updateRole('editor', description: null);
        $this->assertNull($role->description);
        $this->assertSame(['articles.edit'], $role->permissions->pluck('code')->all());
        $this->assertSame('New description', $team->updateRole('editor', description: 'New description')->description);
        $role = $team->updateRole('editor', []);
        $this->assertSame('New description', $role->description);
        $this->assertEmpty($role->permissions);
        $this->assertNull($team->updateRole('editor', null, null, null)->description);
    }

    public function test_accepting_an_existing_members_invitation_preserves_their_role(): void
    {
        Mail::fake();
        $team = $this->team();
        $team->addRole('reader', ['articles.view']);
        $team->addRole('editor', ['articles.*']);
        $user = $this->user();
        $team->inviteUser($user->email, 'editor');
        $invitation = $team->invitations()->firstOrFail();
        $team->addUser($user, 'reader');
        $added = 0;
        Event::listen(TeamMemberAdded::class, function () use (&$added) {
            $added++;
        });
        $team->inviteAccept($invitation->id);

        $this->assertSame(0, $team->invitations()->count());
        $this->assertSame('reader', $user->teamRole($team)->code);
        $this->assertSame(0, $added);
    }

    public function test_accepting_an_owners_stale_invitation_only_deletes_the_invitation(): void
    {
        $team = $this->team();
        $role = $team->addRole('editor', []);
        $invitation = $team->invitations()->create(['email' => $team->owner->email, 'role_id' => $role->id]);
        $team->inviteAccept($invitation->id);

        $this->assertSame(0, $team->invitations()->count());
        $this->assertSame(0, $team->users()->count());
    }
}
