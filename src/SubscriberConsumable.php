<?php

namespace PenMan\LaravelSubscriptions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

abstract class SubscriberConsumable extends Model
{
    protected $table = 'subscription_consumables';
    
    protected $fillable = [
        'plan_feature_code','subscription_id','available','used',
    ];

    public function getAvailable()
    {
        return $this->available;
    }

    public function getUsed()
    {
        return $this->used;
    }

    public function getCode(): string
    {
        return $this->plan_feature_code;
    }
}
