<?php

namespace Jurager\Teams\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Jurager\Teams\Traits\HasMembers;

class Team extends Model
{
    use HasMembers;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = ['user_id', 'name'];

    /**
     * The relationships that should always be loaded.
     *
     * @var array
     */
    protected $with = ['roles.permissions', 'groups.permissions'];

    /**
     * Creates a new instance of the model.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->table = Config::get('teams.tables.teams', 'teams');
    }
}