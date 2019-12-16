<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\ActivityTicket;
use Faker\Generator as Faker;

$factory->define(ActivityTicket::class, function (Faker $faker) {
    return [
        'activity_id' => DB::select('id')->from('activities')->inRandomOrder()->first(),

        'name' => $faker->sentence,
        'description' => $faker->sentence,

        'for_member' => $faker->boolean,
        'for_guest' => $faker->boolean
    ];
});
