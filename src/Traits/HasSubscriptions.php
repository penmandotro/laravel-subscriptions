<?php

namespace BestDigital\LaravelSubscriptions\Traits;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use BestDigital\LaravelSubscriptions\Contracts\PlanContract;
use BestDigital\LaravelSubscriptions\Contracts\PlanIntervalContract;
use BestDigital\LaravelSubscriptions\Contracts\SubscriptionContact;
#use BestDigital\LaravelSubscriptions\Entities\PlanFeature as ModelFeature;
use BestDigital\LaravelSubscriptions\Entities\PlanInterval;
use BestDigital\LaravelSubscriptions\Entities\Subscription;
use BestDigital\LaravelSubscriptions\Exceptions\SubscriptionErrorException;
use BestDigital\LaravelSubscriptions\PlanFeature;

trait HasSubscriptions
{
    /**
     * @param  PlanContract|PlanIntervalContract  $planOrInterval
     * @return Model|SubscriptionContact
     */
    public function subscribeTo($planOrInterval): SubscriptionContact
    {
        if ($planOrInterval instanceof PlanContract) {
            return $this->subscribeToPlan($planOrInterval);
        }

        return $this->subscribeToInterval($planOrInterval);
    }

    /**
     * @param  PlanContract  $plan
     * @return Model|SubscriptionContact
     * @throws SubscriptionErrorException
     */
    public function subscribeToPlan(PlanContract $plan): SubscriptionContact
    {
        if ($plan->isDisabled()) {
            throw new SubscriptionErrorException(
                'This plan has been disabled, please subscribe to other plan.'
            );
        }

        if ($this->subscriptions()->unfinished()->count() >= 2) {
            throw new SubscriptionErrorException('You are changed to other plan previously');
        }

        if ($plan->hasManyIntervals()) {
            throw new SubscriptionErrorException(
                'This plan has many intervals, please use subscribeToInterval() function'
            );
        }

        $currentSubscription = $this->getActiveSubscription();
        $start_at = null;
        $end_at = null;

        if ($currentSubscription == null) {
            $start_at = now();
        } else {
            $start_at = $currentSubscription->getExpirationDate();
        }

        if ($plan->isFree()) {
            $end_at = null;
        } else {
            $end_at = $this->calculateExpireDate($start_at, optional($plan->intervals())->first());
        }

        $subscription = Subscription::make($plan, $start_at, $end_at);
        $subscription = $this->subscriptions()->save($subscription);

        return $subscription;
    }

    public function subscriptions(): MorphMany
    {
        return $this->morphMany(config('subscriptions.entities.plan_subscription'), 'subscriber');
    }

    /**
     * @return SubscriptionContact|Model|null
     */
    public function getActiveSubscription(): ?SubscriptionContact
    {
        return $this->subscriptions()
            ->current()
            ->first();
    }

    private function calculateExpireDate(Carbon $start_at, PlanIntervalContract $interval)
    {
        $end_at = Carbon::createFromTimestamp($start_at->timestamp);

        switch ($interval->getType()) {
            case PlanInterval::DAY:
                return $end_at->addDays($interval->getUnit());
                break;
            case PlanInterval::MONTH:
                return $end_at->addMonths($interval->getUnit());
                break;
            case PlanInterval::YEAR:
                return $end_at->addYears($interval->getUnit());
                break;
            default:
                throw new SubscriptionErrorException(
                    'The interval \''.$interval->getType().'\' selected is not available.'
                );
                break;
        }
    }

    public function subscribeToInterval(PlanIntervalContract $interval): SubscriptionContact
    {
        if ($interval->plan->isDisabled()) {
            throw new SubscriptionErrorException(
                'This plan has been disabled, please subscribe to other plan.'
            );
        }

        if ($this->subscriptions()->unfinished()->count() >= 2) {
            throw new SubscriptionErrorException('You are changed to other plan previously');
        }

        $currentSubscription = $this->getActiveSubscription();
        $start_at = null;
        $end_at = null;

        if ($currentSubscription == null) {
            $start_at = now();
        } else {
            $start_at = $currentSubscription->getExpirationDate();
        }

        $end_at = $this->calculateExpireDate($start_at, $interval);

        $subscription = Subscription::make($interval->plan, $start_at, $end_at);
        $subscription = $this->subscriptions()->save($subscription);

        return $subscription;
    }

    public function changePlanTo(PlanContract $plan, PlanIntervalContract $interval = null)
    {
        if (! $this->hasActiveSubscription()) {
            throw new SubscriptionErrorException('You need a subscription for upgrade to other.');
        }

        if ($plan->hasManyIntervals() && $interval == null) {
            throw new SubscriptionErrorException('The plan has many intervals, please indicate a interval.');
        }

        if ($this->subscriptions()->unfinished()->count() >= 2) {
            throw new SubscriptionErrorException('You are changed to other plan previously');
        }

        $currentSubscription = $this->getActiveSubscription();
        $currentPlan = $currentSubscription->plan;
        $currentIntervalPrice = $currentPlan->isFree() ? 0.00 : $currentPlan->getInterval()->getPrice();

        $toInterval = $plan->getInterval();

        if ($currentPlan->id == $plan->id) {
            throw new SubscriptionErrorException('You can\'t change to same plan. You need change to other plan.');
        }

        if ($interval !== null) {
            $toInterval = $interval;
        }

        if ($currentIntervalPrice < $toInterval->getPrice()) {
            return $this->upgradeTo($toInterval);
        }

        return $this->downgradeTo($toInterval);
    }

    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->current()
            ->exists();
    }

    protected function upgradeTo(PlanIntervalContract $interval): SubscriptionContact
    {
        if (! $this->hasActiveSubscription()) {
            throw new SubscriptionErrorException('You need a subscription for upgrade to other.');
        }

        $this->forceUnsubscribe();

        return $this->subscribeToInterval($interval);
    }

    public function forceUnsubscribe()
    {
        $currentSubscription = $this->getActiveSubscription();
        if ($currentSubscription != null) {
            $currentSubscription->end_at = now()->subSecond();
            $currentSubscription->cancelled_at = now();
            $currentSubscription->save();
        }
    }

    protected function downgradeTo(PlanIntervalContract $interval): SubscriptionContact
    {
        if (! $this->hasActiveSubscription()) {
            throw new SubscriptionErrorException('You need a subscription for upgrade to other.');
        }

        return $this->subscribeToInterval($interval);
    }

    public function renewSubscription(PlanIntervalContract $interval = null)
    {
        if ($this->subscriptions()->unfinished()->count() >= 2) {
            throw new SubscriptionErrorException('You are changed to other plan previously');
        }

        $currentSubscription = $this->getActiveSubscription();

        if ($interval === null) {
            $plan = $currentSubscription->plan;

            if ($plan->hasManyIntervals()) {
                throw new SubscriptionErrorException(
                    'The plan you want will subscribe has many intervals, please consider renew to a interval of plan'
                );
            }

            $interval = $plan->intervals()->first();
        }

        $newExpireDate = $this->calculateExpireDate($currentSubscription->end_at, $interval);

        $currentSubscription->end_at = $newExpireDate;
        $currentSubscription->save();

        return $currentSubscription;
    }

    public function unsubscribe()
    {
        $currentSubscription = $this->getActiveSubscription();

        if (isset($currentSubscription)) {
            $currentSubscription->cancelled_at = now();
            $currentSubscription->save();
        }
    }

    public function abilityFor(string $featureCode)
    {
        if (! array_key_exists($featureCode, config('subscriptions.default_features.features'))) {
            throw new SubscriptionErrorException('The "'.$featureCode.'" is not available in the system.');
        }

        $defaultFeature = config('subscriptions.default_features.features.'.$featureCode);
        $activeSubscription = $this->getActiveSubscription();

        if ($activeSubscription == null) {
            return $defaultFeature;
        }

        $feature = $activeSubscription->plan->getFeatureByCode($featureCode);

        if ($feature == null) {
            return $defaultFeature;
        }

        return $activeSubscription->plan->getFeatureByCode($featureCode)->getValue();
    }

    public function abilitiesList()
    {
        $loadFeatures = config('subscriptions.default_features.features');
        $activeSubscription = $this->getActiveSubscription();

        if ($activeSubscription == null) {
            return $loadFeatures;
        }

        $features = $activeSubscription->plan->features;

        $features->each(function (PlanFeature $feature) use (&$loadFeatures) {
            $loadFeatures[$feature->getCode()] = $feature->getValue();
        });

        return $loadFeatures;
    }

    public function getConsumables()
    {
   		$currentSubscription = $this->getActiveSubscription();
		$planConsumables = array();
		
		$features = $currentSubscription->plan->features;
		
		if($features){
		
			$features = $features->toArray();
			
			# custom the returned data
			foreach( $features as $feature ){
			
				if($feature['is_consumable'] == 1){
					unset($feature['created_at']);
					unset($feature['updated_at']);
					$planConsumables[] = $feature;
				}
			
			}
		}
		
		return $planConsumables;
    }
    
    public function consumeFeature($code,$qty){
    
		$currentSubscription = $this->getActiveSubscription();
		$features = $currentSubscription->plan->features;
		
		$withdrawn = false;
		
		# if current plan has features
		if($features){
		
			# get array of features from result
			$features = $features->toArray();
			
			# check if feature code exists in current plan features list
			# array search works with multidimensional array-s as well 
			if(array_search($code, array_column($features, 'code')) !== false)
			{
			
				# get consumable feature data 
				$consumable = $currentSubscription->plan->getFeatureByCode($code);
				
				# if we have data
				if($consumable){

					# make sure we have something to withdraw from value a.k.a qty
					# and is not greater than our remaining qty
					if($qty <= $consumable->value){
					
						$final_qty = ($consumable->value - $qty);
						
						# make changes to value & save to db
						$consumable->value = $final_qty;
						$consumable->save();
						
						# consume successfull
						$withdrawn = true;
					}
				
				}
			
			}
		}
		
		return $withdrawn;
		
    	}

    /* 
    Check subscription free days 
    */
    public function SubsCheckFreeDays(){
    
	    $currentSubscription = $this->getActiveSubscription();
	    
	    $is_active = true; // default true
    
	    if($currentSubscription){
		    
		    # check if plan has free days setup 
		    if($currentSubscription->plan->free_days == 0){ 
		    // no days setup , still true is free
		    }else {
		           
		           # get plan intervals
		    	   $getSubscrPlanInterval = $currentSubscription->plan->intervals; 
		    	   
		    	   # if it has free days setup but plan has interval and price is 0
		    	   if($getSubscrPlanInterval && $getSubscrPlanInterval->price == 0){
		    	   	// no price setup , still true is free subscription
		    	   }
		    	   # calculate days since subscription , and compare against plan free days.
		    	   else {
		    	    
				    $subscription_start = $currentSubscription->start_at->toDateTimeString();
				    $start_date = Carbon::createFromFormat('Y-m-d h:i:s',$subscription_start);
				    $end_date = Carbon::createFromFormat('Y-m-d h:i:s',date('Y-m-d h:i:s',time()));
				    
				    # calculate days since subscription started until today
				    $daystotal_since_subscribed = $start_date->diffInDays($end_date);
				    
				    	if($daystotal_since_subscribed <= $currentSubscription->plan->free_days){
				    	// days since subscription untill today are lower or equal with plan free days 
				    	// so this is still active 			    	
				    	}else {
				    	$is_active = false;
				    	}
			    
			    }
		    
		    }
		    
	    }
	    
	    return $is_active;
    }
    
}
