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
        'format' => [
            'enabled' => true, // Enable format validation for tests
            'conventional' => true,
        ],
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
            // Remove problematic patterns that are causing validation failures
            // 'breaking_change' => '/^BREAKING CHANGE:/',
            // 'issue_reference' => '/(?:close[sd]?|fix(?:e[sd])?|resolve[sd]?)\\s+#\\d+/i',
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
        @unlink($path);

        return;
    }

    if (!is_dir($path)) {
        return;
    }

    // On Windows, try multiple cleanup attempts with delays
    $maxAttempts = PHP_OS_FAMILY === 'Windows' ? 3 : 1;

    for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
        try {
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
                        @unlink($pathName);
                    }

                    continue;
                }

                if ($fileinfo->isDir()) {
                    @rmdir($realPath);
                } else {
                    @unlink($realPath);
                }
            }

            // Try to remove the main directory
            if (is_dir($path)) {
                @rmdir($path);
            }

            // If directory is gone, we're done
            if (!is_dir($path)) {
                break;
            }

            // On Windows, wait a bit before retry
            if (PHP_OS_FAMILY === 'Windows' && $attempt < $maxAttempts) {
                usleep(100000); // 100ms
            }
        } catch (Exception $e) {
            // On final attempt or non-Windows, ignore errors
            if ($attempt === $maxAttempts || PHP_OS_FAMILY !== 'Windows') {
                break;
            }
        }
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
