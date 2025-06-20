<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Models;

final readonly class ValidationResult
{
    /**
     * @param list<string> $errors
     */
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

    /**
     * @return list<string>
     */
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

    public function getFirstError(): ?string
    {
        return $this->errors[0] ?? null;
    }

    /**
     * @param list<string> $errors
     */
    public function withErrors(array $errors): self
    {
        return new self(false, $errors, $this->type, $this->scope);
    }

    public function withError(string $error): self
    {
        $errors = $this->errors;
        $errors[] = $error;

        return new self(false, $errors, $this->type, $this->scope);
    }

    public function withType(?string $type): self
    {
        return new self($this->isValid, $this->errors, $type, $this->scope);
    }

    public function withScope(?string $scope): self
    {
        return new self($this->isValid, $this->errors, $this->type, $scope);
    }

    /**
     * @return array{valid: bool, errors: list<string>, type: string|null, scope: string|null}
     */
    public function toArray(): array
    {
        return [
            'valid' => $this->isValid,
            'errors' => $this->errors,
            'type' => $this->type,
            'scope' => $this->scope,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public static function valid(?string $type = null, ?string $scope = null): self
    {
        return new self(true, [], $type, $scope);
    }

    /**
     * @param list<string> $errors
     */
    public static function invalid(array $errors, ?string $type = null, ?string $scope = null): self
    {
        return new self(false, $errors, $type, $scope);
    }

    public static function error(string $error, ?string $type = null, ?string $scope = null): self
    {
        return new self(false, [$error], $type, $scope);
    }
}
