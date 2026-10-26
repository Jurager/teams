<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Database\Factories\RoleFactory;
use Jurager\Teams\Support\Facades\Teams;
use Jurager\Teams\Traits\HasPermissions;
use RuntimeException;

class Role extends Model
{
    use HasFactory;
    use HasPermissions;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['code', 'name', 'description'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array<int, string>
     */
    protected $hidden = ['permissions'];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

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
        return $this->table ?? Config::get('teams.tables.roles', 'roles');
    }

    /**
     * Get the team that the role belongs to.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Teams::model('team'), Config::get('teams.foreign_keys.team_id'));
    }

    /**
     * Get the team members with this role.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Teams::model('user'), Teams::model('membership'), 'role_id', 'user_id');
    }

    /**
     * Delete the role with its permissions and rules. A role that members still have cannot be deleted.
     */
    public function delete(): ?bool
    {
        return $this->getConnection()->transaction(function () {
            if ($this->members()->exists()) {
                throw new RuntimeException('Reassign or remove team members before deleting their role.');
            }

            return parent::delete();
        });
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): RoleFactory
    {
        return RoleFactory::new();
    }
}
