<?php

namespace BestDigital\LaravelSubscriptions\Traits;

use BestDigital\LaravelSubscriptions\Contracts\PlanIntervalContract;

trait HasManyIntervals
{
    public function setIntervals(array $intervals)
    {
        $this->intervals()->delete();
        $this->intervals()->saveMany($intervals);
    }

    public function addInterval(PlanIntervalContract $interval)
    {
        $this->intervals()->save($interval);
    }
}
