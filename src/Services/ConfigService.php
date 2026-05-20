<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Services;

use RuntimeException;
use Throwable;

class ConfigService
{
    public const string CONFIG_FILE = '.commitlintrc.json';
    public const string COMPOSER_FILE = 'composer.json';
    public const string COMPOSER_CONFIG_KEY = 'php-commitlint';

    private const string SCHEMA_URL = 'https://raw.githubusercontent.com/dev-kraken/php-commitlint/main/docs/schema.json';
    private const int MAX_CONFIG_FILE_SIZE = 100_000;
    private const int JSON_ENCODE_FLAGS = JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

    /** @var array<string, mixed>|null */
    private static ?array $configCache = null;

    /**
     * @return array<string, mixed>
     */
    public function loadConfig(): array
    {
        return self::$configCache ??= $this->loadConfigFromSources();
    }

    public function clearCache(): void
    {
        self::$configCache = null;
    }

    public function configExists(): bool
    {
        return file_exists($this->getConfigPath());
    }

    public function getConfigPath(): string
    {
        return getcwd() . DIRECTORY_SEPARATOR . self::CONFIG_FILE;
    }

    public function createDefaultConfig(): void
    {
        $config = ['$schema' => self::SCHEMA_URL] + $this->getDefaultConfig();
        $this->writeJsonFile($this->getConfigPath(), $this->prepareForEncoding($config));
    }

    /**
     * @param array<string, mixed> $config
     */
    public function saveConfig(array $config): void
    {
        $this->writeJsonFile($this->getConfigPath(), $this->prepareForEncoding($config));
    }

    /**
     * @param array<string, mixed> $config
     */
    public function updateComposerConfig(array $config): void
    {
        if (!file_exists(self::COMPOSER_FILE)) {
            throw new RuntimeException('composer.json not found');
        }

        $composer = $this->decodeJsonObject($this->readFile(self::COMPOSER_FILE), self::COMPOSER_FILE);
        $composer['extra'] ??= [];

        if (!is_array($composer['extra'])) {
            throw new RuntimeException('composer.json "extra" must be an object');
        }

        $composer['extra'][self::COMPOSER_CONFIG_KEY] = $config;
        $this->writeJsonFile(self::COMPOSER_FILE, $composer);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDefaultConfig(): array
    {
        return [
            'auto_install' => false,
            'rules' => [
                'type' => [
                    'required' => true,
                    'allowed' => [
                        'feat', 'fix', 'docs', 'style', 'refactor', 'perf',
                        'test', 'chore', 'ci', 'build', 'revert',
                    ],
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
                'issue_reference' => '/(?:close[sd]?|fix(?:e[sd])?|resolve[sd]?)\s+#\d+/i',
            ],
            'hooks' => [
                'commit-msg' => true,
                'pre-commit' => false,
                'pre-push' => false,
            ],
            'pre_commit_commands' => [],
            'format' => [
                'type' => true,
                'scope' => 'optional',
                'description' => true,
                'body' => 'optional',
                'footer' => 'optional',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfigFromSources(): array
    {
        if ($this->configExists()) {
            return $this->loadFromDedicatedConfig();
        }

        if (file_exists(self::COMPOSER_FILE)) {
            return $this->loadFromComposerConfig();
        }

        return $this->getDefaultConfig();
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFromDedicatedConfig(): array
    {
        try {
            $content = $this->readFileSecurely($this->getConfigPath());
            $config = $this->decodeJsonObject($content, $this->getConfigPath());

            return $this->mergeConfig($this->getDefaultConfig(), $this->validateConfig($config));
        } catch (Throwable $e) {
            throw new RuntimeException('Failed to load configuration: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function loadFromComposerConfig(): array
    {
        try {
            $composer = $this->decodeJsonObject($this->readFile(self::COMPOSER_FILE), self::COMPOSER_FILE);
            $extra = $this->asStringKeyedArray($composer['extra'] ?? null);
            $custom = $this->asStringKeyedArray($extra[self::COMPOSER_CONFIG_KEY] ?? null);

            return $this->mergeConfig($this->getDefaultConfig(), $this->validateConfig($custom));
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Failed to load configuration from composer.json: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    private function readFile(string $filePath): string
    {
        $content = @file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Failed to read file: {$filePath}");
        }

        return $content;
    }

    private function readFileSecurely(string $filePath): string
    {
        $realPath = realpath($filePath);
        if ($realPath === false) {
            throw new RuntimeException("File not found: {$filePath}");
        }

        $workingDir = getcwd();
        if ($workingDir === false) {
            throw new RuntimeException('Failed to get working directory');
        }

        if (!$this->isPathWithin($realPath, $workingDir)) {
            throw new RuntimeException('Access denied');
        }

        if (!is_readable($realPath)) {
            throw new RuntimeException('Failed to read file');
        }

        $content = file_get_contents($realPath);
        if ($content === false) {
            throw new RuntimeException('Failed to read file');
        }

        if (strlen($content) > self::MAX_CONFIG_FILE_SIZE) {
            throw new RuntimeException("Configuration file too large: {$filePath}");
        }

        return $content;
    }

    private function isPathWithin(string $path, string $workingDir): bool
    {
        $normalizedPath = $this->normalizePath($path);
        $normalizedDir = $this->normalizePath($workingDir);

        if (PHP_OS_FAMILY === 'Windows') {
            $resolvedDir = realpath($workingDir);
            if ($resolvedDir !== false) {
                $normalizedDir = $this->normalizePath($resolvedDir);
            }
        }

        return str_starts_with($normalizedPath, $normalizedDir);
    }

    private function normalizePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);

        if (PHP_OS_FAMILY === 'Windows') {
            $normalized = strtolower($normalized);

            // Expand Windows 8.3 short path names
            if (str_contains($normalized, '~')) {
                $resolved = realpath($path);
                if ($resolved !== false) {
                    $normalized = strtolower(str_replace('\\', '/', $resolved));
                }
            }
        }

        return rtrim($normalized, '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonObject(string $content, string $filePath): array
    {
        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(sprintf(
                'Invalid JSON in %s: %s',
                $this->getRelativePath($filePath),
                json_last_error_msg()
            ));
        }

        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new RuntimeException('Configuration must be a JSON object');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    private function getRelativePath(string $filePath): string
    {
        $workingDir = getcwd();
        if ($workingDir !== false && str_starts_with($filePath, $workingDir)) {
            return ltrim(substr($filePath, strlen($workingDir)), '/\\');
        }

        return basename($filePath);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function writeJsonFile(string $filePath, array $data): void
    {
        $json = json_encode($data, self::JSON_ENCODE_FLAGS);
        if ($json === false) {
            throw new RuntimeException("Failed to encode JSON for: {$filePath}");
        }

        if (file_put_contents($filePath, $json) === false) {
            throw new RuntimeException("Failed to write file: {$filePath}");
        }
    }

    /**
     * Ensure empty pre_commit_commands serialises as a JSON object rather than [].
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function prepareForEncoding(array $config): array
    {
        if (isset($config['pre_commit_commands']) && $config['pre_commit_commands'] === []) {
            $config['pre_commit_commands'] = new \stdClass();
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $default
     * @param array<string, mixed> $custom
     * @return array<string, mixed>
     */
    private function mergeConfig(array $default, array $custom): array
    {
        $result = $default;

        foreach ($custom as $key => $value) {
            if (
                isset($result[$key])
                && is_array($result[$key])
                && is_array($value)
                && !$this->isSequentialArray($value)
                && !$this->isSequentialArray($result[$key])
            ) {
                /** @var array<string, mixed> $existing */
                $existing = $result[$key];
                /** @var array<string, mixed> $value */
                $result[$key] = $this->mergeConfig($existing, $value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array<mixed> $array
     */
    private function isSequentialArray(array $array): bool
    {
        return $array === [] || array_is_list($array);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function validateConfig(array $config): array
    {
        $rules = $this->asStringKeyedArray($config['rules'] ?? null);
        if ($rules === []) {
            return $config;
        }

        $this->assertArrayIfSet($rules, ['type', 'allowed'], 'Config rules.type.allowed must be an array');
        $this->assertArrayIfSet($rules, ['scope', 'allowed'], 'Config rules.scope.allowed must be an array');
        $this->assertNonNegativeIntIfSet(
            $rules,
            ['subject', 'min_length'],
            'Config rules.subject.min_length must be a non-negative integer'
        );
        $this->assertPositiveIntIfSet(
            $rules,
            ['subject', 'max_length'],
            'Config rules.subject.max_length must be a positive integer'
        );

        $case = $this->nestedValue($rules, ['subject', 'case']);
        if ($case !== null && !in_array($case, ['lower', 'upper', 'any'], true)) {
            throw new RuntimeException('Config rules.subject.case must be one of: lower, upper, any');
        }

        return $config;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $path
     */
    private function assertArrayIfSet(array $data, array $path, string $message): void
    {
        $value = $this->nestedValue($data, $path);
        if ($value !== null && !is_array($value)) {
            throw new RuntimeException($message);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $path
     */
    private function assertNonNegativeIntIfSet(array $data, array $path, string $message): void
    {
        $value = $this->nestedValue($data, $path);
        if ($value !== null && (!is_int($value) || $value < 0)) {
            throw new RuntimeException($message);
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $path
     */
    private function assertPositiveIntIfSet(array $data, array $path, string $message): void
    {
        $value = $this->nestedValue($data, $path);
        if ($value !== null && (!is_int($value) || $value < 1)) {
            throw new RuntimeException($message);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function asStringKeyedArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        /** @var array<string, mixed> $value */
        return $value;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $path
     */
    private function nestedValue(array $data, array $path): mixed
    {
        $current = $data;
        foreach ($path as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return null;
            }
            $current = $current[$segment];
        }

        return $current;
    }
}
