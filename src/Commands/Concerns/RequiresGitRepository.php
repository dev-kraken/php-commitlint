<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Commands\Concerns;

use DevKraken\PhpCommitlint\Services\HookService;
use RuntimeException;

trait RequiresGitRepository
{
    private function assertGitRepository(HookService $hookService, string $message = 'Not a Git repository!'): void
    {
        if (!$hookService->isGitRepository()) {
            throw new RuntimeException($message);
        }
    }
}
