<?php
declare(strict_types=1);
/**
 * Plugin Name:       SYM - DPWT
 * Plugin URI:        https://sevenyellowmonkeys.dk
 * Description:       Extra functionalities for DPWT Accommodation
 * Version:           0.3.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Jan Eliasen
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       sym-dpwt
 * Domain Path:       /languages
 *
 * @package SymDpwt
 */

defined('ABSPATH') || exit;

if (!defined('SYM_DPWT_VERSION')) {
    define('SYM_DPWT_VERSION', '0.2.0');
}

if (!defined('SYM_DPWT_PLUGIN_FILE')) {
    define('SYM_DPWT_PLUGIN_FILE', __FILE__);
}

if (!defined('SYM_DPWT_PLUGIN_DIR')) {
    define('SYM_DPWT_PLUGIN_DIR', plugin_dir_path(__FILE__));
}

if (!defined('SYM_DPWT_PLUGIN_URL')) {
    define('SYM_DPWT_PLUGIN_URL', plugin_dir_url(__FILE__));
}

if (!defined('SYM_DPWT_DEBUG_ADMIN_PAGE_SLUG')) {
    define('SYM_DPWT_DEBUG_ADMIN_PAGE_SLUG', 'sym-dpwt-debug');
}

if (!defined('SYM_DPWT_DEBUG_CLEAR_HOOK')) {
    define('SYM_DPWT_DEBUG_CLEAR_HOOK', 'sym_dpwt_debug_clear');
}

if (!defined('SYM_DPWT_DEBUG_TIMEZONE')) {
    define('SYM_DPWT_DEBUG_TIMEZONE', 'Europe/Copenhagen');
}

// New: option names for selectable log levels
if (!defined('SYM_DPWT_LOG_LEVELS_OPTION')) {
    define('SYM_DPWT_LOG_LEVELS_OPTION', 'sym_dpwt_log_levels');
}
if (!defined('SYM_DPWT_DEFAULT_LOG_LEVELS')) {
    // PSR-3 levels
    define('SYM_DPWT_DEFAULT_LOG_LEVELS', serialize(['debug','info','notice','warning','error','critical','alert','emergency']));
}

require_once SYM_DPWT_PLUGIN_DIR . 'includes/class-sym-dpwt-debugger.php';

use SymDpwt\Debugger;

function sym_dpwt_debugger(): Debugger
{
    return Debugger::instance();
}

register_activation_hook(SYM_DPWT_PLUGIN_FILE, 'sym_dpwt_activate');
register_deactivation_hook(SYM_DPWT_PLUGIN_FILE, 'sym_dpwt_deactivate');

function sym_dpwt_activate(): void
{
    sym_dpwt_schedule_debug_clear_event();
}

function sym_dpwt_deactivate(): void
{
    sym_dpwt_unschedule_debug_clear_event();
}

function sym_dpwt_bootstrap(): void
{
    sym_dpwt_debugger()->init();
    sym_dpwt_schedule_debug_clear_event();

    add_action('admin_menu', 'sym_dpwt_register_debug_menu');
    add_action('admin_enqueue_scripts', 'sym_dpwt_debug_enqueue_assets');

    // New: settings for selectable log levels
    add_action('admin_init', 'sym_dpwt_register_logging_settings');
}

add_action('plugins_loaded', 'sym_dpwt_bootstrap');
add_action(SYM_DPWT_DEBUG_CLEAR_HOOK, 'sym_dpwt_handle_scheduled_clear');

function sym_dpwt_register_debug_menu(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    add_menu_page(
        __('DPWT Debug', 'sym-dpwt'),
        __('DPWT-Debug', 'sym-dpwt'),
        'manage_options',
        SYM_DPWT_DEBUG_ADMIN_PAGE_SLUG,
        'sym_dpwt_render_debug_page',
        'dashicons-admin-tools',
        59
    );
}

function sym_dpwt_debug_enqueue_assets(string $hook): void
{
    if ('toplevel_page_' . SYM_DPWT_DEBUG_ADMIN_PAGE_SLUG !== $hook) {
        return;
    }

    $script = <<<'JS'
(function () {
    const button = document.getElementById('sym-dpwt-copy-log');
    if (!button) {
        return;
    }
    const textarea = document.getElementById('sym-dpwt-debug-log');
    if (!textarea) {
        return;
    }

    const defaultLabel = button.dataset.defaultLabel || button.textContent;
    const copiedLabel = button.dataset.copiedLabel || defaultLabel;

    const resetLabel = function () {
        window.setTimeout(function () {
            button.textContent = defaultLabel;
        }, 2500);
    };

    const copyWithFallback = function () {
        textarea.focus();
        textarea.select();
        if (typeof textarea.setSelectionRange === 'function') {
            textarea.setSelectionRange(0, textarea.value.length);
        }
        const didCopy = document.execCommand ? document.execCommand('copy') : false;
        if (didCopy) {
            button.textContent = copiedLabel;
            resetLabel();
        }
    };

    button.addEventListener('click', function () {
        if (navigator && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            navigator.clipboard.writeText(textarea.value)
                .then(function () {
                    button.textContent = copiedLabel;
                    resetLabel();
                })
                .catch(copyWithFallback);
            return;
        }

        copyWithFallback();
    });
}());
JS;

    add_action(
        'admin_print_footer_scripts',
        static function () use ($script): void {
            wp_print_inline_script_tag($script, ['id' => 'sym-dpwt-debug-controls']);
        }
    );
}

// New: Settings registration and helpers
function sym_dpwt_get_enabled_levels(): array
{
    $levels = get_option(SYM_DPWT_LOG_LEVELS_OPTION);
    if (!is_array($levels) || !$levels) {
        return unserialize(SYM_DPWT_DEFAULT_LOG_LEVELS);
    }
    return array_values(array_intersect(
        $levels,
        ['debug','info','notice','warning','error','critical','alert','emergency']
    ));
}

function sym_dpwt_register_logging_settings(): void
{
    register_setting(
        'sym-dpwt-logging',
        SYM_DPWT_LOG_LEVELS_OPTION,
        [
            'type' => 'array',
            'description' => 'SYM DPWT: enabled log levels',
            'sanitize_callback' => function ($val) {
                $allowed = ['debug','info','notice','warning','error','critical','alert','emergency'];
                if (!is_array($val)) { return unserialize(SYM_DPWT_DEFAULT_LOG_LEVELS); }
                $clean = array_values(array_intersect($allowed, array_map('strval', $val)));
                return $clean ?: unserialize(SYM_DPWT_DEFAULT_LOG_LEVELS);
            },
            'default' => unserialize(SYM_DPWT_DEFAULT_LOG_LEVELS),
            'show_in_rest' => false,
        ]
    );

    add_settings_section(
        'sym_dpwt_logging_section',
        __('Logging settings', 'sym-dpwt'),
        '__return_false',
        SYM_DPWT_DEBUG_ADMIN_PAGE_SLUG
    );

    add_settings_field(
        'sym_dpwt_log_levels',
        __('Log levels to record', 'sym-dpwt'),
        'sym_dpwt_render_log_levels_field',
        SYM_DPWT_DEBUG_ADMIN_PAGE_SLUG,
        'sym_dpwt_logging_section'
    );
}

function sym_dpwt_render_log_levels_field(): void
{
    $enabled = sym_dpwt_get_enabled_levels();
    $levels = ['debug','info','notice','warning','error','critical','alert','emergency'];
    echo '<fieldset>';
    foreach ($levels as $lvl) {
        $id = 'sym-dpwt-level-' . esc_attr($lvl);
        printf(
            '<label for="%1$s" style="display:inline-block;min-width:140px;margin:0 16px 8px 0;">
                <input type="checkbox" name="%2$s[]" id="%1$s" value="%3$s"%4$s />
                %5$s
            </label>',
            $id,
            esc_attr(SYM_DPWT_LOG_LEVELS_OPTION),
            esc_attr($lvl),
            in_array($lvl, $enabled, true) ? ' checked' : '',
            esc_html(ucfirst($lvl))
        );
    }
    echo '</fieldset>';
}

function sym_dpwt_render_debug_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have access to this page.', 'sym-dpwt'));
    }

    $debugger = sym_dpwt_debugger();
    $log_contents = $debugger->get_log_contents();
    $log_file = $debugger->get_log_file();
    $cleared = '1' === filter_input(INPUT_GET, 'cleared', FILTER_SANITIZE_NUMBER_INT);
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('DPWT Debug', 'sym-dpwt'); ?></h1>
        <?php if ($cleared) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php esc_html_e('Debug log cleared.', 'sym-dpwt'); ?></p>
            </div>
        <?php endif; ?>

        <form method="post" action="options.php" style="margin-bottom:16px;">
            <?php
            settings_fields('sym-dpwt-logging');
            do_settings_sections(SYM_DPWT_DEBUG_ADMIN_PAGE_SLUG);
            submit_button(__('Save logging settings', 'sym-dpwt'));
            ?>
        </form>

        <p>
            <?php esc_html_e('Log file location:', 'sym-dpwt'); ?>
            <code><?php echo esc_html($log_file); ?></code>
        </p>
        <p><?php esc_html_e('Only the last 500KB is shown if the log is larger.', 'sym-dpwt'); ?></p>
        <textarea readonly id="sym-dpwt-debug-log" class="large-text code" rows="20"><?php echo esc_textarea($log_contents); ?></textarea>
        <p>
            <button type="button" class="button" id="sym-dpwt-copy-log" data-default-label="<?php echo esc_attr__('Copy all content', 'sym-dpwt'); ?>" data-copied-label="<?php echo esc_attr__('Copied!', 'sym-dpwt'); ?>"><?php esc_html_e('Copy all content', 'sym-dpwt'); ?></button>
        </p>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('sym_dpwt_clear_log'); ?>
            <input type="hidden" name="action" value="sym_dpwt_clear_log" />
            <?php submit_button(__('Clear content', 'sym-dpwt'), 'delete', 'submit', false); ?>
        </form>
    </div>
    <?php
}

function sym_dpwt_debug(string $message, array $context = [], string $level = 'debug'): void
{
    if (isset($context['level']) && is_string($context['level'])) {
        $level = $context['level'];
        unset($context['level']);
    } elseif (isset($context['_level']) && is_string($context['_level'])) {
        $level = $context['_level'];
        unset($context['_level']);
    }

    sym_dpwt_debugger()->log($message, $context, $level);
}

function sym_dpwt_handle_scheduled_clear(): void
{
    sym_dpwt_debugger()->init();
    sym_dpwt_debugger()->clear_log();
}

function sym_dpwt_get_debug_timezone(): \DateTimeZone
{
    $name = apply_filters('sym_dpwt_debug_timezone', SYM_DPWT_DEBUG_TIMEZONE);

    try {
        return new \DateTimeZone($name);
    } catch (\Exception $exception) {
        return new \DateTimeZone('Europe/Copenhagen');
    }
}

function sym_dpwt_schedule_debug_clear_event(): void
{
    if (wp_next_scheduled(SYM_DPWT_DEBUG_CLEAR_HOOK)) {
        return;
    }

    $timestamp = sym_dpwt_next_debug_clear_timestamp();
    if ($timestamp > 0) {
        wp_schedule_event($timestamp, 'daily', SYM_DPWT_DEBUG_CLEAR_HOOK);
    }
}

function sym_dpwt_unschedule_debug_clear_event(): void
{
    $timestamp = wp_next_scheduled(SYM_DPWT_DEBUG_CLEAR_HOOK);
    while ($timestamp) {
        wp_unschedule_event($timestamp, SYM_DPWT_DEBUG_CLEAR_HOOK);
        $timestamp = wp_next_scheduled(SYM_DPWT_DEBUG_CLEAR_HOOK);
    }
}

function sym_dpwt_next_debug_clear_timestamp(): int
{
    $timezone = sym_dpwt_get_debug_timezone();
    $now = new DateTimeImmutable('now', $timezone);
    $target = $now->setTime(2, 0, 0);

    if ($target <= $now) {
        $target = $target->modify('+1 day');
    }

    return $target->getTimestamp();
}

// Admin-post handler for Clear content
add_action('admin_post_sym_dpwt_clear_log', function (): void {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('Insufficient permissions', 'sym-dpwt'));
    }
    check_admin_referer('sym_dpwt_clear_log');
    sym_dpwt_handle_scheduled_clear();
    wp_safe_redirect(add_query_arg('cleared', '1', menu_page_url(SYM_DPWT_DEBUG_ADMIN_PAGE_SLUG, false)));
    exit;
});
