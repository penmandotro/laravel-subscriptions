<?php

namespace BestDigital\LaravelSubscriptions\Entities;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use BestDigital\LaravelSubscriptions\Contracts\PlanContract;
use BestDigital\LaravelSubscriptions\Contracts\SubscriptionContact;
use BestDigital\LaravelSubscriptions\Exceptions\SubscriptionErrorException;
use BestDigital\LaravelSubscriptions\Entities\SubscriberConsumable;

class Subscription extends Model implements SubscriptionContact
{
    protected $table = 'subscriptions';

    protected $fillable = [
        'plan_id', 'start_at', 'end_at',
    ];

    protected $dates = [
        'start_at', 'end_at',
    ];

    public static function make(PlanContract $plan, Carbon $start_at, Carbon $end_at = null): Model
    {
        if (! $plan instanceof Model) {
            throw new SubscriptionErrorException('$plan must be '.Model::class);
        }

        return new self([
            'plan_id' => $plan->id,
            'start_at' => $start_at,
            'end_at' => $end_at,
        ]);
    }

    public function scopeCurrent(Builder $q)
    {
        $today = now();

        return $q->where('start_at', '<=', $today)
            ->where(function ($query) use ($today) {
                $query->where('end_at', '>=', $today)->orWhereNull('end_at');
            });
    }

    public function scopeUnfinished(Builder $q)
    {
        $today = now();

        return $q->where(function ($query) use ($today) {
            $query->where('end_at', '>=', $today)->orWhereNull('end_at');
        });
    }

    public function getDaysLeft(): ?int
    {
        if ($this->isPerpetual()) {
            return null;
        }

        return now()->diffInDays($this->end_at);
    }

    public function isPerpetual(): bool
    {
        return $this->end_at == null;
    }

    public function getElapsedDays(): int
    {
        return now()->diffInDays($this->start_at);
    }

    public function getExpirationDate(): ?Carbon
    {
        return $this->end_at;
    }

    public function getStartDate(): Carbon
    {
        return $this->start_at;
    }
    
    public function getAllConsumableSubscriptions() {
    	
    	# select consumable subscription bassed on feature code
    	# qry for current user id & user current plan id
    	$subAllConsumable = self::select(
    			'subscr.subscriber_id',
    			'subscns.subscription_id',
    			'subscr.start_at as subscription_start_at',
    			'subscr.end_at as subscription_end_at',
    			'subscr.cancelled_at as subscription_cancelled_at',
    			'subscr.plan_id',
    			'subscns.id as plan_feature_id',
    			'subscns.plan_feature_code',
    			'subscns.available',
    			'subscns.used',
    			'subscns.created_at',
    			'subscns.updated_at'
    			)->from('subscriptions as subscr')
    			->join('subscription_consumables as subscns', 'subscns.subscription_id', '=', 'subscr.id')
    			->join('plans as plns', 'plns.id', '=', 'subscr.plan_id')
			->where('plns.id', '=', $this->plan->id)
			->where('subscr.subscriber_id', '=', $this->subscriber->id)
			->where('plns.is_enabled', '=', '1')
			->get();
	            	
	            	#vsprintf(str_replace(['?'], ['\'%s\''], $subConsumable->toSql()), $subConsumable->getBindings())
	            	
    	return $subAllConsumable;
    
    }
    
    public function getConsumableSubscriptions(string $plan_feature_code='') {
    	
    	# select consumable subscription bassed on feature code
    	# qry for current user id & user current plan id
    	$subConsumable = self::select(
    			'subscr.subscriber_id',
    			'subscns.subscription_id',
    			'subscr.start_at as subscription_start_at',
    			'subscr.end_at as subscription_end_at',
    			'subscr.cancelled_at as subscription_cancelled_at',
    			'subscr.plan_id',
    			'subscns.id as plan_feature_id',
    			'subscns.plan_feature_code',
    			'subscns.available',
    			'subscns.used',
    			'subscns.created_at',
    			'subscns.updated_at'
    			)->from('subscriptions as subscr')
    			->join('subscription_consumables as subscns', 'subscns.subscription_id', '=', 'subscr.id')
    			->join('plans as plns', 'plns.id', '=', 'subscr.plan_id')
			->where('plns.id', '=', $this->plan->id)
			->where('subscr.subscriber_id', '=', $this->subscriber->id)
			->where('subscns.plan_feature_code', '=', $plan_feature_code)
			->where('plns.is_enabled', '=', '1')
			->first();
	            	
	            	#vsprintf(str_replace(['?'], ['\'%s\''], $subConsumable->toSql()), $subConsumable->getBindings())
	            	
    	return $subConsumable;
    
    }

    public function subscriber()
    {
        return $this->morphTo();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(config('subscriptions.entities.plan'));
    }
    
}
