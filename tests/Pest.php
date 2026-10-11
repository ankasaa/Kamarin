<?php

use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Frozen Clock Helper
|--------------------------------------------------------------------------
|
| Business rules depend on "today", expiry deadlines, and no-show cutoffs
| (docs/rencana_pengujian.md section 2: "fixed now"). Call freezeNow() inside
| a test to pin Carbon's clock. The clock is reset automatically before and
| after every test so one test cannot leak its time into another.
|
| freezeNow();                  // frozen at 2026-01-01 12:00:00 (app timezone)
| freezeNow('2026-06-15 10:00:00');
| freezeNow(Carbon::parse('2026-06-15'));
|
*/

function freezeNow(string|Carbon $now = '2026-01-01 12:00:00'): Carbon
{
    $frozen = $now instanceof Carbon ? $now->copy() : Carbon::parse($now);

    Carbon::setTestNow($frozen);

    return $frozen;
}

// Global hooks must be registered through the `pest()` configuration object
// (https://pestphp.com/docs/global-hooks). They reset the frozen clock so one
// test can never leak its time into the next one.
pest()
    ->beforeEach(function () {
        Carbon::setTestNow();
    })
    ->afterEach(function () {
        Carbon::setTestNow();
    });

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
