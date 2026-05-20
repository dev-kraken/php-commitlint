<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Services;

use DevKraken\PhpCommitlint\Models\CommitMessage;
use DevKraken\PhpCommitlint\Models\ValidationResult;
use Throwable;

class ValidationService
{
    private const int MAX_MESSAGE_LENGTH = 10_000;
    private const int DEFAULT_SUBJECT_MIN_LENGTH = 1;
    private const int DEFAULT_SUBJECT_MAX_LENGTH = 100;
    private const int DEFAULT_BODY_MAX_LINE_LENGTH = 100;

    /**
     * @param array<string, mixed> $config
     */
    public function validate(string $message, array $config): ValidationResult
    {
        if (trim($message) === '') {
            return ValidationResult::error('Commit message cannot be empty');
        }

        if (strlen($message) > self::MAX_MESSAGE_LENGTH) {
            return ValidationResult::error(sprintf(
                'Commit message too long (max %s characters)',
                number_format(self::MAX_MESSAGE_LENGTH)
            ));
        }

        try {
            $commitMessage = CommitMessage::fromString($message);

            if ($commitMessage->shouldSkipValidation()) {
                return ValidationResult::valid($commitMessage->getType(), $commitMessage->getScope());
            }

            return $this->validateCommitMessage($commitMessage, $config);
        } catch (Throwable $e) {
            return ValidationResult::error('Validation error: ' . $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function validateCommitMessage(CommitMessage $commitMessage, array $config): ValidationResult
    {
        $errors = [
            ...$this->validateFormat($commitMessage, $config),
            ...$this->validateType($commitMessage, $config),
            ...$this->validateScope($commitMessage, $config),
            ...$this->validateSubject($commitMessage, $config),
            ...$this->validateBody($commitMessage, $config),
            ...$this->validateFooter($commitMessage, $config),
        ];

        return $errors === []
            ? ValidationResult::valid($commitMessage->getType(), $commitMessage->getScope())
            : ValidationResult::invalid($errors, $commitMessage->getType(), $commitMessage->getScope());
    }

    /**
     * @param array<string, mixed> $config
     * @return list<string>
     */
    private function validateFormat(CommitMessage $commitMessage, array $config): array
    {
        $formatConfig = $this->arrayValue($config, 'format');

        if (isset($formatConfig['enabled']) && !$formatConfig['enabled']) {
            return [];
        }

        if (!($formatConfig['conventional'] ?? true)) {
            return [];
        }

        if (!$commitMessage->hasValidFormat()) {
            return ['Commit message must follow conventional commit format: type(scope): description'];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $config
     * @return list<string>
     */
    private function validateType(CommitMessage $commitMessage, array $config): array
    {
        $typeConfig = $this->arrayValue($config, 'rules', 'type');

        if (!($typeConfig['required'] ?? true)) {
            return [];
        }

        $type = $commitMessage->getType();
        if ($type === null || $type === '') {
            return ['Commit type is required'];
        }

        $allowedTypes = $this->stringListOption($typeConfig, 'allowed');
        if ($allowedTypes !== [] && !in_array($type, $allowedTypes, true)) {
            return [sprintf(
                'Invalid commit type "%s". Allowed types: %s',
                $type,
                implode(', ', $allowedTypes)
            )];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $config
     * @return list<string>
     */
    private function validateScope(CommitMessage $commitMessage, array $config): array
    {
        $scopeConfig = $this->arrayValue($config, 'rules', 'scope');
        $scope = $commitMessage->getScope();

        if (($scopeConfig['required'] ?? false) && ($scope === null || $scope === '')) {
            return ['Commit scope is required'];
        }

        if ($scope === null || $scope === '') {
            return [];
        }

        $allowedScopes = $this->stringListOption($scopeConfig, 'allowed');
        if ($allowedScopes !== [] && !in_array($scope, $allowedScopes, true)) {
            return [sprintf(
                'Invalid commit scope "%s". Allowed scopes: %s',
                $scope,
                implode(', ', $allowedScopes)
            )];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $config
     * @return list<string>
     */
    private function validateSubject(CommitMessage $commitMessage, array $config): array
    {
        $subjectConfig = $this->arrayValue($config, 'rules', 'subject');
        $subject = $commitMessage->getSubject();

        if ($subject === null || $subject === '') {
            return ['Commit subject is required'];
        }

        $errors = [];
        $length = $commitMessage->getSubjectLength();
        $minLength = $this->intOption($subjectConfig, 'min_length', self::DEFAULT_SUBJECT_MIN_LENGTH);
        $maxLength = $this->intOption($subjectConfig, 'max_length', self::DEFAULT_SUBJECT_MAX_LENGTH);

        if ($length < $minLength) {
            $errors[] = sprintf('Subject too short (minimum %d characters)', $minLength);
        }

        if ($length > $maxLength) {
            $errors[] = sprintf('Subject too long (maximum %d characters)', $maxLength);
        }

        $case = $this->stringOption($subjectConfig, 'case', 'any');
        if ($case !== 'any' && ($caseError = $this->validateSubjectCase($subject, $case)) !== null) {
            $errors[] = $caseError;
        }

        $shouldEndWithPeriod = (bool) ($subjectConfig['end_with_period'] ?? false);
        $endsWithPeriod = str_ends_with($subject, '.');

        if ($shouldEndWithPeriod && !$endsWithPeriod) {
            $errors[] = 'Subject must end with a period';
        } elseif (!$shouldEndWithPeriod && $endsWithPeriod) {
            $errors[] = 'Subject must not end with a period';
        }

        return $errors;
    }

    private function validateSubjectCase(string $subject, string $expectedCase): ?string
    {
        return match ($expectedCase) {
            'lower' => ctype_lower($subject[0]) ? null : 'Subject must start with lowercase letter',
            'upper' => ctype_upper($subject[0]) ? null : 'Subject must start with uppercase letter',
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $config
     * @return list<string>
     */
    private function validateBody(CommitMessage $commitMessage, array $config): array
    {
        $body = $commitMessage->getBody();
        if ($body === null || $body === '') {
            return [];
        }

        $bodyConfig = $this->arrayValue($config, 'rules', 'body');
        $errors = [];

        if (($bodyConfig['leading_blank'] ?? true) && !$commitMessage->hasBlankLineAfterSubject()) {
            $errors[] = 'Body must be separated from subject by a blank line';
        }

        $maxLineLength = $this->intOption($bodyConfig, 'max_line_length', self::DEFAULT_BODY_MAX_LINE_LENGTH);
        if ($maxLineLength > 0) {
            foreach (explode("\n", $body) as $lineNumber => $line) {
                if (mb_strlen($line) > $maxLineLength) {
                    $errors[] = sprintf(
                        'Body line %d exceeds maximum length of %d characters',
                        $lineNumber + 1,
                        $maxLineLength
                    );
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $config
     * @return list<string>
     */
    private function validateFooter(CommitMessage $commitMessage, array $config): array
    {
        $footer = $commitMessage->getFooter();
        if ($footer === null || $footer === '') {
            return [];
        }

        $footerConfig = $this->arrayValue($config, 'rules', 'footer');

        if (($footerConfig['leading_blank'] ?? true) && !$commitMessage->hasBlankLineBeforeFooter()) {
            return ['Footer must be separated from body by a blank line'];
        }

        return [];
    }

    /**
     * @param array<string, mixed> $config
     */
    private function intOption(array $config, string $key, int $default): int
    {
        $value = $config[$key] ?? null;

        return is_int($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function stringOption(array $config, string $key, string $default): string
    {
        $value = $config[$key] ?? null;

        return is_string($value) ? $value : $default;
    }

    /**
     * @param array<string, mixed> $config
     * @return list<string>
     */
    private function stringListOption(array $config, string $key): array
    {
        $value = $config[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private function arrayValue(array $config, string ...$keys): array
    {
        $current = $config;
        foreach ($keys as $key) {
            if (!array_key_exists($key, $current) || !is_array($current[$key])) {
                return [];
            }
            /** @var array<string, mixed> $current */
            $current = $current[$key];
        }

        /** @var array<string, mixed> $current */
        return $current;
    }
}
