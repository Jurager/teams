<?php

namespace Jurager\Teams\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class OwnedArticle extends Article
{
    protected $table = 'articles';

    public function isOwner(Model $user): bool
    {
        return (string) $this->owner_id === (string) $user->getKey();
    }
}
