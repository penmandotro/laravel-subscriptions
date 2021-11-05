<?php

/*
 * You can place your custom package configuration in here.
 */
return [
    'entities' => [
        'user' => \App\User::class,
        'plan' => \BestDigital\LaravelSubscriptions\Entities\Plan::class,
        'plan_feature' => \BestDigital\LaravelSubscriptions\Entities\PlanFeature::class,
        'plan_interval' => \BestDigital\LaravelSubscriptions\Entities\PlanInterval::class,
        'plan_subscription' => \BestDigital\LaravelSubscriptions\Entities\Subscription::class,
    ],
    'default_features' => [
        'features' => [
            //'is_featured_clinic' => true
        ],
        'consumables' => [
            // Consumables
            //'number_of_contacts' => 5,
        ],
    ],
];
