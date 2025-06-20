<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint;

use DevKraken\PhpCommitlint\Services\ConfigService;
use DevKraken\PhpCommitlint\Services\HookService;
use DevKraken\PhpCommitlint\Services\LoggerService;
use DevKraken\PhpCommitlint\Services\ValidationService;

final class ServiceContainer
{
    private static ?self $instance = null;

    /**
     * @var array<class-string, object>
     */
    private array $services = [];

    /**
     * @var array<class-string, callable(): object>
     */
    private array $factories = [];

    private function __construct()
    {
        $this->registerFactories();
    }

    public static function getInstance(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    public function get(string $className): object
    {
        if (!isset($this->services[$className])) {
            $this->services[$className] = $this->createService($className);
        }

        /** @var T */
        return $this->services[$className];
    }

    public function getConfigService(): ConfigService
    {
        return $this->get(ConfigService::class);
    }

    public function getHookService(): HookService
    {
        return $this->get(HookService::class);
    }

    public function getValidationService(): ValidationService
    {
        return $this->get(ValidationService::class);
    }

    public function getLoggerService(): LoggerService
    {
        return $this->get(LoggerService::class);
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param T $service
     */
    public function setService(string $className, object $service): void
    {
        $this->services[$className] = $service;
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @param callable(): T $factory
     */
    public function setFactory(string $className, callable $factory): void
    {
        $this->factories[$className] = $factory;
        unset($this->services[$className]); // Clear cached instance
    }

    public function clearServices(): void
    {
        $this->services = [];
    }

    public function reset(): void
    {
        $this->services = [];
        $this->registerFactories();
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    private function createService(string $className): object
    {
        if (isset($this->factories[$className])) {
            /** @var T */
            $service = ($this->factories[$className])();

            return $service;
        }

        /** @var T */
        $service = match ($className) {
            ConfigService::class => new ConfigService(),
            HookService::class => new HookService(),
            ValidationService::class => new ValidationService(),
            LoggerService::class => new LoggerService(),
            default => throw new \InvalidArgumentException("Unknown service: {$className}")
        };

        return $service;
    }

    private function registerFactories(): void
    {
        $this->factories = [
            ConfigService::class => fn () => new ConfigService(),
            HookService::class => fn () => new HookService(),
            ValidationService::class => fn () => new ValidationService(),
            LoggerService::class => fn () => new LoggerService(),
        ];
    }
}
