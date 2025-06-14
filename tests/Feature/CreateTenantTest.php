<?php

test('Should create a tenant', function () {
    $faker = \Faker\Factory::create();
    test()->artisan("orchestra:create $faker->word --domain=$faker->domainName --driver=sqlite")->assertExitCode(0);
});

test('Should delete a tenant', function () {
    $faker = \Faker\Factory::create();
    test()->artisan("orchestra:delete $faker->word --driver=sqlite")->assertExitCode(0);
});

test('Should update a tenant', function () {
    $faker = \Faker\Factory::create();
    $name  = $faker->word;
    test()->artisan("orchestra:create $name --domain=$faker->domainName --driver=sqlite")->assertExitCode(0);
    test()->artisan("orchestra:update $name --by=$faker->word --driver=sqlite")->assertExitCode(0);
});
