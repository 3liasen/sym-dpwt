<?php
declare(strict_types=1);

namespace SymDpwt;

defined('ABSPATH') || exit;

final class Debugger
{
    private static ?self $instance = null;

    /** @var string */
    private string $log_dir;

    /** @var string */
    private string $log_file;

    private function __construct()
    {
        $this->log_dir  = '';
        $this->log_file = '';
    }

    public static function instance(): self
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function init(): void
    {
        $uploads = wp_upload_dir(null, false);
        $base = isset($uploads['basedir']) ? (string) $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';
        $dir  = trailingslashit($base) . 'sym-dpwt';

        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
        }

        $file = trailingslashit($dir) . 'debug.log';
        if (!file_exists($file)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
            $h = @fopen($file, 'a');
            if (is_resource($h)) {
                @fclose($h);
            }
            @chmod($file, 0640);
        }

        $this->log_dir  = $dir;
        $this->log_file = $file;
    }

    public function get_log_file(): string
    {
        if (empty($this->log_file)) {
            $this->init();
        }
        return $this->log_file;
    }

    public function get_log_contents(): string
    {
        $file = $this->get_log_file();
        if (!file_exists($file)) {
            return '';
        }

        $max = 500 * 1024; // 500KB
        $size = filesize($file);
        if (false === $size || $size <= $max) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_get_contents
            $content = @file_get_contents($file);
            return is_string($content) ? $content : '';
        }

        // Tail last 500KB
        $fp = @fopen($file, 'rb');
        if (!$fp) {
            return '';
        }
        try {
            @fseek($fp, -$max, SEEK_END);
            $data = @stream_get_contents($fp);
            return is_string($data) ? $data : '';
        } finally {
            @fclose($fp);
        }
    }

    public function clear_log(): void
    {
        $file = $this->get_log_file();
        if (!file_exists($file)) {
            return;
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
        $fp = @fopen($file, 'w');
        if ($fp) {
            @fclose($fp);
        }
    }

    /**
     * @param array<string,mixed> $context
     */
    public function log(string $message, array $context = [], string $level = 'debug'): void
    {
        $level = strtolower($level);
        if (!$this->should_log($level)) {
            return;
        }

        $line = $this->format_line($message, $context, $level);
        $this->write($line);
    }

    private function should_log(string $level): bool
    {
        if (!function_exists('\sym_dpwt_get_enabled_levels')) {
            return true;
        }
        $allowed = \sym_dpwt_get_enabled_levels();
        return in_array($level, $allowed, true);
    }

    /**
     * @param array<string,mixed> $context
     */
    private function format_line(string $message, array $context, string $level): string
    {
        $tz = \sym_dpwt_get_debug_timezone();
        $now = new \DateTimeImmutable('now', $tz);
        $time = $now->format('Y-m-d H:i:s');

        $json = '';
        if (!empty($context)) {
            // phpcs:ignore WordPress.WP.AlternativeFunctions.json_json_encode
            $json = ' ' . wp_json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return sprintf("[%s] %-9s %s%s%s",
            $time,
            strtoupper($level),
            $message,
            $json,
            PHP_EOL
        );
    }

    private function write(string $line): void
    {
        $file = $this->get_log_file();
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen
        $fp = @fopen($file, 'ab');
        if (!$fp) {
            return;
        }
        try {
            @flock($fp, LOCK_EX);
            @fwrite($fp, $line);
            @fflush($fp);
            @flock($fp, LOCK_UN);
        } finally {
            @fclose($fp);
        }
    }
}
