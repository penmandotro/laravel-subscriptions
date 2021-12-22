<?php

namespace PenMan\LaravelSubscriptions\Contracts;

interface GroupContract
{
    public function getCode(): string;

    public function plans();

    public function addPlan(PlanContract $plan): void;

    public function addPlans(array $plans): void;

    public function getDefaultPlan(): ?PlanContract;

    public function getEnabledPlans();

    public function hasPlans(): bool;

    public function __toString(): string;
}
