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
use BestDigital\LaravelSubscriptions\Entities\SubscriberConsumable;
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
        }else {
            return $this->subscribeToInterval($planOrInterval);
        }
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
	
	# make sure subscribe to consumables when subscribe to plan
	if($plan->features){
		foreach($plan->features as $plan_feature){
			if($plan_feature->is_consumable == 1){
				$subscription_consumable = SubscriberConsumable::make($plan_feature, $subscription, $plan_feature->value, $start_at, $end_at);
			        $subscription_consumable = $subscription_consumable->save($subscription_consumable->toArray());
		        }
		}
	}

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
        
        
        # make sure subscribe to consumables when subscribe to plan
	if($interval->features){
		foreach($interval->features as $interval_feature){
			if($interval_feature->is_consumable == 1){
				$subscription_consumable = SubscriberConsumable::makeInterval($interval_feature, $subscription, $interval_feature->value, $start_at, $end_at);
			        $subscription_consumable = $subscription_consumable->save($subscription_consumable->toArray());
		        }
		}
	}
	

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

    public function upgradeTo($planOrInterval): SubscriptionContact
    {
        if (! $this->hasActiveSubscription()) {
            throw new SubscriptionErrorException('You need a subscription for upgrade to other.');
        }

        $this->forceUnsubscribe();
        
        if ($planOrInterval instanceof PlanContract) {
            return $this->subscribeToPlan($planOrInterval);
        }else {
            return $this->subscribeToInterval($planOrInterval);
        }
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

    public function getPlanSubscribedConsumables()
    {
   		$currentSubscription = $this->getActiveSubscription();
		$planConsumables = array();
		
		$consumables = $currentSubscription->getAllConsumableSubscriptions();
		if($consumables){
		
			$consumables = $consumables->toArray();
			# custom the returned data
			foreach( $consumables as $consumable ){
					unset($consumable['created_at']);
					unset($consumable['updated_at']);
					$planConsumables[] = $consumable;
			}
		}
		
		return $planConsumables;
    }
    
    public function getCurrentPlanConsumables()
    {
   		$currentSubscription = $this->getActiveSubscription();
		$planConsumables = array();
		
		$consumables = $currentSubscription->plan->consumables;
		
		if($consumables){
		
			$consumables = $consumables->toArray();
			
			# custom the returned data
			foreach( $consumables as $consumable ){
			
				if($consumable['is_consumable'] == 1){
					unset($consumable['created_at']);
					unset($consumable['updated_at']);
					$planConsumables[] = $consumable;
				}
			
			}
		}
		
		return $planConsumables;
    }
    
    public function consumeFeature($code,$qty){
    
		$currentSubscription = $this->getActiveSubscription();
	
		# get user consumable by code for current active subscriptions
		# return first()
		$consumable = $currentSubscription->getConsumableSubscriptions($code);
		
		#$consumable_get = SubscriberConsumable::where("plan_feature_code",$code)->first();
		
		$withdrawn = false;
		
		if($consumable){
		
			# convert to array of data from result
			$consumable = $consumable->toArray();
			
			if(!empty($consumable))
			{
				# get plan consumable feature data 
				#$consumable = $currentSubscription->plan->getConsumableByCode($code);

				# make sure we have something to withdraw from value a.k.a qty
				# and is not greater than our remaining qty
				if($qty <= $consumable['available']){
				
					$final_qty = ($consumable['available'] - $qty);
					
					# query consumable for change based on feature id
					$change_consumable = SubscriberConsumable::find($consumable['plan_feature_id'])->first();
					# make changes to value & save to db
					$change_consumable->available = $final_qty;
					$change_consumable->used = $change_consumable->used + $qty;
					$change_consumable->save();
					
					# consume successfull
					$withdrawn = true;
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
	   $is_active = false; // default false
    
           # get plan intervals
    	   $getSubscrPlanInterval = $currentSubscription->plan->intervals; 
    	   
    	   # if plan has interval , is plan interval.
    	   if($getSubscrPlanInterval){
    	   
	    	   # if it has free days setup but plan has interval price 0
	    	   if($currentSubscription->plan->free_days != 0 && $getSubscrPlanInterval->price<=0){
	    	   	// no price setup , true is free subscription
	    	   	$is_active = true;
	    	   }
	    	   else {
	    	    	
	    	    	# if it has free days setup and price is not 0 
	    	    	# calculate days since subscription , and compare against plan free days.
	    	    	if($currentSubscription->plan->free_days != 0 && $getSubscrPlanInterval->price > 0){
	    	    	
			    $subscription_start = $currentSubscription->start_at->toDateTimeString();
			    $start_date = Carbon::createFromFormat('Y-m-d h:i:s',$subscription_start);
			    $end_date = Carbon::createFromFormat('Y-m-d h:i:s',date('Y-m-d h:i:s',time()));
			    
			    # calculate days since subscription started until today
			    $daystotal_since_subscribed = $start_date->diffInDays($end_date);
			    
			    	if($daystotal_since_subscribed <= $currentSubscription->plan->free_days){
			    	// days since subscription untill today are lower or equal with plan free days 
			    	// true is free subscription 	
			    	$is_active = true;		    	
			    	}
			    	
			    }
		    
		    }
    		}

	    return $is_active;
    }
    
}
