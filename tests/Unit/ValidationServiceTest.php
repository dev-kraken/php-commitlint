<?php

declare(strict_types=1);

use DevKraken\PhpCommitlint\Services\ValidationService;

beforeEach(function () {
    $this->validator = new ValidationService();
});

describe('ValidationService', function () {
    it('validates a valid conventional commit message', function () {
        $config = createConfig();
        $message = 'feat: add user authentication';

        $result = $this->validator->validate($message, $config);

        expect($result->isValid())->toBeTrue()
            ->and($result->getType())->toBe('feat')
            ->and($result->getScope())->toBeNull()
            ->and($result->getErrors())->toBeEmpty();
    });

    it('validates a valid commit message with scope', function () {
        $config = createConfig();
        $message = 'feat(auth): add user authentication';

        $result = $this->validator->validate($message, $config);

        expect($result->isValid())->toBeTrue();
        expect($result->getType())->toBe('feat');
        expect($result->getScope())->toBe('auth');
        expect($result->getErrors())->toBeEmpty();
    });

    it('rejects invalid commit type', function () {
        $config = createConfig();
        $message = 'invalid: add user authentication';

        $result = $this->validator->validate($message, $config);

        expect($result->isValid())->toBeFalse();
        expect($result->getErrors())->toContain('Invalid commit type "invalid". Allowed types: feat, fix, docs, style, refactor, test, chore');
    });

    it('rejects commit message without type', function () {
        $config = createConfig([
            'format' => [
                'enabled' => true,
                'conventional' => true,
            ],
        ]);
        $message = 'add user authentication';

        $result = $this->validator->validate($message, $config);

        expect($result->isValid())->toBeFalse();
        expect($result->getErrors())->toContain('Commit message must follow conventional commit format: type(scope): description');
    });

    it('rejects empty commit message', function () {
        $config = createConfig();
        $message = '';

        $result = $this->validator->validate($message, $config);

        expect($result->isValid())->toBeFalse();
        expect($result->getErrors())->toContain('Commit message cannot be empty');
    });

    it('validates subject length constraints', function () {
        $config = createConfig([
            'rules' => [
                'subject' => [
                    'min_length' => 10,
                    'max_length' => 20,
                ],
            ],
        ]);

        // Too short
        $result = $this->validator->validate('feat: short', $config);
        expect($result->isValid())->toBeFalse();
        expect($result->getErrors())->toContain('Subject too short (minimum 10 characters)');

        // Too long
        $result = $this->validator->validate('feat: this is a very long subject that exceeds the maximum length', $config);
        expect($result->isValid())->toBeFalse();
        expect($result->getErrors())->toContain('Subject too long (maximum 20 characters)');

        // Just right
        $result = $this->validator->validate('feat: perfect length', $config);
        expect($result->isValid())->toBeTrue();
    });

    it('validates subject case requirements', function () {
        $config = createConfig([
            'rules' => [
                'subject' => [
                    'case' => 'lower',
                ],
            ],
        ]);

        $result = $this->validator->validate('feat: Add User Auth', $config);
        expect($result->isValid())->toBeFalse();
        expect($result->getErrors())->toContain('Subject must start with lowercase letter');

        $result = $this->validator->validate('feat: add user auth', $config);
        expect($result->isValid())->toBeTrue();
    });

    it('validates period ending rules', function () {
        $config = createConfig([
            'rules' => [
                'subject' => [
                    'end_with_period' => false,
                ],
            ],
        ]);

        $result = $this->validator->validate('feat: add user auth.', $config);
        expect($result->isValid())->toBeFalse();
        expect($result->getErrors())->toContain('Subject must not end with a period');

        $result = $this->validator->validate('feat: add user auth', $config);
        expect($result->isValid())->toBeTrue();
    });

    it('skips validation for merge commits', function () {
        $config = createConfig();
        $message = 'Merge branch "feature/auth" into main';

        $result = $this->validator->validate($message, $config);

        expect($result->isValid())->toBeTrue();
        expect($result->getErrors())->toBeEmpty();
    });

    it('skips validation for revert commits', function () {
        $config = createConfig();
        $message = 'Revert "feat: add user authentication"';

        $result = $this->validator->validate($message, $config);

        expect($result->isValid())->toBeTrue();
        expect($result->getErrors())->toBeEmpty();
    });

    it('skips validation for initial commits', function () {
        $config = createConfig();
        $message = 'Initial commit';

        $result = $this->validator->validate($message, $config);

        expect($result->isValid())->toBeTrue();
        expect($result->getErrors())->toBeEmpty();
    });

    it('skips validation for fixup commits', function () {
        $config = createConfig();
        $message = 'fixup! feat: add user authentication';

        $result = $this->validator->validate($message, $config);

        expect($result->isValid())->toBeTrue();
        expect($result->getErrors())->toBeEmpty();
    });

    it('validates scope when required', function () {
        $config = createConfig([
            'rules' => [
                'scope' => [
                    'required' => true,
                    'allowed' => ['auth', 'ui', 'api'],
                ],
            ],
        ]);

        // Missing scope
        $result = $this->validator->validate('feat: add authentication', $config);
        expect($result->isValid())->toBeFalse();
        expect($result->getErrors())->toContain('Commit scope is required');

        // Invalid scope
        $result = $this->validator->validate('feat(invalid): add authentication', $config);
        expect($result->isValid())->toBeFalse();
        expect($result->getErrors())->toContain('Invalid commit scope "invalid". Allowed scopes: auth, ui, api');

        // Valid scope
        $result = $this->validator->validate('feat(auth): add authentication', $config);
        expect($result->isValid())->toBeTrue();
    });

    it('validates multi-line commit messages', function () {
        $config = createConfig();
        $message = "feat: add user authentication\n\nThis adds a complete authentication system\nwith login and registration features.\n\nCloses #123";

        $result = $this->validator->validate($message, $config);

        expect($result->isValid())->toBeTrue();
        expect($result->getType())->toBe('feat');
    });

    it('validates body line length constraints', function () {
        $config = createConfig([
            'rules' => [
                'body' => [
                    'max_line_length' => 50,
                ],
            ],
        ]);

        $message = "feat: add auth\n\nThis is a very long line that exceeds the maximum allowed length for body lines and should fail validation";

        $result = $this->validator->validate($message, $config);
        expect($result->isValid())->toBeFalse();
        expect($result->getErrors())->toContain('Body line 1 exceeds maximum length of 50 characters');
    });

    it('validates body leading blank line requirement', function () {
        $config = createConfig([
            'rules' => [
                'body' => [
                    'leading_blank' => true,
                ],
            ],
        ]);

        $messageWithoutBlank = "feat: add auth\nThis body has no leading blank line";
        $result = $this->validator->validate($messageWithoutBlank, $config);
        expect($result->isValid())->toBeFalse();
        expect($result->getErrors())->toContain('Body must be separated from subject by a blank line');

        $messageWithBlank = "feat: add auth\n\nThis body has proper leading blank line";
        $result = $this->validator->validate($messageWithBlank, $config);
        expect($result->isValid())->toBeTrue();
    });

    it('validates footer leading blank line requirement', function () {
        $config = createConfig([
            'rules' => [
                'footer' => [
                    'leading_blank' => true,
                ],
            ],
        ]);

        $messageWithoutBlank = "feat: add auth\n\nBody content\nCloses #123";
        $result = $this->validator->validate($messageWithoutBlank, $config);
        expect($result->isValid())->toBeFalse();
        expect($result->getErrors())->toContain('Footer must be separated from body by a blank line');

        $messageWithBlank = "feat: add auth\n\nBody content\n\nCloses #123";
        $result = $this->validator->validate($messageWithBlank, $config);
        expect($result->isValid())->toBeTrue();
    });

    it('validates extremely long messages', function () {
        $config = createConfig();
        $longMessage = 'feat: ' . str_repeat('a', 10000);

        $result = $this->validator->validate($longMessage, $config);
        expect($result->isValid())->toBeFalse();
        expect($result->getErrors())->toContain('Commit message too long (max 10,000 characters)');
    });
});
