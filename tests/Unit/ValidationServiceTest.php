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

        expect($result->isValid())->toBeTrue();
        expect($result->getType())->toBe('feat');
        expect($result->getScope())->toBeNull();
        expect($result->getErrors())->toBeEmpty();
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
        $config = createConfig();
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
        expect($result->getErrors())->toContain('Subject must be at least 10 characters long');

        // Too long
        $result = $this->validator->validate('feat: this is a very long subject that exceeds the maximum length', $config);
        expect($result->isValid())->toBeFalse();
        expect($result->getErrors())->toContain('Subject must not exceed 20 characters');

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
        expect($result->getErrors())->toContain('Subject must be in lowercase');

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
});
