<?php

namespace PenMan\LaravelSubscriptions\Tests\Feature;

use PenMan\LaravelSubscriptions\Entities\Plan;
use PenMan\LaravelSubscriptions\Entities\PlanInterval;
use PenMan\LaravelSubscriptions\Exceptions\IntervalErrorException;
use PenMan\LaravelSubscriptions\Tests\TestCase;

class PlanIntervalTest extends TestCase
{
    /** @test */
    public function send_error_exception_when_interval_range_is_not_available()
    {
        $this->expectException(IntervalErrorException::class);

        PlanInterval::make(
            'foo',
            30,
            4.99
        );
    }

    /** @test */
    public function it_can_create_interval_for_plans()
    {
        // Make Interval
        $interval = PlanInterval::make(
            PlanInterval::DAY,
            30,
            4.99
        );
        $plan = factory(Plan::class)->create();
        $plan->setInterval($interval);

        $this->assertEquals($plan->id, $interval->plan->id);
        $this->assertEquals(PlanInterval::DAY, $interval->getType());
        $this->assertEquals(30, $interval->getUnit());
        $this->assertEquals(4.99, $interval->getPrice());
        $this->assertNotTrue($interval->isInfinite());
        $this->assertTrue($interval->isNotFree());

        // Interval Free
        $interval = PlanInterval::make(
            PlanInterval::DAY,
            30,
            0
        );

        $this->assertTrue($interval->isFree());

        // Infinity Interval Free
        $interval = PlanInterval::makeInfinite(
            0
        );
        $this->assertTrue($interval->isInfinite());
        $this->assertTrue($interval->isFree());

        // Infinity Interval Not Free
        $interval = PlanInterval::makeInfinite(
            50.00
        );
        $this->assertTrue($interval->isInfinite());
        $this->assertTrue($interval->isNotFree());
    }
}
