<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Enums;

enum ExitCode: int
{
    case SUCCESS = 0;
    case VALIDATION_FAILED = 1;
    case CONFIGURATION_ERROR = 2;
    case FILE_SYSTEM_ERROR = 3;
    case INVALID_ARGUMENT = 4;
    case RUNTIME_ERROR = 5;
    case PERMISSION_DENIED = 6;
    case NOT_GIT_REPOSITORY = 7;
}
