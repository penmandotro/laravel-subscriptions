# Laravel Subscriptions

A Subscriptions package for laravel.

## Features Overview

- Create plans and his features or consumables.
- Finished features CONSUMABLES processing
- Manage your plans: get all plans, disable, delete, etc.
- Your user can subscribe to a plan.
- The user can renew, cancel, upgrade or downgrade his subscription.
- Group your plans now is very simple.
- Let your users enjoy some free_days on a plan subscription, EX: before consuming features.
- A lot more

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
namespace App\Http\Controllers;

use BestDigital\LaravelSubscriptions\Entities\Plan;
use BestDigital\LaravelSubscriptions\Entities\PlanFeature;
use BestDigital\LaravelSubscriptions\Entities\PlanConsumable;
use BestDigital\LaravelSubscriptions\Entities\PlanInterval;

$plan = Plan::create(
        'name of plan', //name
        'this is a description', //description
        '10', // free_days 
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

### Get Consumable Features & Consume Feature ex.

```php
<?php 
namespace App\Http\Controllers;

use App\User;
use BestDigital\LaravelSubscriptions\Entities\Plan;
use BestDigital\LaravelSubscriptions\Entities\PlanFeature;
use BestDigital\LaravelSubscriptions\Entities\PlanConsumable;
use BestDigital\LaravelSubscriptions\Entities\PlanInterval;

class MyCustomController extends Controller
{

public function getUserConsumables(){

    $user = auth()->user();
    
    $consumables = array();
    
    if($user->hasActiveSubscription()){
         
         # get array of consumables
         # defined in vendor package /src/Traits/HasSubscriptions.php
         $consumables =  $user->getConsumables();

    }
    
    return $consumables;

}

public function testConsumeFeature() {
	    
        $code = "credits"; // feature code example
        $qty = "100"; // feature value example 
        
	$user = auth()->user();
	    
        # check first if user has active subscription
        if($user->hasActiveSubscription()){
            
            # feature check is done inside vendor package /src/Traits/HasSubscriptions.php
            # consumeFeature > returns false if : 
            # - subscription has no features
            # - $code does not exist in features list
            # - $qty quantity is bigger than the existing remaining value in database
            
            if($reqconsume = $user->consumeFeature($code,$qty)){
                return response()->json(array('success'=>'Feature consumed successfully!'));
            }else {
                return response()->json(array('error'=>'Failed consuming qty!'));
            }
        
        }
        else {
                return response()->json(array('error'=>'No active subscription!'));
            }

}


}

```

### An user can subscribe to a plan
```php
<?php
namespace App\Http\Controllers;

use BestDigital\LaravelSubscriptions\Entities\Plan;

$user = auth()->user();
$plan = Plan::find(1);

$user->subscribeTo($plan);

$user->hasActiveSubscription(); // return true;

$currentSubscription = $user->getActiveSubscription(); // return Subscription object;

```

## Plan free_days > Check Subscription free days
## Use this to check if subscription is in its free days period
- (1st Rulle) Use it for a Plan that has free_days setup AND a PlanInterval with price bigger than 0.
- Returns TRUE (meaning subscription is free), if subscription plan has free_days setup but active PlanInterval price is 0.
- Returns TRUE (meaning subscription is free), if it meets (1st Rulle) AND if the subscription plan is in its free days range,
  Meaning (days since subscribed untill Today) <= (is lower or equal) than/with plan free_days.
- Returns FALSE by default in all other cases.

```php
<?php 
namespace App\Http\Controllers;

use App\User;
use BestDigital\LaravelSubscriptions\Entities\Plan;
use BestDigital\LaravelSubscriptions\Entities\PlanFeature;
use BestDigital\LaravelSubscriptions\Entities\PlanConsumable;
use BestDigital\LaravelSubscriptions\Entities\PlanInterval;

class MyCustomController extends Controller
{

	public function TestcheckUserSubscriptionDays(){
	
	    $user = auth()->user();
	    
	    if($user->hasActiveSubscription()){
	
		# SubsCheckFreeDays defined in vendor package /src/Traits/HasSubscriptions.php
		if($user->SubsCheckFreeDays()){
			// user is in free use :D , do stuff
		}else {
			// user is in paid mode , ex: consume features 
			$code = "credits"; // feature code example
		        $qty = "100"; // feature value example 
			$user->consumeFeature($code,$qty);
		}
	
	    }
	
	}

}

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
