<?php

namespace PenMan\LaravelSubscriptions\Contracts;

use Illuminate\Database\Eloquent\Model;

interface PlanContract
{
    /**
     * @param  string  $name
     * @param  string  $description
     * @param  int  $free_days
     * @param  int  $sort_order
     * @param  bool  $is_active
     * @param  bool  $is_default
     * @param  GroupContract|null  $group
     * @return Model|PlanContract
     */
    public static function create(
        string $name,
        string $description,
        int $free_days,
        int $sort_order,
        bool $is_active = false,
        bool $is_default = false,
        GroupContract $group = null
    ): self;

    public function intervals();

    public function isDefault(): bool;

    public function isEnabled(): bool;

    public function isFree(): bool;

    public function isNotFree(): bool;

    public function hasManyIntervals(): bool;

    public function subscriptions();

    public function consumables();

    public function setDefault();

    public function myGroup(): ?GroupContract;

    public function changeToGroup(GroupContract $group): void;
}
