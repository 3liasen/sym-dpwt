<?php
declare(strict_types=1);
/**
 * Plugin Name:       SYM - DPWT
 * Plugin URI:        https://sevenyellowmonkeys.dk
 * Description:       Extra functionalities for DPWT Accommodation
 * Version:           0.2.0
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

require_once SYM_DPWT_PLUGIN_DIR . 'includes/class-sym-dpwt-debugger.php';

use SymDpwt\Debugger;

function sym_dpwt_debugger(): Debugger
{
    return Debugger::instance();
}

function sym_dpwt_bootstrap(): void
{
    sym_dpwt_debugger()->init();

    add_action('admin_menu', 'sym_dpwt_register_debug_menu');
    add_action('admin_enqueue_scripts', 'sym_dpwt_debug_enqueue_assets');
}

add_action('plugins_loaded', 'sym_dpwt_bootstrap');

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

function sym_dpwt_debug(string $message, array $context = []): void
{
    sym_dpwt_debugger()->log($message, $context);
}
