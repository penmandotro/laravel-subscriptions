<?php

use Illuminate\Support\Str;

$factory->define(\PenMan\LaravelSubscriptions\Tests\Entities\User::class, function (Faker\Generator $faker) {
    return [
        'name' => $faker->name,
        'email' => $faker->unique()->safeEmail,
        'email_verified_at' => now(),
        'password' => '$2y$10$TKh8H1.PfQx37YgCzwiKb.KjNyWgaHb9cbcoQgdIVFlYg7B77UdFm', // secret
        'remember_token' => Str::random(10),
    ];
});

$factory->define(\PenMan\LaravelSubscriptions\Entities\Plan::class, function (Faker\Generator $faker) {
    return [
        'name'          => $faker->word,
        'description'   => $faker->sentence,
        'free_days'     => $faker->randomNumber(2),
        'is_enabled'    => $faker->boolean,
        'is_default'    => 0,
        'sort_order'    => $faker->randomNumber(2),
    ];
});
