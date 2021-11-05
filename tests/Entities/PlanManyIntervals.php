<?php

namespace BestDigital\LaravelSubscriptions\Tests\Entities;

use BestDigital\LaravelSubscriptions\Plan;
use BestDigital\LaravelSubscriptions\Traits\HasManyIntervals;

class PlanManyIntervals extends Plan
{
    use HasManyIntervals;
}
