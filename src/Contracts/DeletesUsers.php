<?php

namespace Jurager\Teams\Contracts;

interface DeletesUsers
{
    /**
     * Delete the given user.
     */
    public function delete(object $user): void;
}
