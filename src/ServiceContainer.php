<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint;

use DevKraken\PhpCommitlint\Services\ConfigService;
use DevKraken\PhpCommitlint\Services\HookService;
use DevKraken\PhpCommitlint\Services\ValidationService;

class ServiceContainer
{
    private static ?self $instance = null;
    /**
     * @var array<string, object>
     */
    private array $services = [];

    private function __construct()
    {
        // Private constructor for singleton
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @return ConfigService
     */
    public function getConfigService(): ConfigService
    {
        if (!isset($this->services[ConfigService::class])) {
            $this->services[ConfigService::class] = new ConfigService();
        }

        $service = $this->services[ConfigService::class];
        assert($service instanceof ConfigService);

        return $service;
    }

    public function getHookService(): HookService
    {
        if (!isset($this->services[HookService::class])) {
            $this->services[HookService::class] = new HookService();
        }

        $service = $this->services[HookService::class];
        assert($service instanceof HookService);

        return $service;
    }

    public function getValidationService(): ValidationService
    {
        if (!isset($this->services[ValidationService::class])) {
            $this->services[ValidationService::class] = new ValidationService();
        }

        $service = $this->services[ValidationService::class];
        assert($service instanceof ValidationService);

        return $service;
    }

    /**
     * For testing - allows injecting mock services
     */
    public function setService(string $className, object $service): void
    {
        $this->services[$className] = $service;
    }

    /**
     * Clear all services (useful for testing)
     */
    public function clearServices(): void
    {
        $this->services = [];
    }
}
