<?php

declare(strict_types=1);

namespace DevKraken\PhpCommitlint\Services;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

class LoggerService implements LoggerInterface
{
    private const string LOG_FILE = '.git/php-commitlint.log';
    private const int MAX_LOG_SIZE = 5 * 1024 * 1024; // 5MB

    private bool $enabled;
    private string $logFile;
    private string $minLevel;

    public function __construct(bool $enabled = true, ?string $logFile = null, string $minLevel = LogLevel::INFO)
    {
        $this->enabled = $enabled;
        $this->logFile = $logFile ?? self::LOG_FILE;
        $this->minLevel = $minLevel;
    }

    public function emergency(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(Stringable|string $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, Stringable|string $message, array $context = []): void
    {
        if (!$this->enabled || !is_string($level) || !$this->shouldLog($level)) {
            return;
        }

        $this->rotateLogIfNeeded();

        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = $this->interpolate((string) $message, $context);
        $logEntry = "[{$timestamp}] {$level}: {$formattedMessage}" . PHP_EOL;

        if ($context) {
            $logEntry .= "Context: " . json_encode($context, JSON_UNESCAPED_SLASHES) . PHP_EOL;
        }

        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getLogFile(): string
    {
        return $this->logFile;
    }

    public function clearLog(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }

    private function shouldLog(string $level): bool
    {
        $levels = [
            LogLevel::DEBUG => 0,
            LogLevel::INFO => 1,
            LogLevel::NOTICE => 2,
            LogLevel::WARNING => 3,
            LogLevel::ERROR => 4,
            LogLevel::CRITICAL => 5,
            LogLevel::ALERT => 6,
            LogLevel::EMERGENCY => 7,
        ];

        return ($levels[$level] ?? 0) >= ($levels[$this->minLevel] ?? 0);
    }

    private function rotateLogIfNeeded(): void
    {
        if (!file_exists($this->logFile)) {
            return;
        }

        if (filesize($this->logFile) > self::MAX_LOG_SIZE) {
            $backupFile = $this->logFile . '.old';
            if (file_exists($backupFile)) {
                unlink($backupFile);
            }
            rename($this->logFile, $backupFile);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function interpolate(string $message, array $context = []): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        return strtr($message, $replace);
    }
}
