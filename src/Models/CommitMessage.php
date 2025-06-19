<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Models;

class CommitMessage
{
    private string $rawMessage;
    private ?string $type = null;
    private ?string $scope = null;
    private ?string $subject = null;
    private ?string $body = null;
    private ?string $footer = null;
    /**
     * @var array<int, string>
     */
    private array $lines;

    public function __construct(string $message)
    {
        $this->rawMessage = trim($message);
        $this->lines = explode("\n", $this->rawMessage);
        $this->parse();
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
     * @return array<int, string>
     */
    public function getLines(): array
    {
        return $this->lines;
    }

    public function hasValidFormat(): bool
    {
        // Check if it matches conventional commit format: type(scope): description
        // or type: description
        $pattern = '/^([a-z]+)(\([^)]+\))?: .+/';

        return preg_match($pattern, $this->lines[0] ?? '') === 1;
    }

    public function hasBlankLineAfterSubject(): bool
    {
        return isset($this->lines[1]) && trim($this->lines[1]) === '';
    }

    public function hasBlankLineBeforeFooter(): bool
    {
        if (empty($this->footer)) {
            return true;
        }

        $footerStartLine = $this->findFooterStartLine();
        if ($footerStartLine > 1) {
            return trim($this->lines[$footerStartLine - 1]) === '';
        }

        return false;
    }

    private function parse(): void
    {
        if (empty($this->lines)) {
            return;
        }

        $this->parseHeader($this->lines[0]);
        $this->parseBodyAndFooter();
    }

    private function parseHeader(string $header): void
    {
        // Parse conventional commit format: type(scope): description
        $pattern = '/^([a-z]+)(\(([^)]+)\))?: (.+)$/';

        if (preg_match($pattern, $header, $matches)) {
            $this->type = $matches[1];
            $this->scope = !empty($matches[3]) ? $matches[3] : null;
            $this->subject = $matches[4];
        } else {
            // If it doesn't match conventional format, treat entire header as subject
            $this->subject = $header;
        }
    }

    private function parseBodyAndFooter(): void
    {
        if (count($this->lines) <= 1) {
            return;
        }

        $bodyLines = [];
        $footerLines = [];
        $inFooter = false;
        $startFromLine = 1;

        // Skip blank line after subject if it exists
        if (isset($this->lines[1]) && trim($this->lines[1]) === '') {
            $startFromLine = 2;
        }

        for ($i = $startFromLine; $i < count($this->lines); $i++) {
            $line = $this->lines[$i];

            // Check if this line starts a footer (conventional format)
            if ($this->isFooterLine($line)) {
                $inFooter = true;
            }

            if ($inFooter) {
                $footerLines[] = $line;
            } else {
                $bodyLines[] = $line;
            }
        }

        // Remove trailing empty lines from body
        while (!empty($bodyLines) && trim(end($bodyLines)) === '') {
            array_pop($bodyLines);
        }

        // If we have footer lines, check if there's a blank line before footer
        if (!empty($footerLines) && !empty($bodyLines)) {
            // Remove the blank line that separates body from footer
            if (trim(end($bodyLines)) === '') {
                array_pop($bodyLines);
            }
        }

        $this->body = !empty($bodyLines) ? implode("\n", $bodyLines) : null;
        $this->footer = !empty($footerLines) ? implode("\n", $footerLines) : null;
    }

    private function isFooterLine(string $line): bool
    {
        // Footer lines typically start with:
        // - BREAKING CHANGE:
        // - Closes #123
        // - Fixes #123
        // - Refs #123
        // - Co-authored-by:
        // - Signed-off-by:
        $footerPatterns = [
            '/^BREAKING CHANGE:/',
            '/^(Closes?|Fixes?|Resolves?)\s+#\d+/i',
            '/^Refs?\s+#\d+/i',
            '/^Co-authored-by:/i',
            '/^Signed-off-by:/i',
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
