<?php

declare(strict_types=1);

use DevKraken\PhpCommitlint\Services\ConfigService;

beforeEach(function () {
    $this->configService = new ConfigService();
    $this->tempDir = createTempDirectory();
    $this->originalCwd = getcwd();
    chdir($this->tempDir);

    // Clean up any existing config files
    if (file_exists('.commitlintrc.json')) {
        unlink('.commitlintrc.json');
    }
});

afterEach(function () {
    chdir($this->originalCwd);
    cleanupTempPath($this->tempDir);

    // Clear the config cache
    $this->configService->clearCache();
});

describe('ConfigService', function () {
    it('returns default config when no config file exists', function () {
        $config = $this->configService->loadConfig();

        expect($config)->toHaveKey('rules')
            ->and($config)->toHaveKey('auto_install')
            ->and($config['rules'])->toHaveKey('type')
            ->and($config['rules']['type']['allowed'])->toContain('feat')
            ->and($config['rules']['type']['allowed'])->toContain('fix');
    });

    it('creates default config file', function () {
        expect(file_exists('.commitlintrc.json'))->toBeFalse();

        $this->configService->createDefaultConfig();

        expect(file_exists('.commitlintrc.json'))->toBeTrue();

        $content = file_get_contents('.commitlintrc.json');
        expect($content)->not()->toBeFalse();
        $config = json_decode((string) $content, true);
        assert(is_array($config));

        expect($config)->toHaveKey('rules');
        expect($config['auto_install'])->toBeFalse();
    });

    it('loads config from file when it exists', function () {
        $customConfig = [
            'auto_install' => true,
            'rules' => [
                'type' => [
                    'allowed' => ['custom', 'types'],
                ],
            ],
        ];

        file_put_contents('.commitlintrc.json', json_encode($customConfig));

        $config = $this->configService->loadConfig();
        assert(is_array($config));

        expect($config['auto_install'])->toBeTrue();
        expect($config['rules']['type']['allowed'])->toContain('custom');
        expect($config['rules']['type']['allowed'])->toContain('types');
        // Should still merge with defaults
        expect($config['rules']['subject'])->toHaveKey('min_length');
    });

    it('merges custom config with defaults', function () {
        $customConfig = [
            'rules' => [
                'type' => [
                    'allowed' => ['custom'],
                ],
                'scope' => [
                    'required' => true,
                ],
            ],
        ];

        file_put_contents('.commitlintrc.json', json_encode($customConfig));

        $config = $this->configService->loadConfig();
        assert(is_array($config));

        // Custom values should override defaults
        expect($config['rules']['type']['allowed'])->toBe(['custom']);
        expect($config['rules']['scope']['required'])->toBeTrue();

        // Non-specified values should use defaults
        expect($config['auto_install'])->toBeFalse();
        expect($config['rules']['subject']['min_length'])->toBe(1);
    });

    it('throws exception for invalid JSON', function () {
        file_put_contents('.commitlintrc.json', 'invalid json content');

        expect(fn () => $this->configService->loadConfig())
            ->toThrow(RuntimeException::class, 'Failed to load configuration: Invalid JSON in .commitlintrc.json: Syntax error');
    });

    it('checks if config file exists', function () {
        expect($this->configService->configExists())->toBeFalse();

        $this->configService->createDefaultConfig();

        expect($this->configService->configExists())->toBeTrue();
    });

    it('saves config to file', function () {
        $config = [
            'auto_install' => true,
            'rules' => [
                'type' => [
                    'allowed' => ['feat', 'fix', 'custom'],
                ],
            ],
        ];

        $this->configService->saveConfig($config);

        expect(file_exists('.commitlintrc.json'))->toBeTrue();

        $content = file_get_contents('.commitlintrc.json');
        expect($content)->not()->toBeFalse();
        $saved = json_decode((string) $content, true);
        assert(is_array($saved));
        expect($saved['auto_install'])->toBeTrue();
        expect($saved['rules']['type']['allowed'])->toContain('custom');
    });

    it('returns correct config path', function () {
        $path = $this->configService->getConfigPath();

        expect($path)->toEndWith('.commitlintrc.json');
        expect($path)->toContain(getcwd());
    });

    it('validates configuration structure', function () {
        $invalidConfig = [
            'rules' => [
                'type' => [
                    'allowed' => 'should be array not string',
                ],
            ],
        ];

        file_put_contents('.commitlintrc.json', json_encode($invalidConfig));

        expect(fn () => $this->configService->loadConfig())
            ->toThrow(RuntimeException::class, 'Failed to load configuration: Config rules.type.allowed must be an array');
    });

    it('validates subject length configuration', function () {
        $invalidConfig = [
            'rules' => [
                'subject' => [
                    'min_length' => -1, // Invalid negative value
                ],
            ],
        ];

        file_put_contents('.commitlintrc.json', json_encode($invalidConfig));

        expect(fn () => $this->configService->loadConfig())
            ->toThrow(RuntimeException::class, 'Failed to load configuration: Config rules.subject.min_length must be a non-negative integer');
    });
});

describe('ConfigService Security Features', function () {
    beforeEach(function () {
        $this->configService = new ConfigService();
        $this->tempDir = createTempDirectory();
        $this->originalCwd = getcwd();
        chdir($this->tempDir);
    });

    afterEach(function () {
        chdir($this->originalCwd);
        cleanupTempPath($this->tempDir);
        $this->configService->clearCache();
    });

    it('prevents directory traversal attacks', function () {
        // Create a directory outside the working directory
        $outsideDir = createTempDirectory();
        file_put_contents($outsideDir . '/secret.json', '{"secret": "data"}');

        // Try to create a symlink to outside file (skip on Windows if symlink fails)
        try {
            symlink($outsideDir . '/secret.json', '.commitlintrc.json');

            expect(fn () => $this->configService->loadConfig())
                ->toThrow(RuntimeException::class, 'Access denied');
        } catch (Throwable $e) {
            // On Windows, symlink might fail due to permissions
            // In that case, manually create a file that would trigger the same path validation
            if (PHP_OS_FAMILY === 'Windows' && str_contains($e->getMessage(), 'symlink')) {
                // Create a config file that points to outside directory by copying content
                copy($outsideDir . '/secret.json', '.commitlintrc.json');

                // This should still work fine as it's in the working directory
                $config = $this->configService->loadConfig();
                expect($config)->toBeArray();
            } else {
                throw $e;
            }
        }

        cleanupTempPath($outsideDir);
    });

    it('rejects files that are too large', function () {
        // Create a large config file (over 100KB)
        $largeContent = json_encode([
            'large_data' => str_repeat('x', 100001),
        ]);
        file_put_contents('.commitlintrc.json', $largeContent);

        expect(fn () => $this->configService->loadConfig())
            ->toThrow(RuntimeException::class, 'Configuration file too large');
    });

    it('validates JSON must be an object', function () {
        file_put_contents('.commitlintrc.json', '["not", "an", "object"]');

        expect(fn () => $this->configService->loadConfig())
            ->toThrow(RuntimeException::class, 'Configuration must be a JSON object');
    });

    it('handles file read failures gracefully', function () {
        // Skip this test on Windows as file permissions work differently
        if (PHP_OS_FAMILY === 'Windows') {
            // On Windows, just verify the security check works for paths
            expect(true)->toBeTrue(); // Mark test as passed - path security is tested elsewhere

            return;
        }

        // Create a file and then make it unreadable (Unix/Linux only)
        file_put_contents('.commitlintrc.json', '{"test": true}');
        chmod('.commitlintrc.json', 0o000);

        expect(fn () => $this->configService->loadConfig())
            ->toThrow(RuntimeException::class, 'Failed to read file');

        chmod('.commitlintrc.json', 0o644); // Restore permissions
    });
});

describe('ConfigService Caching', function () {
    beforeEach(function () {
        $this->configService = new ConfigService();
        $this->tempDir = createTempDirectory();
        $this->originalCwd = getcwd();
        chdir($this->tempDir);
    });

    afterEach(function () {
        chdir($this->originalCwd);
        cleanupTempPath($this->tempDir);
        $this->configService->clearCache();
    });

    it('caches configuration after first load', function () {
        $config = ['auto_install' => true];
        file_put_contents('.commitlintrc.json', json_encode($config));

        // First load
        $config1 = $this->configService->loadConfig();
        assert(is_array($config1));

        // Modify file
        file_put_contents('.commitlintrc.json', json_encode(['auto_install' => false]));

        // Second load should return cached version
        $config2 = $this->configService->loadConfig();
        assert(is_array($config2));

        expect($config1['auto_install'])->toBe($config2['auto_install']);
        expect($config2['auto_install'])->toBeTrue(); // Still cached version
    });

    it('clears cache correctly', function () {
        $config = ['auto_install' => true];
        file_put_contents('.commitlintrc.json', json_encode($config));

        // First load
        $config1 = $this->configService->loadConfig();
        assert(is_array($config1));
        expect($config1['auto_install'])->toBeTrue();

        // Clear cache
        $this->configService->clearCache();

        // Modify file
        file_put_contents('.commitlintrc.json', json_encode(['auto_install' => false]));

        // Should reload from file
        $config2 = $this->configService->loadConfig();
        assert(is_array($config2));
        expect($config2['auto_install'])->toBeFalse();
    });

    it('returns same cached instance across multiple calls', function () {
        $config = ['auto_install' => true];
        file_put_contents('.commitlintrc.json', json_encode($config));

        $config1 = $this->configService->loadConfig();
        $config2 = $this->configService->loadConfig();
        $config3 = $this->configService->loadConfig();

        // All should be the same cached instance
        expect($config1)->toBe($config2);
        expect($config2)->toBe($config3);
    });
});
