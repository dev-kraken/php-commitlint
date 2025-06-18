<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Models;

class ValidationResult
{
    public function __construct(
        private bool $isValid,
        private array $errors,
        private ?string $type = null,
        private ?string $scope = null
    ) {
    }

    public function isValid(): bool
    {
        return $this->isValid;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getErrorCount(): int
    {
        return count($this->errors);
    }

    public function addError(string $error): void
    {
        $this->errors[] = $error;
        $this->isValid = false;
    }

    public function toArray(): array
    {
        return [
            'valid' => $this->isValid,
            'errors' => $this->errors,
            'type' => $this->type,
            'scope' => $this->scope,
        ];
    }
}
