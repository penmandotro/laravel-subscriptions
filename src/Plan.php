<?php

namespace BestDigital\LaravelSubscriptions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use PenMan\LaravelSubscriptions\Contracts\GroupContract;
use PenMan\LaravelSubscriptions\Contracts\PlanContract;
use PenMan\LaravelSubscriptions\Entities\Group;
use PenMan\LaravelSubscriptions\Exceptions\PlanErrorException;
use PenMan\LaravelSubscriptions\Traits\HasFeatures;
use PenMan\LaravelSubscriptions\Traits\HasConsumables;

abstract class Plan extends Model implements PlanContract
{
    use HasFeatures;
    use HasConsumables;

    protected $table = 'plans';

    protected $fillable = [
        'name', 'description', 'free_days', 'sort_order', 'is_enabled', 'is_default', 'group',
    ];

    /**
     * @param  string  $name
     * @param  string  $description
     * @param  int  $free_days
     * @param  int  $sort_order
     * @param  bool  $is_enabled
     * @param  bool  $is_default
     * @param  GroupContract|null  $group
     * @return Model|PlanContract
     */
    public static function create(
        string $name,
        string $description,
        int $free_days = 0,
        int $sort_order = 0,
        bool $is_enabled = false,
        bool $is_default = false,
        GroupContract $group = null
    ): PlanContract {
        $attributes = [
            'name'        => $name,
            'description' => $description,
            'free_days'   => $free_days,
            'sort_order'  => $sort_order,
            'is_enabled'  => $is_enabled,
            'is_default'  => $is_default,
            'group'       => $group,
        ];
        $calledClass = get_called_class();

        if (! self::defaultExists($group)) {
            $attributes['is_default'] = true;
        }

        $plan = new $calledClass($attributes);
        $plan->save();

        return $plan;
    }

    private static function defaultExists(GroupContract $group = null): bool
    {
        $calledClass = get_called_class();

        return $calledClass::query()
            ->byGroup($group)
            ->isDefault()
            ->exists();
    }

    public function scopeByGroup(Builder $q, GroupContract $group = null)
    {
        return $q->where('group', $group);
    }

    public function scopeIsDefault(Builder $q)
    {
        return $q->where('is_default', 1);
    }

    public function scopeEnabled(Builder $q)
    {
        return $q->where('is_enabled', 1);
    }

    public function scopeDisabled(Builder $q)
    {
        return $q->where('is_enabled', 1);
    }

    public function isDefault(): bool
    {
        return $this->is_default;
    }

    public function isEnabled(): bool
    {
        return $this->is_enabled;
    }

    public function isDisabled(): bool
    {
        return ! $this->is_enabled;
    }

    public function isFree(): bool
    {
        return $this->intervals()->count() == 0 || $this->intervals()->first()->price == 0;
    }

    public function intervals()
    {
        return $this->hasMany(config('subscriptions.entities.plan_interval'), 'plan_id')
            ->orderBy('price');
    }

    public function isNotFree(): bool
    {
        return $this->intervals()->count() > 0 && $this->intervals()->first()->price > 0;
    }

    public function hasManyIntervals(): bool
    {
        return $this->intervals()->count() > 1;
    }

    public function setFree()
    {
        $this->intervals()->delete();
    }

    public function setDefault()
    {
        $myGroup = $this->myGroup();
        $calledClass = get_called_class();

        $currentDefaults = $calledClass::query()
            ->byGroup($myGroup)
            ->isDefault()
            ->get();

        $currentDefaults->each(function ($plan) {
            $plan->is_default = false;
            $plan->save();
        });

        $this->is_default = true;
        $this->save();
    }

    public function myGroup(): ?GroupContract
    {
        return empty($this->group) ? null : new Group($this->group);
    }

    public function changeToGroup(GroupContract $group): void
    {
        $this->group = $group;

        if (! self::defaultExists($group)) {
            $this->is_default = true;
        }

        $this->save();
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function delete()
    {
        if ($this->subscriptions()->exists() > 0) {
            throw new PlanErrorException('You cannot delete this plan because this has subscriptions.');
        }

        return parent::delete();
    }

    public function subscriptions()
    {
        return $this->hasMany(config('subscriptions.entities.plan_subscription'));
    }

    public function consumables()
    {
        return $this->hasMany(config('subscriptions.entities.plan_consumable_features'));
    }
    
}
