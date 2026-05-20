<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint;

use DevKraken\PhpCommitlint\Services\ConfigService;
use DevKraken\PhpCommitlint\Services\HookService;
use DevKraken\PhpCommitlint\Services\LoggerService;
use DevKraken\PhpCommitlint\Services\ValidationService;
use InvalidArgumentException;

final class ServiceContainer
{
    private static ?self $instance = null;

    /** @var array<class-string, object> */
    private array $services = [];

    /** @var array<class-string, callable(): object> */
    private array $factories = [];

    private function __construct()
    {
        $this->registerDefaultFactories();
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
        unset($this->services[$className]);
    }

    public function clearServices(): void
    {
        $this->services = [];
    }

    public function reset(): void
    {
        $this->services = [];
        $this->registerDefaultFactories();
    }

    /**
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    private function createService(string $className): object
    {
        if (!isset($this->factories[$className])) {
            throw new InvalidArgumentException("Unknown service: {$className}");
        }

        /** @var T */
        return ($this->factories[$className])();
    }

    private function registerDefaultFactories(): void
    {
        $this->factories = [
            ConfigService::class => static fn (): ConfigService => new ConfigService(),
            HookService::class => static fn (): HookService => new HookService(),
            ValidationService::class => static fn (): ValidationService => new ValidationService(),
            LoggerService::class => static fn (): LoggerService => new LoggerService(),
        ];
    }
}
