<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\ActivityTicketPayment;
use App\Models\Activity;
use Faker\Generator as Faker;

$factory->define(ActivityTicketPayment::class, function (Faker $faker) {
    return [
        'activity_ticket_id' => DB::select('id')->from('activity_tickets')->inRandomOrder()->first(),

        'name' => $faker->sentence,
        'statement' => $faker->optional()->word,
        'description' => $faker->sentence,

        'for_member' => $faker->boolean,
        'for_guest' => $faker->boolean,

        'payment_type' => $faker->randomElement([
            Activity::PAYMENT_TYPE_INTENT,
            Activity::PAYMENT_TYPE_BILLING,
        ]),

        'price' => $price = $faker->numberBetween(250, 4000),
        'total_price' => $faker->numberBetween($price, max($price * 0.025, $price + 0.40))
    ];
});
