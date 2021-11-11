<?php

namespace BestDigital\LaravelSubscriptions\Entities;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use BestDigital\LaravelSubscriptions\Contracts\PlanContract;
use BestDigital\LaravelSubscriptions\Contracts\PlanIntervalContract;
use BestDigital\LaravelSubscriptions\Contracts\SubscriptionContact;
use BestDigital\LaravelSubscriptions\PlanFeature;
use BestDigital\LaravelSubscriptions\Contracts\SubscriptionConsumableContact;
use BestDigital\LaravelSubscriptions\Exceptions\SubscriptionErrorException;
use BestDigital\LaravelSubscriptions\SubscriberConsumable as MasterSubscriberConsumable;

class SubscriberConsumable extends MasterSubscriberConsumable implements SubscriptionConsumableContact
{

    public static function make(PlanFeature $feature, SubscriptionContact $subscription,string $available): Model
    {
        if (! $feature instanceof Model) {
            throw new SubscriptionErrorException('$plan must be '.Model::class);
        }

        return new self([
            'plan_feature_code' => $feature->code,
            'subscription_id' => $subscription->id,
            'available' => $available,
        ]);
    }
    
    public static function makeInterval(PlanFeature $feature, SubscriptionContact $subscription,string $available): Model
    {
        if (! $feature instanceof Model) {
            throw new SubscriptionErrorException('$plan must be '.Model::class);
        }

        return new self([
            'plan_feature_code' => $feature->code,
            'subscription_id' => $subscription->id,
            'available' => $available,
        ]);
    }
    
}
