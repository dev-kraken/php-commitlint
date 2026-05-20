<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Models;

use InvalidArgumentException;
use RuntimeException;

final class CommitMessage
{
    private const string CONVENTIONAL_COMMIT_PATTERN = '/^([a-z]+)(\(([^)]+)\))?:\s*(.+)$/';

    /** @var list<string> */
    private const array FOOTER_PATTERNS = [
        '/^BREAKING CHANGE:/',
        '/^(Closes?|Fixes?|Resolves?)\s+#\d+/i',
        '/^Refs?\s+#\d+/i',
        '/^Co-authored-by:/i',
        '/^Signed-off-by:/i',
        '/^Reviewed-by:/i',
        '/^Acked-by:/i',
        '/^Tested-by:/i',
    ];

    private readonly string $rawMessage;

    /** @var list<string> */
    private readonly array $lines;

    private readonly ?string $type;
    private readonly ?string $scope;
    private readonly ?string $subject;
    private readonly ?string $body;
    private readonly ?string $footer;

    public function __construct(
        string $rawMessage,
        ?string $type = null,
        ?string $scope = null,
        ?string $subject = null,
        ?string $body = null,
        ?string $footer = null,
    ) {
        $this->rawMessage = $this->sanitizeMessage($rawMessage);
        $this->lines = explode("\n", $this->rawMessage);

        $parsed = $this->parseMessage();

        $this->type = $type ?? $parsed['type'];
        $this->scope = $scope ?? $parsed['scope'];
        $this->subject = $subject ?? $parsed['subject'];
        $this->body = $body ?? $parsed['body'];
        $this->footer = $footer ?? $parsed['footer'];
    }

    public static function fromString(string $message): self
    {
        return new self(trim($message));
    }

    public static function fromFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("File not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Failed to read file: {$filePath}");
        }

        return new self($content);
    }

    public function getRawMessage(): string
    {
        return $this->rawMessage;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function getFooter(): ?string
    {
        return $this->footer;
    }

    /**
     * @return list<string>
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    public function hasValidFormat(): bool
    {
        return preg_match(self::CONVENTIONAL_COMMIT_PATTERN, $this->lines[0]) === 1;
    }

    public function hasBlankLineAfterSubject(): bool
    {
        return count($this->lines) > 1 && trim($this->lines[1]) === '';
    }

    public function hasBlankLineBeforeFooter(): bool
    {
        $footerStartLine = $this->findFooterStartLine();

        return $footerStartLine > 0
            && $footerStartLine < count($this->lines)
            && trim($this->lines[$footerStartLine - 1]) === '';
    }

    public function isEmpty(): bool
    {
        return trim($this->rawMessage) === '';
    }

    public function isMergeCommit(): bool
    {
        return str_starts_with($this->rawMessage, 'Merge ');
    }

    public function isRevertCommit(): bool
    {
        return str_starts_with($this->rawMessage, 'Revert ');
    }

    public function isInitialCommit(): bool
    {
        return str_starts_with($this->rawMessage, 'Initial commit');
    }

    public function isFixupCommit(): bool
    {
        return str_starts_with($this->rawMessage, 'fixup!') || str_starts_with($this->rawMessage, 'squash!');
    }

    public function shouldSkipValidation(): bool
    {
        return $this->isMergeCommit()
            || $this->isRevertCommit()
            || $this->isInitialCommit()
            || $this->isFixupCommit();
    }

    public function getWordCount(): int
    {
        return str_word_count($this->rawMessage);
    }

    public function getCharacterCount(): int
    {
        return mb_strlen($this->rawMessage);
    }

    public function getSubjectLength(): int
    {
        return $this->subject === null ? 0 : mb_strlen($this->subject);
    }

    public function withType(string $type): self
    {
        return new self($this->rawMessage, $type, $this->scope, $this->subject, $this->body, $this->footer);
    }

    public function withScope(?string $scope): self
    {
        return new self($this->rawMessage, $this->type, $scope, $this->subject, $this->body, $this->footer);
    }

    private function sanitizeMessage(string $message): string
    {
        $sanitized = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $message) ?? $message;
        $sanitized = str_replace(["\r\n", "\r"], "\n", $sanitized);

        return trim($sanitized);
    }

    /**
     * @return array{type: ?string, scope: ?string, subject: ?string, body: ?string, footer: ?string}
     */
    private function parseMessage(): array
    {
        $result = [
            'type' => null,
            'scope' => null,
            'subject' => null,
            'body' => null,
            'footer' => null,
        ];

        $header = $this->lines[0];
        if (preg_match(self::CONVENTIONAL_COMMIT_PATTERN, $header, $matches)) {
            $result['type'] = $matches[1];
            $result['scope'] = $matches[3] !== '' ? $matches[3] : null;
            $result['subject'] = $matches[4];
        } elseif (trim($header) !== '') {
            $result['subject'] = $header;
        }

        if (count($this->lines) > 1) {
            $parsed = $this->parseBodyAndFooter();
            $result['body'] = $parsed['body'];
            $result['footer'] = $parsed['footer'];
        }

        return $result;
    }

    /**
     * @return array{body: ?string, footer: ?string}
     */
    private function parseBodyAndFooter(): array
    {
        $bodyLines = [];
        $footerLines = [];
        $inFooter = false;
        $startFromLine = $this->hasBlankLineAfterSubject() ? 2 : 1;
        $totalLines = count($this->lines);

        for ($i = $startFromLine; $i < $totalLines; $i++) {
            $line = $this->lines[$i];

            if (!$inFooter && $this->isFooterLine($line)) {
                $inFooter = true;
            }

            if ($inFooter) {
                $footerLines[] = $line;
            } else {
                $bodyLines[] = $line;
            }
        }

        return [
            'body' => $this->joinLinesTrimmed($bodyLines),
            'footer' => $footerLines === [] ? null : implode("\n", $footerLines),
        ];
    }

    /**
     * @param list<string> $lines
     */
    private function joinLinesTrimmed(array $lines): ?string
    {
        while ($lines !== [] && trim(end($lines)) === '') {
            array_pop($lines);
        }

        return $lines === [] ? null : implode("\n", $lines);
    }

    private function isFooterLine(string $line): bool
    {
        foreach (self::FOOTER_PATTERNS as $pattern) {
            if (preg_match($pattern, $line)) {
                return true;
            }
        }

        return false;
    }

    private function findFooterStartLine(): int
    {
        $totalLines = count($this->lines);
        for ($i = 0; $i < $totalLines; $i++) {
            if ($this->isFooterLine($this->lines[$i])) {
                return $i;
            }
        }

        return $totalLines;
    }
}
