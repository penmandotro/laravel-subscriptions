<?php

namespace PenMan\LaravelSubscriptions\Contracts;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use BestDigital\LaravelSubscriptions\PlanFeature;

interface SubscriptionConsumableContact
{
    public static function make(PlanFeature $feature, SubscriptionContact $subscription,string $available): Model;

    public static function makeInterval(PlanFeature $interval_feature, SubscriptionContact $subscription,string $available): Model;

}
