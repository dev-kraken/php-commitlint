<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Models;

final class CommitMessage
{
    private const CONVENTIONAL_COMMIT_PATTERN = '/^([a-z]+)(\(([^)]+)\))?:\s*(.+)$/';

    private array $lines;
    private ?string $type;
    private ?string $scope;
    private ?string $subject;
    private ?string $body;
    private ?string $footer;

    public function __construct(
        private string $rawMessage,
        ?string $type = null,
        ?string $scope = null,
        ?string $subject = null,
        ?string $body = null,
        ?string $footer = null
    ) {
        $this->rawMessage = $this->sanitizeMessage($rawMessage);
        $this->lines = explode("\n", $this->rawMessage);

        // Parse the message to extract components
        $parsed = $this->parseMessage();

        // Set properties, using provided values if available, otherwise parsed values
        $this->type = $type ?? $parsed['type'];
        $this->scope = $scope ?? $parsed['scope'];
        $this->subject = $subject ?? $parsed['subject'];
        $this->body = $body ?? $parsed['body'];
        $this->footer = $footer ?? $parsed['footer'];
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
        return $this->matchesConventionalCommitFormat();
    }

    public function hasBlankLineAfterSubject(): bool
    {
        return count($this->lines) > 1 && trim($this->lines[1]) === '';
    }

    public function hasBlankLineBeforeFooter(): bool
    {
        $footerStartLine = $this->findFooterStartLine();

        return $footerStartLine > 0 &&
            $footerStartLine < count($this->lines) &&
            trim($this->lines[$footerStartLine - 1]) === '';
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
        return $this->subject ? mb_strlen($this->subject) : 0;
    }

    public function withType(string $type): self
    {
        return new self($this->rawMessage, $type, $this->scope, $this->subject, $this->body, $this->footer);
    }

    public function withScope(?string $scope): self
    {
        return new self($this->rawMessage, $this->type, $scope, $this->subject, $this->body, $this->footer);
    }

    public static function fromString(string $message): self
    {
        return new self(trim($message));
    }

    public static function fromFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("File not found: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new \RuntimeException("Failed to read file: {$filePath}");
        }

        return new self($content);
    }

    private function sanitizeMessage(string $message): string
    {
        // Remove null bytes and other control characters except newlines and tabs
        $sanitized = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $message);

        // Normalize line endings
        $sanitized = str_replace(["\r\n", "\r"], "\n", $sanitized ?? '');

        return trim($sanitized);
    }

    private function matchesConventionalCommitFormat(): bool
    {
        return preg_match(self::CONVENTIONAL_COMMIT_PATTERN, $this->lines[0] ?? '') === 1;
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

        if (empty($this->lines)) {
            return $result;
        }

        // Parse header
        $header = $this->lines[0];
        if (preg_match(self::CONVENTIONAL_COMMIT_PATTERN, $header, $matches)) {
            $result['type'] = $matches[1];
            $result['scope'] = !empty($matches[3]) ? $matches[3] : null;
            $result['subject'] = $matches[4];
        } else {
            // Only set subject if header is not empty
            $result['subject'] = !empty(trim($header)) ? $header : null;
        }

        // Parse body and footer
        if (count($this->lines) > 1) {
            $bodyAndFooter = $this->parseBodyAndFooter();
            $result['body'] = $bodyAndFooter['body'];
            $result['footer'] = $bodyAndFooter['footer'];
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

        for ($i = $startFromLine; $i < count($this->lines); $i++) {
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
            'body' => $this->processBodyLines($bodyLines),
            'footer' => $this->processFooterLines($footerLines),
        ];
    }

    /**
     * @param list<string> $bodyLines
     */
    private function processBodyLines(array $bodyLines): ?string
    {
        // Remove trailing empty lines
        while (!empty($bodyLines) && trim(end($bodyLines)) === '') {
            array_pop($bodyLines);
        }

        return !empty($bodyLines) ? implode("\n", $bodyLines) : null;
    }

    /**
     * @param list<string> $footerLines
     */
    private function processFooterLines(array $footerLines): ?string
    {
        return !empty($footerLines) ? implode("\n", $footerLines) : null;
    }

    private function isFooterLine(string $line): bool
    {
        $footerPatterns = [
            '/^BREAKING CHANGE:/',
            '/^(Closes?|Fixes?|Resolves?)\s+#\d+/i',
            '/^Refs?\s+#\d+/i',
            '/^Co-authored-by:/i',
            '/^Signed-off-by:/i',
            '/^Reviewed-by:/i',
            '/^Acked-by:/i',
            '/^Tested-by:/i',
        ];

        foreach ($footerPatterns as $pattern) {
            if (preg_match($pattern, $line)) {
                return true;
            }
        }

        return false;
    }

    private function findFooterStartLine(): int
    {
        for ($i = 0; $i < count($this->lines); $i++) {
            if ($this->isFooterLine($this->lines[$i])) {
                return $i;
            }
        }

        return count($this->lines);
    }
}
