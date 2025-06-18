<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Services;

class ConfigService
{
    private const CONFIG_FILE = '.commitlintrc.json';
    private const COMPOSER_CONFIG_KEY = 'php-commitlint';

    public function loadConfig(): array
    {
        // Try to load from .commitlintrc.json first
        if ($this->configExists()) {
            try {
                $content = file_get_contents($this->getConfigPath());
                if ($content === false) {
                    throw new \RuntimeException('Failed to read config file: ' . $this->getConfigPath());
                }

                $config = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException('Invalid JSON in config file: ' . json_last_error_msg());
                }

                return $this->mergeConfig($this->getDefaultConfig(), $this->validateConfig($config));
            } catch (\Throwable $e) {
                throw new \RuntimeException('Failed to load configuration: ' . $e->getMessage());
            }
        }

        // Fallback to composer.json config
        if (file_exists('composer.json')) {
            try {
                $content = file_get_contents('composer.json');
                if ($content === false) {
                    throw new \RuntimeException('Failed to read composer.json');
                }

                $composer = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \RuntimeException('Invalid JSON in composer.json: ' . json_last_error_msg());
                }

                if (isset($composer['extra'][self::COMPOSER_CONFIG_KEY])) {
                    return $this->mergeConfig($this->getDefaultConfig(), $this->validateConfig($composer['extra'][self::COMPOSER_CONFIG_KEY]));
                }
            } catch (\Throwable $e) {
                throw new \RuntimeException('Failed to load configuration from composer.json: ' . $e->getMessage());
            }
        }

        return $this->getDefaultConfig();
    }

    public function configExists(): bool
    {
        return file_exists($this->getConfigPath());
    }

    public function getConfigPath(): string
    {
        return getcwd() . '/' . self::CONFIG_FILE;
    }

    public function createDefaultConfig(): void
    {
        $config = $this->getDefaultConfig();
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
        $composer['extra'][self::COMPOSER_CONFIG_KEY] = $config;

        $json = json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents('composer.json', $json);
    }

    private function mergeConfig(array $default, array $custom): array
    {
        $result = $default;

        foreach ($custom as $key => $value) {
            if (isset($result[$key]) && is_array($result[$key]) && is_array($value) && !$this->isSequentialArray($value)) {
                // Only merge associative arrays, not sequential arrays
                $result[$key] = $this->mergeConfig($result[$key], $value);
            } else {
                // Override completely for non-arrays and sequential arrays
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
            throw new \InvalidArgumentException('Config rules.type.allowed must be an array');
        }

        if (isset($config['rules']['scope']['allowed']) && !is_array($config['rules']['scope']['allowed'])) {
            throw new \InvalidArgumentException('Config rules.scope.allowed must be an array');
        }

        if (isset($config['rules']['subject']['min_length']) && (!is_int($config['rules']['subject']['min_length']) || $config['rules']['subject']['min_length'] < 0)) {
            throw new \InvalidArgumentException('Config rules.subject.min_length must be a non-negative integer');
        }

        if (isset($config['rules']['subject']['max_length']) && (!is_int($config['rules']['subject']['max_length']) || $config['rules']['subject']['max_length'] < 1)) {
            throw new \InvalidArgumentException('Config rules.subject.max_length must be a positive integer');
        }

        if (isset($config['rules']['subject']['case']) && !in_array($config['rules']['subject']['case'], ['lower', 'upper', 'any'])) {
            throw new \InvalidArgumentException('Config rules.subject.case must be one of: lower, upper, any');
        }

        return $config;
    }
}
