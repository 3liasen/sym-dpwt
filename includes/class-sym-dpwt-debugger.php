<?php
declare(strict_types=1);

namespace SymDpwt;

use Throwable;

defined('ABSPATH') || exit;

final class Debugger
{
    private const LOG_DIR_NAME = 'sym-dpwt';
    private const LOG_FILENAME = 'dpwt-debug.log';

    private static ?self $instance = null;

    private string $log_file;

    /**
     * @var callable|null
     */
    private $previous_error_handler = null;

    /**
     * @var callable|null
     */
    private $previous_exception_handler = null;

    private bool $initialized = false;

    private function __construct()
    {
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
        if ($this->initialized) {
            return;
        }

        $this->log_file = $this->determine_log_file();
        $this->ensure_log_location();

        error_reporting(E_ALL);
        ini_set('log_errors', '1');
        ini_set('display_errors', '0');
        ini_set('error_log', $this->log_file);

        $this->previous_error_handler = set_error_handler([$this, 'handle_error']);
        $this->previous_exception_handler = set_exception_handler([$this, 'handle_exception']);
        register_shutdown_function([$this, 'handle_shutdown']);

        \add_action('admin_post_sym_dpwt_clear_log', [$this, 'handle_clear_request']);

        $this->initialized = true;
    }

    public function log(string $message, array $context = []): void
    {
        $context_string = '';
        if (!empty($context)) {
            $encoded = \wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if ($encoded) {
                $context_string = ' | ' . $encoded;
            }
        }

        $entry = sprintf('[%s][INFO] %s%s', gmdate('Y-m-d H:i:s'), $message, $context_string);
        $this->write($entry);
    }

    public function get_log_file(): string
    {
        return $this->log_file;
    }

    public function get_log_contents(): string
    {
        if (!file_exists($this->log_file)) {
            return '';
        }

        $filesize = filesize($this->log_file);
        if (false === $filesize || 0 === $filesize) {
            return '';
        }

        if ($filesize > 500000) {
            $handle = fopen($this->log_file, 'r');
            if (false === $handle) {
                return '';
            }

            fseek($handle, -500000, SEEK_END);
            $data = fread($handle, 500000);
            fclose($handle);

            if (false !== $data) {
                return "[showing last 500KB]\n" . $data;
            }

            return '';
        }

        $content = file_get_contents($this->log_file);

        return false === $content ? '' : $content;
    }

    public function clear_log(): void
    {
        if (!file_exists($this->log_file)) {
            return;
        }

        file_put_contents($this->log_file, '');
    }

    public function handle_error(int $errno, string $errstr, string $errfile, int $errline): bool
    {
        $label = $this->map_error_type($errno);
        $message = sprintf('%s in %s on line %d', $errstr, $errfile, $errline);
        $this->write($this->format_entry($label, $message));

        if (is_callable($this->previous_error_handler)) {
            return (bool) call_user_func($this->previous_error_handler, $errno, $errstr, $errfile, $errline);
        }

        return false;
    }

    public function handle_exception(Throwable $exception): void
    {
        $message = sprintf('%s: %s in %s on line %d', get_class($exception), $exception->getMessage(), $exception->getFile(), $exception->getLine());
        $this->write($this->format_entry('EXCEPTION', $message));
        $this->write($this->format_entry('TRACE', $exception->getTraceAsString()));

        if (is_callable($this->previous_exception_handler)) {
            call_user_func($this->previous_exception_handler, $exception);
        }
    }

    public function handle_shutdown(): void
    {
        $error = error_get_last();
        if (null === $error) {
            return;
        }

        $label = $this->map_error_type((int) $error['type']);
        $message = sprintf('%s in %s on line %d', $error['message'], $error['file'], $error['line']);
        $this->write($this->format_entry($label, $message));
    }

    public function handle_clear_request(): void
    {
        if (!\current_user_can('manage_options')) {
            \wp_die(\esc_html__('You do not have permission to clear the log.', 'sym-dpwt'));
        }

        \check_admin_referer('sym_dpwt_clear_log');
        $this->clear_log();

        $page = \defined('SYM_DPWT_DEBUG_ADMIN_PAGE_SLUG') ? SYM_DPWT_DEBUG_ADMIN_PAGE_SLUG : 'sym-dpwt-debug';

        \wp_safe_redirect(\add_query_arg(['page' => $page, 'cleared' => '1'], \admin_url('admin.php')));
        exit;
    }

    private function determine_log_file(): string
    {
        $uploads = \wp_upload_dir();
        $base_dir = isset($uploads['basedir']) ? (string) $uploads['basedir'] : WP_CONTENT_DIR;
        $log_dir = \trailingslashit($base_dir) . self::LOG_DIR_NAME;

        return \trailingslashit($log_dir) . self::LOG_FILENAME;
    }

    private function ensure_log_location(): void
    {
        $dir = dirname($this->log_file);
        if (!is_dir($dir)) {
            \wp_mkdir_p($dir);
        }

        if (!file_exists($this->log_file)) {
            file_put_contents($this->log_file, '');
        }
    }

    private function write(string $entry): void
    {
        $line = $entry . "\n";
        file_put_contents($this->log_file, $line, FILE_APPEND | LOCK_EX);
    }

    private function map_error_type(int $errno): string
    {
        return match ($errno) {
            E_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR => 'ERROR',
            E_WARNING, E_USER_WARNING => 'WARNING',
            E_PARSE => 'PARSE',
            E_NOTICE, E_USER_NOTICE => 'NOTICE',
            E_STRICT => 'STRICT',
            E_DEPRECATED, E_USER_DEPRECATED => 'DEPRECATED',
            default => 'INFO',
        };
    }

    private function format_entry(string $label, string $message): string
    {
        return sprintf('[%s][%s] %s', gmdate('Y-m-d H:i:s'), $label, $message);
    }
}
