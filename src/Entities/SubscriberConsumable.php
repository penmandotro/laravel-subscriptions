<?php

namespace PenMan\LaravelSubscriptions\Entities;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PenMan\LaravelSubscriptions\Contracts\PlanContract;
use PenMan\LaravelSubscriptions\Contracts\PlanIntervalContract;
use PenMan\LaravelSubscriptions\Contracts\SubscriptionContact;
use PenMan\LaravelSubscriptions\PlanFeature;
use PenMan\LaravelSubscriptions\Contracts\SubscriptionConsumableContact;
use PenMan\LaravelSubscriptions\Exceptions\SubscriptionErrorException;
use PenMan\LaravelSubscriptions\SubscriberConsumable as MasterSubscriberConsumable;

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
