<?php

declare(strict_types=1);

use DevKraken\PhpCommitlint\ServiceContainer;
use DevKraken\PhpCommitlint\Services\ConfigService;
use DevKraken\PhpCommitlint\Services\HookService;
use DevKraken\PhpCommitlint\Services\ValidationService;

beforeEach(function () {
    // Clear the singleton instance before each test
    $reflection = new ReflectionClass(ServiceContainer::class);
    $instance = $reflection->getProperty('instance');
    $instance->setAccessible(true);
    $instance->setValue(null, null);
});

describe('ServiceContainer', function () {
    it('implements singleton pattern correctly', function () {
        $container1 = ServiceContainer::getInstance();
        $container2 = ServiceContainer::getInstance();

        expect($container1)->toBe($container2);
    });

    it('creates ConfigService instance', function () {
        $container = ServiceContainer::getInstance();
        $configService = $container->getConfigService();

        expect($configService)->toBeInstanceOf(ConfigService::class);
    });

    it('creates HookService instance', function () {
        $container = ServiceContainer::getInstance();
        $hookService = $container->getHookService();

        expect($hookService)->toBeInstanceOf(HookService::class);
    });

    it('creates ValidationService instance', function () {
        $container = ServiceContainer::getInstance();
        $validationService = $container->getValidationService();

        expect($validationService)->toBeInstanceOf(ValidationService::class);
    });

    it('returns same instance on subsequent calls', function () {
        $container = ServiceContainer::getInstance();

        $configService1 = $container->getConfigService();
        $configService2 = $container->getConfigService();

        expect($configService1)->toBe($configService2);
    });

    it('allows setting custom service instances', function () {
        $container = ServiceContainer::getInstance();
        $mockConfigService = $this->createMock(ConfigService::class);

        $container->setService(ConfigService::class, $mockConfigService);

        expect($container->getConfigService())->toBe($mockConfigService);
    });

    it('clears all services correctly', function () {
        $container = ServiceContainer::getInstance();

        // Create some services
        $configService1 = $container->getConfigService();
        $hookService1 = $container->getHookService();

        // Clear services
        $container->clearServices();

        // Should create new instances
        $configService2 = $container->getConfigService();
        $hookService2 = $container->getHookService();

        expect($configService1)->not->toBe($configService2);
        expect($hookService1)->not->toBe($hookService2);
    });

    it('handles multiple service types correctly', function () {
        $container = ServiceContainer::getInstance();

        $configService = $container->getConfigService();
        $hookService = $container->getHookService();
        $validationService = $container->getValidationService();

        expect($configService)->toBeInstanceOf(ConfigService::class);
        expect($hookService)->toBeInstanceOf(HookService::class);
        expect($validationService)->toBeInstanceOf(ValidationService::class);

        // Ensure they are different instances
        expect($configService)->not->toBe($hookService);
        expect($configService)->not->toBe($validationService);
        expect($hookService)->not->toBe($validationService);
    });
});
