<?php

namespace Jurager\Teams;

use JsonSerializable;

class Owner implements JsonSerializable
{
    /**
     * The key identifier for the role.
     */
    public string|int $id;

    /**
     * The code of the role.
     */
    public string $code;

    /**
     * The name of the role.
     */
    public string $name;

    /**
     * The role's permissions.
     */
    public array $permissions;

    /**
     * The role's description.
     */
    public string $description;

    /**
     * Create a new role instance.
     */
    public function __construct()
    {
        $this->id = 1;
        $this->code = 'owner';
        $this->name = 'Owner';
        $this->permissions = ['*'];
    }

    /**
     * Describe the role.
     *
     * @return $this
     */
    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get the JSON serializable representation of the object.
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => __($this->name),
            'description' => __($this->description),
            'permissions' => $this->permissions,
        ];
    }
}
