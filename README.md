# Laravel Subscriptions

A Subscriptions package for laravel.

All ideas are welcome, please send your issue in: [Send your issues in here](https://github.com/alinrzv/laravel-subscriptions/issues/new)

## Installation

Via Composer Json > Repositories 

Add repository to composer.json : 

```
    "repositories":[

	    {
	        "type": "vcs",
	        "url": "https://github.com/alinrzv/laravel-subscriptions.git"
	    }
	]
```

And install using require as usual :

``` bash
composer require alinrzv/laravel-subscriptions
```

#### Register Service Provider
Add `BestDigital\LaravelSubscriptions\LaravelSubscriptionsServiceProvider::class` to your file `config/app.php`

```php
'providers' => [
    /**
    * Some Providers
    */
    BestDigital\LaravelSubscriptions\LaravelSubscriptionsServiceProvider::class
]
```

#### Config file and migrations
Publish package config file and migrations with the following command:
```cmd
php artisan vendor:publish --provider="BestDigital\LaravelSubscriptions\LaravelSubscriptionsServiceProvider"
```

Then run migrations:
```cmd
php artisan migrate
```

## Features Overview

- Create plans and his features or consumables. (consumables is in development)
- Manage your plans: get all plans, disable, delete, etc.
- Your user can subscribe to a plan.
- The user can renew, cancel, upgrade or downgrade his subscription.
- Group your plans now is very simple.
- A lot more

## A few examples

### Configure your User model for use subscriptions
````php
<?php
use BestDigital\LaravelSubscriptions\Traits\HasSubscriptions;

class User extends Authenticable
{
    use HasSubscriptions; // Add this line for use subscriptions
````

### Create a plan with features

```php
<?php
use BestDigital\LaravelSubscriptions\Entities\Plan;
use BestDigital\LaravelSubscriptions\Entities\PlanFeature;
use \BestDigital\LaravelSubscriptions\Entities\PlanConsumable;
use BestDigital\LaravelSubscriptions\Entities\PlanInterval;

$plan = Plan::create(
        'name of plan', //name
        'this is a description', //description
        1 // sort order
    );
$features = [
    PlanFeature::make('listings', 50),
    PlanFeature::make('pictures_per_listing', 10),
    PlanFeature::make('listing_duration_days', 30),
    PlanFeature::make('listing_title_bold', true),
    PlanConsumable::make('number_of_contacts', 10),
];

// adding features to plan
$plan->features()->saveMany($features);

$plan->isFree(); // return true;

// adding interval of price
$interval = PlanInterval::make(PlanInterval::MONTH, 1, 4.90);
$plan->setInterval($interval);

$plan->isFree(); // return false;
$plan->isNotFree(); // return true; 
```

### An user can subscribe to a plan
```php
<?php
use BestDigital\LaravelSubscriptions\Entities\Plan;

$user = \Auth::user();
$plan = Plan::find(1);

$user->subscribeTo($plan);

$user->hasActiveSubscription(); // return true;

$currentSubscription = $user->getActiveSubscription(); // return Subscription object;

```

## Upgrade or Downgrade Subscription
````php
<?php
use BestDigital\LaravelSubscriptions\Entities\Plan;

$user = \Auth::user();
$firstPlan = Plan::find(1);
$secondPlan = Plan::find(2);

//upgrade or downgrade depending of the price
$user->changePlanTo($secondPlan);
````

### Unsubscribe
````php
<?php
$user = \Auth::user();

// the subscription is will end in the expiration date
$user->unsubscribe();

// the subscription end now
$user->forceUnsubscribe();
````

### Testing

``` bash
composer test
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
