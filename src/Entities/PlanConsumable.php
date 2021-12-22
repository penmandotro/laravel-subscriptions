<?php

namespace PenMan\LaravelSubscriptions\Entities;

use Illuminate\Database\Eloquent\Model;
use PenMan\LaravelSubscriptions\PlanFeature as PlanFeatureBase;

class PlanConsumable extends PlanFeatureBase
{
    protected $attributes = [
        'is_consumable' => true,
    ];

    public static function make(
        string $code,
        int $value,
        int $sortOrder = null
    ): Model {
        $attributes = [
            'code' => $code,
            'value' => $value,
            'sort_order' => $sortOrder,
        ];

        return new self($attributes);
    }
}
