<?php

namespace Jurager\Teams\Models;

use JsonSerializable;

/**
 * The role of the team owner. It is not stored and grants every permission.
 */
class Owner implements JsonSerializable
{
    /**
     * The key of the role. The owner role is not stored, so it has none.
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
     * Get the JSON serializable representation of the role.
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => __($this->name),
        ];
    }
}
