<?php

declare(strict_types=1);

use DevKraken\PhpCommitlint\Models\CommitMessage;

describe('CommitMessage', function () {
    it('parses a simple conventional commit', function () {
        $message = new CommitMessage('feat: add user authentication');

        expect($message->getType())->toBe('feat');
        expect($message->getScope())->toBeNull();
        expect($message->getSubject())->toBe('add user authentication');
        expect($message->getBody())->toBeNull();
        expect($message->getFooter())->toBeNull();
    });

    it('parses a commit with scope', function () {
        $message = new CommitMessage('feat(auth): add user authentication');

        expect($message->getType())->toBe('feat');
        expect($message->getScope())->toBe('auth');
        expect($message->getSubject())->toBe('add user authentication');
    });

    it('parses a multi-line commit message', function () {
        $commitText = "feat(auth): add user authentication\n\nThis adds a complete authentication system\nwith login and registration.\n\nCloses #123";
        $message = new CommitMessage($commitText);

        expect($message->getType())->toBe('feat');
        expect($message->getScope())->toBe('auth');
        expect($message->getSubject())->toBe('add user authentication');
        expect($message->getBody())->toBe("This adds a complete authentication system\nwith login and registration.");
        expect($message->getFooter())->toBe('Closes #123');
    });

    it('detects valid conventional commit format', function () {
        $validMessages = [
            'feat: add feature',
            'fix(auth): resolve login issue',
            'docs: update readme',
            'style: format code',
        ];

        foreach ($validMessages as $msg) {
            $message = new CommitMessage($msg);
            expect($message->hasValidFormat())->toBeTrue("Failed for: {$msg}");
        }
    });

    it('detects invalid conventional commit format', function () {
        $invalidMessages = [
            'Add feature',
            'FEAT: add feature',
            'feat add feature',
            '123: invalid type',
        ];

        foreach ($invalidMessages as $msg) {
            $message = new CommitMessage($msg);
            expect($message->hasValidFormat())->toBeFalse("Should have failed for: {$msg}");
        }
    });

    it('detects blank line after subject', function () {
        $withBlankLine = "feat: add feature\n\nThis is the body";
        $withoutBlankLine = "feat: add feature\nThis is the body";

        $message1 = new CommitMessage($withBlankLine);
        $message2 = new CommitMessage($withoutBlankLine);

        expect($message1->hasBlankLineAfterSubject())->toBeTrue();
        expect($message2->hasBlankLineAfterSubject())->toBeFalse();
    });

    it('identifies footer lines correctly', function () {
        $commitText = "feat: add feature\n\nBody content here\n\nCloses #123\nSigned-off-by: John Doe <john@example.com>";
        $message = new CommitMessage($commitText);

        expect($message->getFooter())->toBe("Closes #123\nSigned-off-by: John Doe <john@example.com>");
    });

    it('handles breaking change footers', function () {
        $commitText = "feat: add new API\n\nThis changes the API\n\nBREAKING CHANGE: API endpoint /users is now /api/users";
        $message = new CommitMessage($commitText);

        expect($message->getFooter())->toBe('BREAKING CHANGE: API endpoint /users is now /api/users');
    });

    it('handles commits without conventional format gracefully', function () {
        $message = new CommitMessage('Just a regular commit message');

        expect($message->getType())->toBeNull();
        expect($message->getScope())->toBeNull();
        expect($message->getSubject())->toBe('Just a regular commit message');
    });

    it('trims whitespace from messages', function () {
        $message = new CommitMessage("  feat: add feature  \n  ");

        expect($message->getRawMessage())->toBe('feat: add feature');
        expect($message->getSubject())->toBe('add feature');
    });
});
