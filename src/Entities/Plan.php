<?php

namespace BestDigital\LaravelSubscriptions\Entities;

use BestDigital\LaravelSubscriptions\Plan as PlanBase;
use BestDigital\LaravelSubscriptions\Traits\HasSingleInterval;

class Plan extends PlanBase // extends model
{
    use HasSingleInterval;
}
