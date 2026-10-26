<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Database\Factories\InvitationFactory;
use Jurager\Teams\Support\Facades\Teams;
use RuntimeException;

class Invitation extends Model
{
    use HasFactory;
    use Prunable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['role_id', 'email'];

    /**
     * Create a new model instance.
     */
    public function __construct(array $attributes = [])
    {
        $this->mergeFillable([Config::get('teams.foreign_keys.team_id')]);

        parent::__construct($attributes);
    }

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        return $this->table ?? Config::get('teams.tables.invitations', 'invitations');
    }

    /**
     * Get the team that the invitation belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Teams::model('team'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get the role the invited user will get.
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Teams::model('role'), 'role_id');
    }

    /**
     * Get the invited user, found by email.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(Teams::model('user'), 'email', 'email');
    }

    /**
     * Add the invited user to the team and delete the invitation.
     *
     * A user who already belongs to the team keeps their role.
     */
    public function accept(): void
    {
        if (! Config::get('teams.invitations.enabled')) {
            throw new RuntimeException('Team invitations are disabled.');
        }

        $this->getConnection()->transaction(function () {
            $team = $this->team()->lockForUpdate()->firstOrFail();
            $invitation = $this->newQuery()->lockForUpdate()->findOrFail($this->getKey());
            $user = $invitation->user ?? throw new RuntimeException('Invited user not found.');

            if (! $user->in($team)) {
                $team->add($user)->as($invitation->role);
            }

            $this->delete();
        });
    }

    /**
     * Decline the invitation.
     */
    public function decline(): void
    {
        $this->delete();
    }

    /**
     * Get the invitations whose links have expired.
     */
    public function prunable(): Builder
    {
        $expire = Config::get('teams.invitations.expire');

        return $expire === null
            ? static::query()->whereRaw('1 = 0')
            : static::query()->where('updated_at', '<', now()->subMinutes($expire));
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): InvitationFactory
    {
        return InvitationFactory::new();
    }
}
