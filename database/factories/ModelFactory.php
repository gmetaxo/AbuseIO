<?php

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| Here you may define all of your model factories. Model factories give
| you a convenient way to create models for testing and seeding your
| database. Just tell the factory how a default model should look.
|
*/



$factory->define(AbuseIO\Models\Account::class, function (Faker\Generator $faker) {
    return [
        'name' => $faker->name,
        'description' => $faker->sentence(rand(6, 10)),
        'disabled' =>  rand(0, 1),
        'systemaccount' => 0,
        'brand_id' => 1,
    ];
});

$factory->define(AbuseIO\Models\Brand::class, function (Faker\Generator $faker) {
    return [
        'name' => $faker->name,
        'company_name' => $faker->company,
        'logo' => \AbuseIO\Models\Brand::getDefaultLogo(),
        'introduction_text' => $faker->realText(),
        'creator_id' => 1,
    ];
});

$factory->define(AbuseIO\Models\User::class, function (Faker\Generator $faker) {
    return [
        'name' => $faker->name,
        'email' => $faker->email,
        'password' => bcrypt(str_random(10)),
        'remember_token' => str_random(10),
    ];
});

$factory->define(AbuseIO\Models\Netblock::class, function (Faker\Generator $faker) {
    $first_ip = $faker->ipv4;
    $last_ip = long2ip(ip2long($first_ip) + rand(1, 100));

    return [
        'contact_id' => \AbuseIO\Models\Contact::all()->first()->id,
        'first_ip' => $first_ip,
        'last_ip' => $last_ip,
        'description' => $faker->sentence(rand(6, 24)),
        'enabled' => $faker->boolean(),
    ];
});





