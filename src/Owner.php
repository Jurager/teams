<?php

namespace Jurager\Teams;

use JsonSerializable;

class Owner implements JsonSerializable
{
    /**
     * The key identifier for the role.
     *
     * @var string
     */
    public $id;

    /**
     * The name of the role.
     *
     * @var string
     */
    public $name;

    /**
     * The role's permissions.
     *
     * @var array
     */
    public $permissions;

    /**
     * The role's description.
     *
     * @var string
     */
    public $description;

    /**
     * Create a new role instance.
     *
     */
    public function __construct()
    {
        $this->id          = 1;
        $this->name        = 'owner';
        $this->permissions = ['*'];
    }

    /**
     * Describe the role.
     *
     * @param  string  $description
     * @return $this
     */
    public function description(string $description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get the JSON serializable representation of the object.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return [
            'id'            => $this->id,
            'name'          => __($this->name),
            'description'   => __($this->description),
            'permissions'   => $this->permissions,
        ];
    }
}