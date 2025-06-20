<?php

declare(strict_types=1);

use DevKraken\PhpCommitlint\Models\CommitMessage;

describe('CommitMessage', function () {
    it('parses a simple conventional commit', function () {
        $message = CommitMessage::fromString('feat: add user authentication');

        expect($message->getType())->toBe('feat');
        expect($message->getScope())->toBeNull();
        expect($message->getSubject())->toBe('add user authentication');
        expect($message->getBody())->toBeNull();
        expect($message->getFooter())->toBeNull();
    });

    it('parses a commit with scope', function () {
        $message = CommitMessage::fromString('feat(auth): add user authentication');

        expect($message->getType())->toBe('feat');
        expect($message->getScope())->toBe('auth');
        expect($message->getSubject())->toBe('add user authentication');
    });

    it('parses a multi-line commit message', function () {
        $commitText = "feat(auth): add user authentication\n\nThis adds a complete authentication system\nwith login and registration.\n\nCloses #123";
        $message = CommitMessage::fromString($commitText);

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
            $message = CommitMessage::fromString($msg);
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
            $message = CommitMessage::fromString($msg);
            expect($message->hasValidFormat())->toBeFalse("Should have failed for: {$msg}");
        }
    });

    it('detects blank line after subject', function () {
        $withBlankLine = "feat: add feature\n\nThis is the body";
        $withoutBlankLine = "feat: add feature\nThis is the body";

        $message1 = CommitMessage::fromString($withBlankLine);
        $message2 = CommitMessage::fromString($withoutBlankLine);

        expect($message1->hasBlankLineAfterSubject())->toBeTrue();
        expect($message2->hasBlankLineAfterSubject())->toBeFalse();
    });

    it('identifies footer lines correctly', function () {
        $commitText = "feat: add feature\n\nBody content here\n\nCloses #123\nSigned-off-by: John Doe <john@example.com>";
        $message = CommitMessage::fromString($commitText);

        expect($message->getFooter())->toBe("Closes #123\nSigned-off-by: John Doe <john@example.com>");
    });

    it('handles breaking change footers', function () {
        $commitText = "feat: add new API\n\nThis changes the API\n\nBREAKING CHANGE: API endpoint /users is now /api/users";
        $message = CommitMessage::fromString($commitText);

        expect($message->getFooter())->toBe('BREAKING CHANGE: API endpoint /users is now /api/users');
    });

    it('handles commits without conventional format gracefully', function () {
        $message = CommitMessage::fromString('Just a regular commit message');

        expect($message->getType())->toBeNull();
        expect($message->getScope())->toBeNull();
        expect($message->getSubject())->toBe('Just a regular commit message');
    });

    it('trims whitespace from messages', function () {
        $message = CommitMessage::fromString("  feat: add feature  \n  ");

        expect($message->getSubject())->toBe('add feature');
    });

    it('provides utility methods for commit detection', function () {
        expect(CommitMessage::fromString('Merge branch "feature" into main')->isMergeCommit())->toBeTrue();
        expect(CommitMessage::fromString('Revert "feat: add feature"')->isRevertCommit())->toBeTrue();
        expect(CommitMessage::fromString('Initial commit')->isInitialCommit())->toBeTrue();
        expect(CommitMessage::fromString('fixup! feat: add feature')->isFixupCommit())->toBeTrue();
        expect(CommitMessage::fromString('squash! feat: add feature')->isFixupCommit())->toBeTrue();

        expect(CommitMessage::fromString('feat: add feature')->isMergeCommit())->toBeFalse();
        expect(CommitMessage::fromString('feat: add feature')->isRevertCommit())->toBeFalse();
        expect(CommitMessage::fromString('feat: add feature')->isInitialCommit())->toBeFalse();
        expect(CommitMessage::fromString('feat: add feature')->isFixupCommit())->toBeFalse();
    });

    it('provides shouldSkipValidation method', function () {
        expect(CommitMessage::fromString('Merge branch "feature" into main')->shouldSkipValidation())->toBeTrue();
        expect(CommitMessage::fromString('Revert "feat: add feature"')->shouldSkipValidation())->toBeTrue();
        expect(CommitMessage::fromString('Initial commit')->shouldSkipValidation())->toBeTrue();
        expect(CommitMessage::fromString('fixup! feat: add feature')->shouldSkipValidation())->toBeTrue();

        expect(CommitMessage::fromString('feat: add feature')->shouldSkipValidation())->toBeFalse();
    });

    it('provides message statistics', function () {
        $message = CommitMessage::fromString('feat: add user authentication system');

        expect($message->getWordCount())->toBeGreaterThan(0);
        expect($message->getCharacterCount())->toBeGreaterThan(0);
        expect($message->getSubjectLength())->toBe(strlen('add user authentication system'));
    });

    it('supports immutable updates', function () {
        $original = CommitMessage::fromString('feat: add feature');
        $withScope = $original->withScope('auth');
        $withType = $original->withType('fix');

        // Original should be unchanged
        expect($original->getScope())->toBeNull();
        expect($original->getType())->toBe('feat');

        // New instances should have updates
        expect($withScope->getScope())->toBe('auth');
        expect($withScope->getType())->toBe('feat'); // type unchanged

        expect($withType->getType())->toBe('fix');
        expect($withType->getScope())->toBeNull(); // scope unchanged
    });

    it('can create from file', function () {
        $tempFile = tempnam(sys_get_temp_dir(), 'commit_test');
        file_put_contents($tempFile, 'feat: add feature from file');

        $message = CommitMessage::fromFile($tempFile);

        expect($message->getType())->toBe('feat');
        expect($message->getSubject())->toBe('add feature from file');

        unlink($tempFile);
    });

    it('throws exception for non-existent file', function () {
        expect(fn () => CommitMessage::fromFile('/non/existent/file.txt'))
            ->toThrow(InvalidArgumentException::class, 'File not found: /non/existent/file.txt');
    });

    it('handles empty messages', function () {
        $message = CommitMessage::fromString('');

        expect($message->isEmpty())->toBeTrue();
        expect($message->getType())->toBeNull();
        expect($message->getSubject())->toBeNull();
    });

    it('identifies additional footer patterns', function () {
        $footerPatterns = [
            "feat: add feature\n\nBody\n\nReviewed-by: Jane Doe <jane@example.com>",
            "feat: add feature\n\nBody\n\nAcked-by: John Smith <john@example.com>",
            "feat: add feature\n\nBody\n\nTested-by: Test Bot <test@example.com>",
        ];

        foreach ($footerPatterns as $pattern) {
            $message = CommitMessage::fromString($pattern);
            expect($message->getFooter())->not->toBeNull();
        }
    });
});
