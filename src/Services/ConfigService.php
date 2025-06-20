<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Services;

class ConfigService
{
    private const CONFIG_FILE = '.commitlintrc.json';
    private const COMPOSER_CONFIG_KEY = 'php-commitlint';

    private static ?array $configCache = null;

    public function loadConfig(): array
    {
        // Return cached config if available
        if (self::$configCache !== null) {
            return self::$configCache;
        }

        $config = $this->loadConfigFromSources();

        // Cache the validated config
        self::$configCache = $config;

        return $config;
    }

    public function clearCache(): void
    {
        self::$configCache = null;
    }

    private function loadConfigFromSources(): array
    {
        // Try to load from .commitlintrc.json first
        if ($this->configExists()) {
            try {
                $content = $this->readFileSecurely($this->getConfigPath());
                $config = $this->parseJson($content, $this->getConfigPath());

                return $this->mergeConfig($this->getDefaultConfig(), $this->validateConfig($config));
            } catch (\Throwable $e) {
                throw new \RuntimeException('Failed to load configuration: ' . $e->getMessage(), 0, $e);
            }
        }

        // Fallback to composer.json config
        if (file_exists('composer.json')) {
            try {
                $content = $this->readFileSecurely('composer.json');
                $composer = json_decode($content, true);
                if (!is_array($composer)) {
                    throw new \RuntimeException('Invalid composer.json format');
                }

                // Ensure 'extra' and 'php-commitlint' keys exist
                if (!isset($composer['extra'])) {
                    $composer['extra'] = [];
                }
                if (!isset($composer['extra'][self::COMPOSER_CONFIG_KEY])) {
                    $composer['extra'][self::COMPOSER_CONFIG_KEY] = [];
                }

                return $this->mergeConfig($this->getDefaultConfig(), $this->validateConfig($composer['extra'][self::COMPOSER_CONFIG_KEY]));
            } catch (\Throwable $e) {
                throw new \RuntimeException('Failed to load configuration from composer.json: ' . $e->getMessage(), 0, $e);
            }
        }

        return $this->getDefaultConfig();
    }

    private function readFileSecurely(string $filePath): string
    {
        // Validate file path to prevent directory traversal
        $realPath = realpath($filePath);
        if ($realPath === false) {
            throw new \RuntimeException("File not found: {$filePath}");
        }

        $workingDir = getcwd();
        if ($workingDir === false) {
            throw new \RuntimeException("Failed to get working directory");
        }

        // Normalize paths for cross-platform compatibility
        $normalizedRealPath = $this->normalizePath($realPath);
        $normalizedWorkingDir = $this->normalizePath($workingDir);

        // On Windows, also try normalizing the working directory with realpath for consistency
        if (PHP_OS_FAMILY === 'Windows') {
            $realWorkingDir = realpath($workingDir);
            if ($realWorkingDir !== false) {
                $normalizedWorkingDir = $this->normalizePath($realWorkingDir);
            }
        }

        if (!str_starts_with($normalizedRealPath, $normalizedWorkingDir)) {
            throw new \RuntimeException("Access denied");
        }

        // Check if file is readable before attempting to read it
        if (!is_readable($realPath)) {
            throw new \RuntimeException("Failed to read file");
        }

        $content = file_get_contents($realPath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read file");
        }

        // Check file size (prevent DoS)
        if (strlen($content) > 100000) { // 100KB limit
            throw new \RuntimeException("Configuration file too large: {$filePath}");
        }

        return $content;
    }

    private function parseJson(string $content, string $filePath): array
    {
        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $relativePath = $this->getRelativePath($filePath);

            throw new \RuntimeException("Invalid JSON in {$relativePath}: " . json_last_error_msg());
        }

        if (!is_array($decoded) || $this->isSequentialArray($decoded)) {
            throw new \RuntimeException("Configuration must be a JSON object");
        }

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

    private function normalizePath(string $path): string
    {
        // Convert backslashes to forward slashes
        $normalized = str_replace('\\', '/', $path);

        // Convert to lowercase on Windows for case-insensitive comparison
        if (PHP_OS_FAMILY === 'Windows') {
            $normalized = strtolower($normalized);

            // Handle Windows short path names (8.3 format) by expanding them
            if (str_contains($normalized, '~')) {
                // Try to get the real path to expand short names
                $realNormalized = realpath($path);
                if ($realNormalized !== false) {
                    $normalized = strtolower(str_replace('\\', '/', $realNormalized));
                }
            }
        }

        // Remove any trailing slashes for consistent comparison
        return rtrim($normalized, '/');
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
        $config = $this->getDefaultConfig();

        // Add schema reference for IDE support
        $schemaUrl = 'https://raw.githubusercontent.com/dev-kraken/php-commitlint/main/docs/schema.json';
        $config = ['$schema' => $schemaUrl] + $config;

        // Ensure pre_commit_commands is encoded as an object, not array
        if (isset($config['pre_commit_commands']) && empty($config['pre_commit_commands'])) {
            $config['pre_commit_commands'] = new \stdClass();
        }

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->getConfigPath(), $json);
    }

    public function getDefaultConfig(): array
    {
        return [
            'auto_install' => false,
            'rules' => [
                'type' => [
                    'required' => true,
                    'allowed' => [
                        'feat',     // New features
                        'fix',      // Bug fixes
                        'docs',     // Documentation changes
                        'style',    // Code style changes (formatting, etc)
                        'refactor', // Code refactoring
                        'perf',     // Performance improvements
                        'test',     // Adding or updating tests
                        'chore',    // Maintenance tasks
                        'ci',       // CI/CD changes
                        'build',    // Build system changes
                        'revert',   // Reverting previous commits
                    ],
                ],
                'scope' => [
                    'required' => false,
                    'allowed' => [], // Empty means any scope is allowed
                ],
                'subject' => [
                    'min_length' => 1,
                    'max_length' => 100,
                    'case' => 'any', // 'lower', 'upper', 'any'
                    'end_with_period' => false,
                ],
                'body' => [
                    'max_line_length' => 100,
                    'leading_blank' => true, // Require blank line between subject and body
                ],
                'footer' => [
                    'leading_blank' => true, // Require blank line between body and footer
                ],
            ],
            'patterns' => [
                // Custom regex patterns for additional validation
                'breaking_change' => '/^BREAKING CHANGE:/',
                'issue_reference' => '/(?:close[sd]?|fix(?:e[sd])?|resolve[sd]?)\s+#\d+/i',
            ],
            'hooks' => [
                'commit-msg' => true,
                'pre-commit' => false,
                'pre-push' => false,
            ],
            'pre_commit_commands' => [
                // Examples of pre-commit commands that run for all team members
                // 'Code Style Check' => 'vendor/bin/php-cs-fixer fix --dry-run --diff',
                // 'Static Analysis' => 'vendor/bin/phpstan analyse',
                // 'Run Tests' => 'vendor/bin/pest',
            ],
            'format' => [
                'type' => true,
                'scope' => 'optional',
                'description' => true,
                'body' => 'optional',
                'footer' => 'optional',
            ],
        ];
    }

    public function saveConfig(array $config): void
    {
        // Ensure pre_commit_commands is encoded as an object, not array
        if (isset($config['pre_commit_commands']) && empty($config['pre_commit_commands'])) {
            $config['pre_commit_commands'] = new \stdClass();
        }

        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->getConfigPath(), $json);
    }

    public function updateComposerConfig(array $config): void
    {
        if (!file_exists('composer.json')) {
            throw new \RuntimeException('composer.json not found');
        }

        $content = file_get_contents('composer.json');
        if ($content === false) {
            throw new \RuntimeException('Failed to read composer.json');
        }
        $composer = json_decode($content, true);
        if (!is_array($composer)) {
            throw new \RuntimeException('Invalid composer.json format');
        }

        // Ensure 'extra' and 'php-commitlint' keys exist
        if (!isset($composer['extra'])) {
            $composer['extra'] = [];
        }
        if (!isset($composer['extra'][self::COMPOSER_CONFIG_KEY])) {
            $composer['extra'][self::COMPOSER_CONFIG_KEY] = [];
        }

        $composer['extra'][self::COMPOSER_CONFIG_KEY] = $config;

        $json = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents('composer.json', $json);
    }

    private function mergeConfig(array $default, array $custom): array
    {
        $result = $default;

        foreach ($custom as $key => $value) {
            if (isset($result[$key]) && is_array($result[$key]) && is_array($value)) {
                // For sequential arrays (like 'allowed'), override completely
                if ($this->isSequentialArray($value) || $this->isSequentialArray($result[$key])) {
                    $result[$key] = $value;
                } else {
                    // Only merge associative arrays
                    $result[$key] = $this->mergeConfig($result[$key], $value);
                }
            } else {
                // Override completely for non-arrays and other cases
                $result[$key] = $value;
            }
        }

        return $result;
    }

    private function isSequentialArray(array $array): bool
    {
        if (empty($array)) {
            return true;
        }

        return array_keys($array) === range(0, count($array) - 1);
    }

    private function validateConfig(array $config): array
    {
        // Basic validation of configuration structure
        if (isset($config['rules']['type']['allowed']) && !is_array($config['rules']['type']['allowed'])) {
            throw new \RuntimeException('Config rules.type.allowed must be an array');
        }

        if (isset($config['rules']['scope']['allowed']) && !is_array($config['rules']['scope']['allowed'])) {
            throw new \RuntimeException('Config rules.scope.allowed must be an array');
        }

        if (isset($config['rules']['subject']['min_length']) && (!is_int($config['rules']['subject']['min_length']) || $config['rules']['subject']['min_length'] < 0)) {
            throw new \RuntimeException('Config rules.subject.min_length must be a non-negative integer');
        }

        if (isset($config['rules']['subject']['max_length']) && (!is_int($config['rules']['subject']['max_length']) || $config['rules']['subject']['max_length'] < 1)) {
            throw new \RuntimeException('Config rules.subject.max_length must be a positive integer');
        }

        if (isset($config['rules']['subject']['case']) && !in_array($config['rules']['subject']['case'], ['lower', 'upper', 'any'])) {
            throw new \RuntimeException('Config rules.subject.case must be one of: lower, upper, any');
        }

        return $config;
    }
}
