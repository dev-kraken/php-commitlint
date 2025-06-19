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

expect()->extend('toBeValidCommitMessage', function () {
    return $this->toMatchRegex('/^[a-z]+(\([^)]+\))?: .+/');
});

expect()->extend('toBeExitCode', function (int $expectedCode) {
    return $this->toBe($expectedCode);
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

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
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
            'body' => [
                'max_line_length' => 100,
                'leading_blank' => true,
            ],
            'footer' => [
                'leading_blank' => true,
            ],
        ],
        'patterns' => [
            'breaking_change' => '/^BREAKING CHANGE:/',
            'issue_reference' => '/(?:close[sd]?|fix(?:e[sd])?|resolve[sd]?)\\s+#\\d+/i',
        ],
    ];

    return mergeTestConfig($default, $overrides);
}

function createTempDirectory(): string
{
    $tempDir = sys_get_temp_dir() . '/php-commitlint-test-' . uniqid('', true);
    if (!mkdir($tempDir, 0o755, true) && !is_dir($tempDir)) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', $tempDir));
    }

    return $tempDir;
}

function createTempGitRepo(): string
{
    $tempDir = createTempDirectory();
    if (!mkdir($concurrentDirectory = $tempDir . '/.git', 0o755, true) && !is_dir($concurrentDirectory)) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
    }
    if (!mkdir($concurrentDirectory = $tempDir . '/.git/hooks', 0o755, true) && !is_dir($concurrentDirectory)) {
        throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
    }

    return $tempDir;
}

function createTempFile(string $content, string $suffix = '.tmp'): string
{
    $tempFile = tempnam(sys_get_temp_dir(), 'php-commitlint-test') . $suffix;
    file_put_contents($tempFile, $content);

    return $tempFile;
}

function cleanupTempPath(string $path): void
{
    if (is_file($path)) {
        unlink($path);
    } elseif (is_dir($path)) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        /** @var SplFileInfo $fileinfo */
        foreach ($files as $fileinfo) {
            $realPath = $fileinfo->getRealPath();
            if ($realPath === false) {
                // Handle symlinks that might have broken targets
                $pathName = $fileinfo->getPathname();
                if (is_link($pathName)) {
                    unlink($pathName);
                }

                continue;
            }

            $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
            $todo($realPath);
        }

        rmdir($path);
    }
}

/**
 * @param array<string, mixed> $default
 * @param array<string, mixed> $custom
 * @return array<string, mixed>
 */
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

/**
 * @param array $array
 * @return bool
 */
function isSequentialArray(array $array): bool
{
    if (empty($array)) {
        return true;
    }

    return array_is_list($array);
}
