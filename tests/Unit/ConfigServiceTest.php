<?php

declare(strict_types=1);

use DevKraken\PhpCommitlint\Services\ConfigService;

beforeEach(function () {
    $this->configService = new ConfigService();

    // Clean up any existing config files
    if (file_exists('.commitlintrc.json')) {
        unlink('.commitlintrc.json');
    }
});

afterEach(function () {
    // Clean up test files
    if (file_exists('.commitlintrc.json')) {
        unlink('.commitlintrc.json');
    }
});

describe('ConfigService', function () {
    it('returns default config when no config file exists', function () {
        $config = $this->configService->loadConfig();

        expect($config)->toHaveKey('rules');
        expect($config)->toHaveKey('auto_install');
        expect($config['rules'])->toHaveKey('type');
        expect($config['rules']['type']['allowed'])->toContain('feat');
        expect($config['rules']['type']['allowed'])->toContain('fix');
    });

    it('creates default config file', function () {
        expect(file_exists('.commitlintrc.json'))->toBeFalse();

        $this->configService->createDefaultConfig();

        expect(file_exists('.commitlintrc.json'))->toBeTrue();

        $content = file_get_contents('.commitlintrc.json');
        expect($content)->not()->toBeFalse();
        $config = json_decode((string) $content, true);

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
            ->toThrow(RuntimeException::class, 'Failed to load configuration: Invalid JSON in config file: Syntax error');
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
