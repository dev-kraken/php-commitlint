<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Contracts;

interface HookServiceInterface
{
    public function isGitRepository(): bool;

    public function hasExistingHooks(): bool;

    public function hasInstalledHooks(): bool;

    public function installHooks(): void;

    public function uninstallHooks(): void;

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getInstalledHooks(): array;

    public function addCustomHook(string $hookName, string $command): void;

    public function removeCustomHook(string $hookName): void;
}
