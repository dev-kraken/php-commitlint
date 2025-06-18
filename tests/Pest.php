<?php

declare(strict_types=1);

use DevKraken\PhpCommitlint\Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

uses(TestCase::class)->in(__DIR__);

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
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the amount of code you need to type in your tests.
|
*/

function createCommitMessage(string $message): string
{
    return $message;
}

function createConfig(array $overrides = []): array
{
    $default = [
        'auto_install' => false,
        'rules' => [
            'type' => [
                'required' => true,
                'allowed' => ['feat', 'fix', 'docs', 'style', 'refactor', 'test', 'chore'],
            ],
            'scope' => [
                'required' => false,
                'allowed' => [],
            ],
            'subject' => [
                'min_length' => 1,
                'max_length' => 100,
                'case' => 'any',
                'end_with_period' => false,
            ],
        ],
    ];

    return mergeTestConfig($default, $overrides);
}

function mergeTestConfig(array $default, array $custom): array
{
    $result = $default;

    foreach ($custom as $key => $value) {
        if (isset($result[$key]) && is_array($result[$key]) && is_array($value) && !isSequentialArray($value)) {
            $result[$key] = mergeTestConfig($result[$key], $value);
        } else {
            $result[$key] = $value;
        }
    }

    return $result;
}

function isSequentialArray(array $array): bool
{
    if (empty($array)) {
        return true;
    }

    return array_keys($array) === range(0, count($array) - 1);
}
