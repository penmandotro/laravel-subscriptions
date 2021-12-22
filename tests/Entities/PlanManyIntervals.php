<?php

namespace PenMan\LaravelSubscriptions\Tests\Entities;

use PenMan\LaravelSubscriptions\Plan;
use PenMan\LaravelSubscriptions\Traits\HasManyIntervals;

class PlanManyIntervals extends Plan
{
    use HasManyIntervals;
}
