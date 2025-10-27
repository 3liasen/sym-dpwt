<?php
declare(strict_types=1);

defined('ABSPATH') || exit;

function sym_dpwt_register_tools_settings(): void
{
    register_setting(
        'sym-dpwt-tools',
        SYM_DPWT_ADMIN_CSS_OPTION,
        [
            'type'              => 'string',
            'sanitize_callback' => 'sym_dpwt_sanitize_admin_css',
            'default'           => '',
        ]
    );
}

function sym_dpwt_sanitize_admin_css(string $css): string
{
    $css = wp_check_invalid_utf8($css);
    $css = str_ireplace('</style>', '', $css);
    $css = str_replace(["\r\n", "\r"], "\n", $css);
    $css = preg_replace('/[^\S\n]+$/m', '', $css) ?? $css;
    return trim((string) $css);
}

function sym_dpwt_render_tools_page(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have access to this page.', 'sym-dpwt'));
    }

    $css = get_option(SYM_DPWT_ADMIN_CSS_OPTION, '');
    if (!is_string($css)) {
        $css = '';
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('SYM-DPWT Tools', 'sym-dpwt'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('sym-dpwt-tools');
            ?>
            <table class="form-table" role="presentation">
                <tbody>
                <tr>
                    <th scope="row">
                        <label for="sym-dpwt-admin-css"><?php esc_html_e('Admin CSS', 'sym-dpwt'); ?></label>
                    </th>
                    <td>
                        <textarea
                            id="sym-dpwt-admin-css"
                            name="<?php echo esc_attr(SYM_DPWT_ADMIN_CSS_OPTION); ?>"
                            class="large-text code"
                            rows="10"
                        ><?php echo esc_textarea($css); ?></textarea>
                        <p class="description">
                            <?php esc_html_e('Add custom CSS that loads only in the WordPress admin area.', 'sym-dpwt'); ?>
                        </p>
                    </td>
                </tr>
                </tbody>
            </table>
            <?php submit_button(__('Save CSS', 'sym-dpwt')); ?>
        </form>
    </div>
    <?php
}

function sym_dpwt_render_admin_custom_css(): void
{
    if (!is_admin()) {
        return;
    }

    $css = get_option(SYM_DPWT_ADMIN_CSS_OPTION, '');
    if (!is_string($css) || '' === $css) {
        return;
    }

    $css = sym_dpwt_sanitize_admin_css($css);
    if ('' === $css) {
        return;
    }

    printf(
        "<style id='sym-dpwt-admin-css'>\n%s\n</style>\n",
        wp_strip_all_tags($css)
    );
}
