<?php

namespace PenMan\LaravelSubscriptions\Entities;

use PenMan\LaravelSubscriptions\Plan as PlanBase;
use PenMan\LaravelSubscriptions\Traits\HasSingleInterval;

class Plan extends PlanBase // extends model
{
    use HasSingleInterval;
}
