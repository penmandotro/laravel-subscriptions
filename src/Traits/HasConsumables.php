<?php

namespace PenMan\LaravelSubscriptions\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PenMan\LaravelSubscriptions\SubscriberConsumable;

trait HasConsumables
{
    /**
     * @param  PlanFeature|Model  $feature
     */
    public function addConsumable(SubscriberConsumable $consumable)
    {
        $this->consumables()->save($consumable);
    }

    public function consumables(): HasMany
    {
        return $this->hasMany(config('subscriptions.entities.plan_subscription_consumable'));
    }

    public function getConsumableByCode($consumableCode)
    {
        return $this->consumables()->where('plan_feature_code',$consumableCode)->first();
    }

    public function hasConsumables(): bool
    {
        return $this->consumables()->exists();
    }
}
