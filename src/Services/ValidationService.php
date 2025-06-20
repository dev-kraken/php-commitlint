<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Services;

use DevKraken\PhpCommitlint\Models\CommitMessage;
use DevKraken\PhpCommitlint\Models\ValidationResult;
use Throwable;

class ValidationService
{
    private const int MAX_MESSAGE_LENGTH = 10000;

    public function validate(string $message, array $config): ValidationResult
    {
        if (empty(trim($message))) {
            return ValidationResult::error('Commit message cannot be empty');
        }

        if (strlen($message) > self::MAX_MESSAGE_LENGTH) {
            return ValidationResult::error('Commit message too long (max ' . number_format(self::MAX_MESSAGE_LENGTH) . ' characters)');
        }

        try {
            $commitMessage = CommitMessage::fromString($message);

            // Skip validation for special commits
            if ($commitMessage->shouldSkipValidation()) {
                return ValidationResult::valid($commitMessage->getType(), $commitMessage->getScope());
            }

            return $this->validateCommitMessage($commitMessage, $config);
        } catch (Throwable $e) {
            return ValidationResult::error('Validation error: ' . $e->getMessage());
        }
    }

    private function validateCommitMessage(CommitMessage $commitMessage, array $config): ValidationResult
    {
        $errors = [];

        $errors = array_merge($errors, $this->validateFormat($commitMessage, $config));
        $errors = array_merge($errors, $this->validateType($commitMessage, $config));
        $errors = array_merge($errors, $this->validateScope($commitMessage, $config));
        $errors = array_merge($errors, $this->validateSubject($commitMessage, $config));
        $errors = array_merge($errors, $this->validateBody($commitMessage, $config));
        $errors = array_merge($errors, $this->validateFooter($commitMessage, $config));
        $errors = array_merge($errors, $this->validatePatterns($commitMessage, $config));

        return empty($errors)
            ? ValidationResult::valid($commitMessage->getType(), $commitMessage->getScope())
            : ValidationResult::invalid($errors, $commitMessage->getType(), $commitMessage->getScope());
    }

    /**
     * @return list<string>
     */
    private function validateFormat(CommitMessage $commitMessage, array $config): array
    {
        // Check if format validation is enabled (default: true)
        $formatConfig = $config['format'] ?? [];
        if (isset($formatConfig['enabled']) && !$formatConfig['enabled']) {
            return [];
        }

        // Check if conventional format is required (default: true)
        if (!($formatConfig['conventional'] ?? true)) {
            return [];
        }

        if (!$commitMessage->hasValidFormat()) {
            return ['Commit message must follow conventional commit format: type(scope): description'];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function validateType(CommitMessage $commitMessage, array $config): array
    {
        $typeConfig = $config['rules']['type'] ?? [];
        $type = $commitMessage->getType();

        if (!($typeConfig['required'] ?? true)) {
            return [];
        }

        if (empty($type)) {
            return ['Commit type is required'];
        }

        $allowedTypes = $typeConfig['allowed'] ?? [];
        if (!empty($allowedTypes) && !in_array($type, $allowedTypes, true)) {
            return [sprintf(
                'Invalid commit type "%s". Allowed types: %s',
                $type,
                implode(', ', $allowedTypes)
            )];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function validateScope(CommitMessage $commitMessage, array $config): array
    {
        $scopeConfig = $config['rules']['scope'] ?? [];
        $scope = $commitMessage->getScope();

        if (($scopeConfig['required'] ?? false) && empty($scope)) {
            return ['Commit scope is required'];
        }

        if (!empty($scope)) {
            $allowedScopes = $scopeConfig['allowed'] ?? [];
            if (!empty($allowedScopes) && !in_array($scope, $allowedScopes, true)) {
                return [sprintf(
                    'Invalid commit scope "%s". Allowed scopes: %s',
                    $scope,
                    implode(', ', $allowedScopes)
                )];
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function validateSubject(CommitMessage $commitMessage, array $config): array
    {
        $subjectConfig = $config['rules']['subject'] ?? [];
        $subject = $commitMessage->getSubject();
        $errors = [];

        if (empty($subject)) {
            return ['Commit subject is required'];
        }

        $minLength = $subjectConfig['min_length'] ?? 1;
        $maxLength = $subjectConfig['max_length'] ?? 100;
        $subjectLength = $commitMessage->getSubjectLength();

        if ($subjectLength < $minLength) {
            $errors[] = sprintf('Subject too short (minimum %d characters)', $minLength);
        }

        if ($subjectLength > $maxLength) {
            $errors[] = sprintf('Subject too long (maximum %d characters)', $maxLength);
        }

        $case = $subjectConfig['case'] ?? 'any';
        if ($case !== 'any') {
            $errors = array_merge($errors, $this->validateSubjectCase($subject, $case));
        }

        if ($subjectConfig['end_with_period'] ?? false) {
            if (!str_ends_with($subject, '.')) {
                $errors[] = 'Subject must end with a period';
            }
        } else {
            if (str_ends_with($subject, '.')) {
                $errors[] = 'Subject must not end with a period';
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validateSubjectCase(string $subject, string $expectedCase): array
    {
        return match ($expectedCase) {
            'lower' => ctype_lower($subject[0]) ? [] : ['Subject must start with lowercase letter'],
            'upper' => ctype_upper($subject[0]) ? [] : ['Subject must start with uppercase letter'],
            default => []
        };
    }

    /**
     * @return list<string>
     */
    private function validateBody(CommitMessage $commitMessage, array $config): array
    {
        $bodyConfig = $config['rules']['body'] ?? [];
        $body = $commitMessage->getBody();
        $errors = [];

        if (empty($body)) {
            return [];
        }

        if ($bodyConfig['leading_blank'] ?? true) {
            if (!$commitMessage->hasBlankLineAfterSubject()) {
                $errors[] = 'Body must be separated from subject by a blank line';
            }
        }

        $maxLineLength = $bodyConfig['max_line_length'] ?? 100;
        if ($maxLineLength > 0) {
            $bodyLines = explode("\n", $body);
            foreach ($bodyLines as $lineNumber => $line) {
                if (mb_strlen($line) > $maxLineLength) {
                    $errors[] = sprintf('Body line %d exceeds maximum length of %d characters', $lineNumber + 1, $maxLineLength);
                }
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    private function validateFooter(CommitMessage $commitMessage, array $config): array
    {
        $footerConfig = $config['rules']['footer'] ?? [];
        $footer = $commitMessage->getFooter();

        if (empty($footer)) {
            return [];
        }

        if ($footerConfig['leading_blank'] ?? true) {
            if (!$commitMessage->hasBlankLineBeforeFooter()) {
                return ['Footer must be separated from body by a blank line'];
            }
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function validatePatterns(CommitMessage $commitMessage, array $config): array
    {
        $patterns = $config['patterns'] ?? [];
        $errors = [];
        $message = $commitMessage->getRawMessage();

        // Patterns are optional by default - they don't generate errors if they don't match
        // This method is kept for potential future use with required patterns
        return $errors;

        // Legacy code kept for reference (commented out):
        /*
        foreach ($patterns as $name => $pattern) {
            if (!is_string($pattern)) {
                continue;
            }

            try {
                if (!preg_match($pattern, $message)) {
                    $errors[] = sprintf('Message does not match required pattern: %s', $name);
                }
            } catch (Throwable $e) {
                $errors[] = sprintf('Invalid pattern "%s": %s', $name, $e->getMessage());
            }
        }
        */
    }
}
