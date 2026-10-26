<?php

namespace Jurager\Teams\Models;

use JsonSerializable;

class Owner implements JsonSerializable
{
    /**
     * The key identifier for the role. The owner role is not stored, so it has none.
     */
    public string|int|null $id = null;

    /**
     * The code of the role.
     */
    public string $code = 'owner';

    /**
     * The name of the role.
     */
    public string $name = 'Owner';

    /**
     * The role's permissions.
     */
    public array $permissions = ['*'];

    /**
     * The role's description.
     */
    public string $description = '';

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
            'code' => $this->code,
            'name' => __($this->name),
            'description' => __($this->description),
            'permissions' => $this->permissions,
        ];
    }
}
