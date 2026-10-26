<?php

namespace Jurager\Teams\Observers;

use Illuminate\Database\Eloquent\Model;
use Jurager\Teams\Support\Facades\Teams as TeamsFacade;

class PermissionCacheObserver
{
    public function created(Model $model): void
    {
        $this->invalidate($model);
    }

    public function updated(Model $model): void
    {
        $this->invalidate($model);
    }

    public function deleted(Model $model): void
    {
        $this->invalidate($model);
    }

    /**
     * Clear cached decisions now, or leave it to the transaction listener.
     */
    protected function invalidate(Model $model): void
    {
        if ($model->getConnection()->transactionLevel() === 0) {
            TeamsFacade::forgetCachedPermissions();
        }
    }
}
