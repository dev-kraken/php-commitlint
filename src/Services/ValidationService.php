<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Services;

use DevKraken\PhpCommitlint\Models\CommitMessage;
use DevKraken\PhpCommitlint\Models\ValidationResult;

class ValidationService
{
    public function validate(string $message, array $config): ValidationResult
    {
        try {
            $commitMessage = new CommitMessage($message);
            $errors = [];

            // Skip validation for merge commits, revert commits, etc.
            if ($this->shouldSkipValidation($commitMessage)) {
                return new ValidationResult(true, [], $commitMessage->getType(), $commitMessage->getScope());
            }

            // Validate commit message format
            $errors = array_merge($errors, $this->validateFormat($commitMessage, $config));

            // Only continue with other validations if format is valid
            if (!empty($errors)) {
                return new ValidationResult(false, $errors, null, null);
            }

            // Validate type
            $errors = array_merge($errors, $this->validateType($commitMessage, $config));

            // Validate scope
            $errors = array_merge($errors, $this->validateScope($commitMessage, $config));

            // Validate subject
            $errors = array_merge($errors, $this->validateSubject($commitMessage, $config));

            // Validate body
            $errors = array_merge($errors, $this->validateBody($commitMessage, $config));

            // Validate footer
            $errors = array_merge($errors, $this->validateFooter($commitMessage, $config));

            // Custom pattern validation
            $errors = array_merge($errors, $this->validatePatterns($commitMessage, $config));

            $isValid = empty($errors);

            return new ValidationResult(
                $isValid,
                $errors,
                $commitMessage->getType(),
                $commitMessage->getScope()
            );
        } catch (\Throwable $e) {
            return new ValidationResult(false, ['Validation error: ' . $e->getMessage()], null, null);
        }
    }

    private function shouldSkipValidation(CommitMessage $commitMessage): bool
    {
        $message = $commitMessage->getRawMessage();

        // Skip merge commits
        if (str_starts_with($message, 'Merge ')) {
            return true;
        }

        // Skip revert commits
        if (str_starts_with($message, 'Revert ')) {
            return true;
        }

        // Skip initial commits
        if (str_starts_with($message, 'Initial commit')) {
            return true;
        }

        // Skip fixup and squash commits
        if (str_starts_with($message, 'fixup!') || str_starts_with($message, 'squash!')) {
            return true;
        }

        return false;
    }

    private function validateFormat(CommitMessage $commitMessage, array $config): array
    {
        $errors = [];
        $message = $commitMessage->getRawMessage();

        // Check if message is empty
        if (empty(trim($message))) {
            $errors[] = 'Commit message cannot be empty';

            return $errors;
        }

        // Check conventional commit format if required
        if ($config['format']['type'] ?? true) {
            if (!$commitMessage->hasValidFormat()) {
                $errors[] = 'Commit message must follow conventional commit format: type(scope): description';
            }
        }

        return $errors;
    }

    private function validateType(CommitMessage $commitMessage, array $config): array
    {
        $errors = [];
        $type = $commitMessage->getType();
        $typeConfig = $config['rules']['type'] ?? [];

        if ($typeConfig['required'] ?? true) {
            if (empty($type)) {
                $errors[] = 'Commit type is required';

                return $errors;
            }

            $allowedTypes = $typeConfig['allowed'] ?? [];
            if (!empty($allowedTypes) && !in_array($type, $allowedTypes)) {
                $errors[] = sprintf(
                    'Invalid commit type "%s". Allowed types: %s',
                    $type,
                    implode(', ', $allowedTypes)
                );
            }
        }

        return $errors;
    }

    private function validateScope(CommitMessage $commitMessage, array $config): array
    {
        $errors = [];
        $scope = $commitMessage->getScope();
        $scopeConfig = $config['rules']['scope'] ?? [];

        if ($scopeConfig['required'] ?? false) {
            if (empty($scope)) {
                $errors[] = 'Commit scope is required';

                return $errors;
            }
        }

        if (!empty($scope)) {
            $allowedScopes = $scopeConfig['allowed'] ?? [];
            if (!empty($allowedScopes) && !in_array($scope, $allowedScopes)) {
                $errors[] = sprintf(
                    'Invalid commit scope "%s". Allowed scopes: %s',
                    $scope,
                    implode(', ', $allowedScopes)
                );
            }
        }

        return $errors;
    }

    private function validateSubject(CommitMessage $commitMessage, array $config): array
    {
        $errors = [];
        $subject = $commitMessage->getSubject();
        $subjectConfig = $config['rules']['subject'] ?? [];

        if (empty($subject)) {
            $errors[] = 'Commit subject is required';

            return $errors;
        }

        // Length validation
        $minLength = $subjectConfig['min_length'] ?? 1;
        $maxLength = $subjectConfig['max_length'] ?? 100;

        if (strlen($subject) < $minLength) {
            $errors[] = sprintf('Subject must be at least %d characters long', $minLength);
        }

        if (strlen($subject) > $maxLength) {
            $errors[] = sprintf('Subject must not exceed %d characters', $maxLength);
        }

        // Case validation
        $case = $subjectConfig['case'] ?? 'any';
        if ($case === 'lower' && $subject !== strtolower($subject)) {
            $errors[] = 'Subject must be in lowercase';
        } elseif ($case === 'upper' && $subject !== strtoupper($subject)) {
            $errors[] = 'Subject must be in uppercase';
        }

        // Period validation
        $endWithPeriod = $subjectConfig['end_with_period'] ?? false;
        if (!$endWithPeriod && str_ends_with($subject, '.')) {
            $errors[] = 'Subject must not end with a period';
        } elseif ($endWithPeriod && !str_ends_with($subject, '.')) {
            $errors[] = 'Subject must end with a period';
        }

        return $errors;
    }

    private function validateBody(CommitMessage $commitMessage, array $config): array
    {
        $errors = [];
        $body = $commitMessage->getBody();
        $bodyConfig = $config['rules']['body'] ?? [];

        if (empty($body)) {
            return $errors; // Body is optional
        }

        // Line length validation
        $maxLineLength = $bodyConfig['max_line_length'] ?? 100;
        $lines = explode("\n", $body);

        foreach ($lines as $lineNumber => $line) {
            if (strlen($line) > $maxLineLength) {
                $errors[] = sprintf(
                    'Body line %d exceeds maximum length of %d characters',
                    $lineNumber + 1,
                    $maxLineLength
                );
            }
        }

        // Leading blank line validation
        $leadingBlank = $bodyConfig['leading_blank'] ?? true;
        if ($leadingBlank && !$commitMessage->hasBlankLineAfterSubject()) {
            $errors[] = 'Body must be separated from subject by a blank line';
        }

        return $errors;
    }

    private function validateFooter(CommitMessage $commitMessage, array $config): array
    {
        $errors = [];
        $footer = $commitMessage->getFooter();
        $footerConfig = $config['rules']['footer'] ?? [];

        if (empty($footer)) {
            return $errors; // Footer is optional
        }

        // Leading blank line validation
        $leadingBlank = $footerConfig['leading_blank'] ?? true;
        if ($leadingBlank && !$commitMessage->hasBlankLineBeforeFooter()) {
            $errors[] = 'Footer must be separated from body by a blank line';
        }

        return $errors;
    }

    private function validatePatterns(CommitMessage $commitMessage, array $config): array
    {
        $errors = [];
        $patterns = $config['patterns'] ?? [];
        $message = $commitMessage->getRawMessage();

        foreach ($patterns as $name => $pattern) {
            if (is_string($pattern) && !preg_match($pattern, $message)) {
                // This is for informational patterns, not strict validation
                continue;
            }
        }

        return $errors;
    }
}
