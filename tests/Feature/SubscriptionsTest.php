<?php

namespace PenMan\LaravelSubscriptions\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PenMan\LaravelSubscriptions\Entities\Plan;
use PenMan\LaravelSubscriptions\Entities\PlanFeature;
use PenMan\LaravelSubscriptions\Entities\PlanInterval;
use PenMan\LaravelSubscriptions\Entities\Subscription;
use PenMan\LaravelSubscriptions\Exceptions\SubscriptionErrorException;
use PenMan\LaravelSubscriptions\Tests\Entities\PlanManyIntervals;
use PenMan\LaravelSubscriptions\Tests\Entities\User;
use PenMan\LaravelSubscriptions\Tests\TestCase;

class SubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    protected $now;

    public function __construct($name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->now = Carbon::createFromFormat('Y-m-d H:i:s', '2019-10-20 00:00:00');
    }

    /** @test */
    public function a_user_want_to_subscribe_to_a_disabled_plan()
    {
        $user = factory(User::class)->create();
        $plan = factory(Plan::class)->create(['is_enabled' => false]);

        $this->expectException(SubscriptionErrorException::class);
        $user->subscribeTo($plan);
    }

    /** @test */
    public function a_user_want_to_subscribe_to_a_interval_of_a_disabled_plan()
    {
        $user = factory(User::class)->create();
        $plan = factory(Plan::class)->create(['is_enabled' => false]);
        $interval = $plan->setInterval(PlanInterval::make(PlanInterval::MONTH, 3, 90));

        $this->expectException(SubscriptionErrorException::class);

        $user->subscribeToInterval($interval);
    }

    /** @test */
    public function a_user_can_subscribe_to_a_perpetual_plan()
    {
        Carbon::setTestNow($this->now);

        $user = factory(User::class)->create();
        $plan = factory(Plan::class)->create(['is_enabled' => true]);

        $subscription = $user->subscribeTo($plan);

        // when plan is free and the plan has't intervals
        $this->assertTrue($plan->isFree());
        $this->assertTrue($plan->intervals()->count() === 0);

        $this->assertDatabaseHas((new Subscription())->getTable(), [
            'plan_id' => $plan->id,
            'subscriber_type' => User::class,
            'subscriber_id' => $user->id,
            'start_at' => $this->now,
            'end_at' => null,
        ]);

        // the subscription is perpetual
        $this->assertTrue($subscription->isPerpetual());
        $this->assertEquals(0, $subscription->getElapsedDays());
        $this->assertEquals(null, $subscription->getDaysLeft());
        $this->assertEquals($this->now, $subscription->getStartDate());
        $this->assertEquals(null, $subscription->getExpirationDate());
        $this->assertEquals($user->id, $subscription->subscriber->id);

        // when plan has price but not interval
        $otherUser = factory(User::class)->create();
        $otherPlan = factory(Plan::class)->create();

        $otherPlan->setInterval(PlanInterval::makeInfinite(300.00));

        $subscription = $otherUser->subscribeTo($plan);

        $this->assertTrue($subscription->isPerpetual());
        $this->assertEquals(0, $subscription->getElapsedDays());
        $this->assertEquals(null, $subscription->getDaysLeft());
    }

    /** @test */
    public function user_can_subscribe_to_plan_with_interval()
    {
        Carbon::setTestNow($this->now);

        $user = factory(User::class)->create();
        $plan = factory(Plan::class)->create(['is_enabled' => true]);

        //when plan has one interval
        $interval = PlanInterval::make(PlanInterval::MONTH, 1, 4.90);
        $plan->setInterval($interval);

        $this->assertTrue($plan->isNotFree());

        $this->assertNotTrue($plan->hasManyIntervals());

        $subscription = $user->subscribeTo($plan);

        $this->assertDatabaseHas((new Subscription())->getTable(), [
            'plan_id' => $plan->id,
            'subscriber_type' => User::class,
            'subscriber_id' => $user->id,
            'start_at' => now()->toDateTimeString(),
            'end_at' => now()->addMonth($interval->getUnit())->toDateTimeString(),
        ]);

        $this->assertNotTrue($subscription->isPerpetual());
        $this->assertEquals(0, $subscription->getElapsedDays());
        $this->assertEquals(31, $subscription->getDaysLeft());
    }

    /** @test */
    public function user_can_subscribe_to_plan_with_many_intervals()
    {
        Carbon::setTestNow($this->now);

        $user = factory(User::class)->create();

        //set config, a plan with intervals
        $this->app['config']->set('subscriptions.entities.plan', PlanManyIntervals::class);

        $plan = PlanManyIntervals::create(
            'name of plan',
            'this is a description',
            0,
            1,
            true
        );

        $intervals = [
            PlanInterval::make(PlanInterval::MONTH, 1, 4.90),
            PlanInterval::make(PlanInterval::MONTH, 3, 11.90),
            PlanInterval::make(PlanInterval::YEAR, 1, 49.90),
        ];

        $plan->setIntervals($intervals);

        // it's Subscribing
        $user->subscribeTo($intervals[1]); // 3 months for 4.90

        $this->assertDatabaseHas((new Subscription())->getTable(), [
            'plan_id' => $plan->id,
            'subscriber_type' => User::class,
            'subscriber_id' => $user->id,
            'start_at' => now()->toDateTimeString(),
            'end_at' => now()->addMonths($intervals[1]->getUnit())->toDateTimeString(),
        ]);
    }

    /** @test */
    public function user_can_renew_his_subscription()
    {
        $dayOfObtainPlan = $this->now;
        Carbon::setTestNow($dayOfObtainPlan);

        $user = factory(User::class)->create();
        $plan = factory(Plan::class)->create(['is_enabled' => true]);

        $interval = PlanInterval::make(PlanInterval::MONTH, 1, 4.90);
        $plan->setInterval($interval);

        // it's subscribing
        $user->subscribeToPlan($plan);

        $subscriptionData = [
            'plan_id' => $plan->id,
            'subscriber_type' => User::class,
            'subscriber_id' => $user->id,
            'start_at' => now()->toDateTimeString(),
            'end_at' => now()->addMonths(1)->toDateTimeString(),
        ];

        $this->assertDatabaseHas((new Subscription())->getTable(), $subscriptionData);

        // 20 days later;
        Carbon::setTestNow(now()->addDays(20));

        // renew subscription
        $user->renewSubscription();
        $subscriptionData['end_at'] = $dayOfObtainPlan->addMonths(2)->toDateTimeString();

        $this->assertDatabaseHas((new Subscription())->getTable(), $subscriptionData);
    }

    /** @test */
    public function an_user_change_plan_after_renew_subscription()
    {
        $dayOfObtainPlan = $this->now;
        Carbon::setTestNow($dayOfObtainPlan);

        $user = factory(User::class)->create();
        $firstPlan = factory(Plan::class)->create(['is_enabled' => true]);
        $firstPlan->setInterval(PlanInterval::make(PlanInterval::MONTH, 1, 100));

        $secondPlan = factory(Plan::class)->create(['is_enabled' => true]);
        $secondPlan->setInterval(PlanInterval::make(PlanInterval::MONTH, 1, 50));

        $user->subscribeTo($firstPlan);
        $subscription = $user->renewSubscription();

        $this->assertEquals(now()->addMonths(2), $subscription->refresh()->getExpirationDate());

        $subscriptionChanged = $user->changePlanTo($secondPlan);

        $this->assertEquals(now()->addMonths(3), $subscriptionChanged->refresh()->getExpirationDate());

        $this->expectException(SubscriptionErrorException::class);

        $user->renewSubscription();
    }

    /** @test */
    public function user_can_change_plan()
    {
        Carbon::setTestNow($this->now);

        $user = factory(User::class)->create();
        $freePlan = factory(Plan::class)->create(['is_enabled' => true]);

        $firstPlan = factory(Plan::class)->create(['is_enabled' => true]);
        $firstPlan->setInterval(PlanInterval::make(PlanInterval::MONTH, 1, 4.90));

        $secondPlan = factory(Plan::class)->create(['is_enabled' => true]);
        $secondPlan->setInterval(PlanInterval::make(PlanInterval::YEAR, 1, 39.00));

        $this->assertNotTrue($user->hasActiveSubscription());

        // it's subscribing to free plan
        $subscription = $user->subscribeTo($freePlan);
        $this->assertTrue($user->hasActiveSubscription());

        // 20 days later;
        Carbon::setTestNow(now()->addDays(20));

        // it's perpetual subscription because the plan has not a interval
        $this->assertEquals(null, $subscription->getExpirationDate());
        // it's upgrade plan because first plan has a higher price
        $subscriptionUpgraded = $user->changePlanTo($secondPlan);

        $this->assertEquals(now()->subSecond(), $subscription->refresh()->getExpirationDate());
        $this->assertEquals(now()->addYear(), $subscriptionUpgraded->getExpirationDate());
        $this->assertEquals($secondPlan->id, $user->getActiveSubscription()->plan->id);

        //when downgrade plan
        $subscriptionDowngraded = $user->changePlanTo($firstPlan);

        $this->assertEquals(now()->addYear(), $subscriptionUpgraded->refresh()->getExpirationDate());
        $this->assertEquals(now()->addYear(), $subscriptionDowngraded->getStartDate());
        $this->assertEquals($secondPlan->id, $user->getActiveSubscription()->plan->id);

        Carbon::setTestNow(now()->addYear()->addSecond());
        $this->assertEquals($firstPlan->id, $user->getActiveSubscription()->plan->id);
    }

    /** @test */
    public function user_can_cancel_his_subscription()
    {
        Carbon::setTestNow($this->now);

        $user = factory(User::class)->create();
        $freePlan = factory(Plan::class)->create(['is_enabled' => true]);
        $firstPlan = factory(Plan::class)->create(['is_enabled' => true]);
        $firstPlan->setInterval(PlanInterval::make(PlanInterval::MONTH, 1, 4.90));

        $this->assertNotTrue($user->hasActiveSubscription());
        $user->subscribeToPlan($freePlan);
        $this->assertTrue($user->hasActiveSubscription());

        // For free plans, you must be cancel with forceUnsubscribe()

        $user->forceUnsubscribe();
        $this->assertNotTrue($user->hasActiveSubscription());

        $user->subscribeToPlan($firstPlan);
        $this->assertTrue($user->hasActiveSubscription());

        $user->unsubscribe();

        // the subscription are active while not expire but will cancel in expire date
        Carbon::setTestNow(now()->addDays(30));
        $this->assertTrue($user->hasActiveSubscription());

        Carbon::setTestNow(now()->addDays(1)->addSecond());
        $this->assertNotTrue($user->hasActiveSubscription());
    }

    /** @test */
    public function a_user_has_plans_and_is_avility_for_many_features()
    {
        $this->app['config']->set('subscriptions.default_features.features.is_featured_clinic', false);
        $this->app['config']->set('subscriptions.default_features.features.has_promotion', true);

        $user = factory(User::class)->create();

        // The user has not a plan
        $this->assertEquals(false, $user->abilityFor('is_featured_clinic'));
        $this->assertEquals(true, $user->abilityFor('has_promotion'));

        $firstPlan = factory(Plan::class)->create(['is_enabled' => true]);
        $firstPlan->addFeature(PlanFeature::make('is_featured_clinic', true));

        $user->subscribeTo($firstPlan);

        $this->assertEquals(true, $user->abilityFor('is_featured_clinic'));
        $this->assertEquals(true, $user->abilityFor('has_promotion'));

        $firstPlan->addFeature(PlanFeature::make('has_promotion', false));

        $this->assertEquals(0, $user->abilityFor('has_promotion')); //TODO change to strict false
    }

    /** @test */
    public function a_user_can_obtain_list_of_his_avilities()
    {
        $this->app['config']->set('subscriptions.default_features.features', [
            'is_featured_clinic' => false,
            'has_promotion' => true,
            'other_feature' => 5,
        ]);

        $user = factory(User::class)->create();

        $this->assertEquals([
            'is_featured_clinic' => false,
            'has_promotion' => true,
            'other_feature' => 5,
        ], $user->abilitiesList());

        $firstPlan = factory(Plan::class)->create(['is_enabled' => true]);
        $firstPlan->addFeature(PlanFeature::make('is_featured_clinic', true));
        $firstPlan->addFeature(PlanFeature::make('has_promotion', false));

        $user->subscribeTo($firstPlan);

        $this->assertEquals([
            'is_featured_clinic' => 1,
            'has_promotion' => 0,
            'other_feature' => 5,
        ], $user->abilitiesList());
    }
}
