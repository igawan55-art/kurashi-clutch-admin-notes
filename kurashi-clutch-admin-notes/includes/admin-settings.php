<?php
if (!defined('ABSPATH')) exit;

/* ==========================================================================
   1. Add Menu / メニューの追加
   ========================================================================== */
add_action('admin_menu', 'kcan_add_settings_page');
function kcan_add_settings_page() {
    add_options_page(
        esc_html__('kurashi-clutch Admin Notes and Wiki Settings', 'kurashi-clutch-admin-notes'),
        esc_html__('Memo Settings (KCAN)', 'kurashi-clutch-admin-notes'),
        'manage_options',
        'kurashi-clutch-admin-notes',
        'kcan_render_settings_page'
    );
}

/* ==========================================================================
   2. Register and Sanitize Settings / 設定の登録とサニタイズ
   ========================================================================== */
add_action('admin_init', 'kcan_register_settings');
function kcan_register_settings() {
    register_setting('kcan_settings_group', 'kcan_meta_box_context', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('kcan_settings_group', 'kcan_enabled_post_types', array('sanitize_callback' => 'kcan_sanitize_post_types'));
    register_setting('kcan_settings_group', 'kcan_dashboard_memos_config', array('sanitize_callback' => 'kcan_sanitize_dashboard_memos'));
    
    register_setting('kcan_settings_group', 'kcan_alerts_config', array(
        'sanitize_callback' => 'kcan_sanitize_alerts_config'
    ));

    register_setting('kcan_settings_group', 'kcan_wiki_enabled', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('kcan_settings_group', 'kcan_wiki_capability', array('sanitize_callback' => 'sanitize_text_field'));
    register_setting('kcan_settings_group', 'kcan_wiki_title', array('sanitize_callback' => 'sanitize_text_field'));
}

/**
 * Sanitize post types array / 投稿タイプのサニタイズ処理
 */
function kcan_sanitize_post_types($input) {
    if (!is_array($input)) return array();
    return array_filter(array_map('sanitize_text_field', $input)); 
}

/**
 * Sanitize dashboard memos array / ダッシュボードメモのサニタイズ処理
 */
function kcan_sanitize_dashboard_memos($input) {
    if (!is_array($input)) return array();
    
    $sanitized = array();
    foreach ($input as $id => $conf) {
        $clean_id = sanitize_key($id);
        if (empty($clean_id)) continue;
        
        $sanitized[$clean_id] = array(
            'title' => sanitize_text_field($conf['title'] ?? ''),
            'type'  => sanitize_text_field($conf['type'] ?? 'textarea'),
            'cap'   => sanitize_text_field($conf['cap'] ?? 'edit_pages')
        );
    }
    return $sanitized;
}

/**
 * Background processing to control save, delete, and add new alert settings / アラート設定のサニタイズ処理
 */
function kcan_sanitize_alerts_config($input) {
    if (!is_array($input)) return array(); 
    
    $sanitized = array();
    
    foreach ($input as $id => $conf) {
        $clean_id = sanitize_key($id);
        
        if (empty($clean_id)) continue;

        if ($clean_id === 'new' && empty(trim(wp_strip_all_tags($conf['text'] ?? '')))) {
            continue;
        }

        $save_id = ($clean_id === 'new') ? 'alert_' . uniqid() : $clean_id;
        
        // （推奨）値のホワイトリスト検証を追加
        $allowed_rules = array('all', 'dashboard', 'specific');
        $allowed_types = array('notice-info', 'notice-success', 'notice-warning', 'notice-error');
        
        $raw_rule = sanitize_text_field($conf['rule'] ?? 'all');
        $rule = in_array($raw_rule, $allowed_rules, true) ? $raw_rule : 'all';
        
        $raw_type = sanitize_text_field($conf['type'] ?? 'notice-info');
        $type = in_array($raw_type, $allowed_types, true) ? $raw_type : 'notice-info';
        
        $sanitized[$save_id] = array(
            'enabled'        => isset($conf['enabled']) ? 1 : 0,
            'rule'           => $rule,
            'type'           => $type,
            'target_pts'     => isset($conf['target_pts']) && is_array($conf['target_pts']) ? array_map('sanitize_text_field', $conf['target_pts']) : array(),
            'target_screens' => isset($conf['target_screens']) && is_array($conf['target_screens']) ? array_map('sanitize_text_field', $conf['target_screens']) : array(),
            'text'           => wp_kses_post($conf['text'] ?? '')
        );
    }
    
    return $sanitized;
}

/* ==========================================================================
   3. Render Settings Page / 設定画面の描画
   ========================================================================== */
function kcan_render_settings_page() {
    $post_types = get_post_types(array('public' => true), 'objects');
    
    $context = get_option('kcan_meta_box_context', 'side');
    $enabled_pts = get_option('kcan_enabled_post_types', array('post', 'page'));
    if (!is_array($enabled_pts)) $enabled_pts = array();
    
    $memos_config = get_option('kcan_dashboard_memos_config', array());
    if (empty($memos_config)) {
        $memos_config = array('memo_1' => array('title' => esc_html__('General Memo', 'kurashi-clutch-admin-notes'), 'type' => 'textarea', 'cap' => 'edit_pages'));
    }

    $alerts_config = get_option('kcan_alerts_config', array());
    
    if (!isset($alerts_config['new'])) {
        $alerts_config['new'] = array('enabled' => 1, 'rule' => 'all', 'type' => 'notice-info', 'text' => '');
    }

    $wiki_enabled = get_option('kcan_wiki_enabled', '1');
    $wiki_cap     = get_option('kcan_wiki_capability', 'edit_posts');
    $wiki_title   = get_option('kcan_wiki_title', esc_html__('Internal Wiki', 'kurashi-clutch-admin-notes'));

    add_filter('wp_default_editor', function() { return 'tinymce'; });
    ?>

    <div class="wrap">
        <h1><?php esc_html_e('kurashi-clutch Admin Notes and Wiki Settings', 'kurashi-clutch-admin-notes'); ?></h1>
        
        <h2 class="nav-tab-wrapper">
            <a href="#tab-dashboard" class="nav-tab nav-tab-active"><?php esc_html_e('Dashboard Memos', 'kurashi-clutch-admin-notes'); ?></a>
            <a href="#tab-page" class="nav-tab"><?php esc_html_e('Page Memos', 'kurashi-clutch-admin-notes'); ?></a>
            <a href="#tab-alert" class="nav-tab"><?php esc_html_e('Admin Alerts', 'kurashi-clutch-admin-notes'); ?></a>
            <a href="#tab-wiki" class="nav-tab"><?php esc_html_e('Wiki Feature', 'kurashi-clutch-admin-notes'); ?></a>
        </h2>

        <form method="post" action="options.php">
            <?php settings_fields('kcan_settings_group'); ?>
            
            <div id="tab-dashboard" class="kcan-tab-content active">
                <div class="kcan-permission-note"><?php esc_html_e('Configure the memo widgets displayed on the dashboard.', 'kurashi-clutch-admin-notes'); ?></div>
                <div id="kcan-memos-wrapper">
                    <?php foreach ($memos_config as $id => $conf) : ?>
                        <div class="kcan-memo-row">
                            <input type="hidden" name="kcan_dashboard_memos_config[<?php echo esc_attr($id); ?>][id]" value="<?php echo esc_attr($id); ?>">
                            <table class="form-table">
                                <tr><th><?php esc_html_e('Title', 'kurashi-clutch-admin-notes'); ?></th><td><input type="text" name="kcan_dashboard_memos_config[<?php echo esc_attr($id); ?>][title]" value="<?php echo esc_attr($conf['title'] ?? ''); ?>" class="regular-text"></td></tr>
                                <tr><th><?php esc_html_e('Input Type', 'kurashi-clutch-admin-notes'); ?></th><td><select name="kcan_dashboard_memos_config[<?php echo esc_attr($id); ?>][type]"><option value="textarea" <?php selected($conf['type'] ?? '', 'textarea'); ?>><?php esc_html_e('Textarea', 'kurashi-clutch-admin-notes'); ?></option><option value="list" <?php selected($conf['type'] ?? '', 'list'); ?>><?php esc_html_e('Unordered List', 'kurashi-clutch-admin-notes'); ?></option></select></td></tr>
                                <tr><th><?php esc_html_e('Edit Permission', 'kurashi-clutch-admin-notes'); ?></th><td><select name="kcan_dashboard_memos_config[<?php echo esc_attr($id); ?>][cap]"><option value="manage_options" <?php selected($conf['cap'] ?? '', 'manage_options'); ?>><?php esc_html_e('Administrator Only', 'kurashi-clutch-admin-notes'); ?></option><option value="edit_pages" <?php selected($conf['cap'] ?? '', 'edit_pages'); ?>><?php esc_html_e('Editor and Above', 'kurashi-clutch-admin-notes'); ?></option><option value="read" <?php selected($conf['cap'] ?? '', 'read'); ?>><?php esc_html_e('Anyone', 'kurashi-clutch-admin-notes'); ?></option></select></td></tr>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="tab-page" class="kcan-tab-content">
                <div class="kcan-permission-note"><?php esc_html_e('Note: Page memo edit permissions are synchronized with each post\'s edit permission.', 'kurashi-clutch-admin-notes'); ?></div>
                <table class="form-table">
                    <tr><th><?php esc_html_e('Display Location', 'kurashi-clutch-admin-notes'); ?></th><td><label><input type="radio" name="kcan_meta_box_context" value="side" <?php checked($context, 'side'); ?>> <?php esc_html_e('Sidebar', 'kurashi-clutch-admin-notes'); ?></label><br><label><input type="radio" name="kcan_meta_box_context" value="normal" <?php checked($context, 'normal'); ?>> <?php esc_html_e('Below Editor', 'kurashi-clutch-admin-notes'); ?></label></td></tr>
                    <tr>
                        <th><?php esc_html_e('Target Post Types', 'kurashi-clutch-admin-notes'); ?></th>
                        <td>
                            <input type="hidden" name="kcan_enabled_post_types[]" value="">
                            <?php foreach ($post_types as $pt): ?>
                                <label><input type="checkbox" name="kcan_enabled_post_types[]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $enabled_pts)); ?>> <?php echo esc_html($pt->label); ?></label>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                </table>
            </div>

            <div id="tab-alert" class="kcan-tab-content">
                <h2 class="title"><?php esc_html_e('Admin Alert Settings (Multiple Allowed)', 'kurashi-clutch-admin-notes'); ?></h2>
                <p><?php esc_html_e('Display important notifications or instructions to the team using color-coded banners.', 'kurashi-clutch-admin-notes'); ?></p>
                
                <div id="kcan-alerts-wrapper">
                    <?php foreach ($alerts_config as $id => $conf) : 
                        $is_new = ($id === 'new');
                        $editor_id = 'kcan_alert_text_' . str_replace('-', '_', $id);
                    ?>
                        <div class="kcan-memo-row kcan-alert-row <?php if ($is_new) echo 'kcan-alert-new'; ?>">
                            <?php if ($is_new) : ?>
                                <h3><?php esc_html_e('+ Add New Alert', 'kurashi-clutch-admin-notes'); ?></h3>
                            <?php endif; ?>
                            
                            <table class="form-table">
                                <tr>
                                    <th scope="row"><?php esc_html_e('Display Alert', 'kurashi-clutch-admin-notes'); ?></th>
                                    <td>
                                        <label><input type="checkbox" name="kcan_alerts_config[<?php echo esc_attr($id); ?>][enabled]" value="1" <?php checked($conf['enabled'] ?? '0', '1'); ?>> <?php esc_html_e('Enable this alert', 'kurashi-clutch-admin-notes'); ?></label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Display Location', 'kurashi-clutch-admin-notes'); ?></th>
                                    <td>
                                        <label><input type="radio" name="kcan_alerts_config[<?php echo esc_attr($id); ?>][rule]" value="all" <?php checked($conf['rule'] ?? 'all', 'all'); ?>> <?php esc_html_e('All Admin Screens', 'kurashi-clutch-admin-notes'); ?></label><br>
                                        <label><input type="radio" name="kcan_alerts_config[<?php echo esc_attr($id); ?>][rule]" value="dashboard" <?php checked($conf['rule'] ?? 'all', 'dashboard'); ?>> <?php esc_html_e('Dashboard Only', 'kurashi-clutch-admin-notes'); ?></label><br>
                                        <label><input type="radio" name="kcan_alerts_config[<?php echo esc_attr($id); ?>][rule]" value="specific" <?php checked($conf['rule'] ?? 'all', 'specific'); ?>> <?php esc_html_e('Specific Post Types Only', 'kurashi-clutch-admin-notes'); ?></label>
                                    </td>
                                </tr>
                                <tr class="kcan-specific-rules-row">
                                    <th scope="row"><?php esc_html_e('Detailed Display Rules', 'kurashi-clutch-admin-notes'); ?></th>
                                    <td>
                                        <?php 
                                        $alert_target_pts = $conf['target_pts'] ?? array('post', 'page');
                                        $alert_target_screens = $conf['target_screens'] ?? array('list', 'edit');
                                        ?>
                                        <p><strong><?php esc_html_e('Target Post Types:', 'kurashi-clutch-admin-notes'); ?></strong></p>
                                        <?php foreach ($post_types as $pt): ?>
                                            <label>
                                                <input type="checkbox" name="kcan_alerts_config[<?php echo esc_attr($id); ?>][target_pts][]" value="<?php echo esc_attr($pt->name); ?>" <?php checked(in_array($pt->name, $alert_target_pts)); ?>>
                                                <?php echo esc_html($pt->label); ?>
                                            </label>
                                        <?php endforeach; ?>
                                        
                                        <p><strong><?php esc_html_e('Target Screens:', 'kurashi-clutch-admin-notes'); ?></strong></p>
                                        <label>
                                            <input type="checkbox" name="kcan_alerts_config[<?php echo esc_attr($id); ?>][target_screens][]" value="list" <?php checked(in_array('list', $alert_target_screens)); ?>> <?php esc_html_e('Post List Page', 'kurashi-clutch-admin-notes'); ?>
                                        </label>
                                        <label>
                                            <input type="checkbox" name="kcan_alerts_config[<?php echo esc_attr($id); ?>][target_screens][]" value="edit" <?php checked(in_array('edit', $alert_target_screens)); ?>> <?php esc_html_e('Edit / New Post Page', 'kurashi-clutch-admin-notes'); ?>
                                        </label>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Alert Color (Type)', 'kurashi-clutch-admin-notes'); ?></th>
                                    <td>
                                        <select name="kcan_alerts_config[<?php echo esc_attr($id); ?>][type]">
                                            <option value="notice-info" <?php selected($conf['type'] ?? 'notice-info', 'notice-info'); ?>><?php esc_html_e('Info (Blue)', 'kurashi-clutch-admin-notes'); ?></option>
                                            <option value="notice-success" <?php selected($conf['type'] ?? 'notice-info', 'notice-success'); ?>><?php esc_html_e('Success (Green)', 'kurashi-clutch-admin-notes'); ?></option>
                                            <option value="notice-warning" <?php selected($conf['type'] ?? 'notice-info', 'notice-warning'); ?>><?php esc_html_e('Warning (Yellow)', 'kurashi-clutch-admin-notes'); ?></option>
                                            <option value="notice-error" <?php selected($conf['type'] ?? 'notice-info', 'notice-error'); ?>><?php esc_html_e('Error (Red)', 'kurashi-clutch-admin-notes'); ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php esc_html_e('Alert Content', 'kurashi-clutch-admin-notes'); ?></th>
                                    <td>
                                        <?php 
                                        wp_editor(
                                            $conf['text'] ?? '', 
                                            $editor_id, 
                                            array(
                                                'textarea_name' => "kcan_alerts_config[{$id}][text]",
                                                'textarea_rows' => 4,
                                                'media_buttons' => false,
                                                'tinymce' => true,
                                                'quicktags' => false
                                            )
                                        ); 
                                        ?>
                                    </td>
                                </tr>
                            </table>
                            <?php if (!$is_new) : ?>
                            <p><button type="button" class="button-link-delete kcan-remove-alert-btn"><?php esc_html_e('Delete this alert', 'kurashi-clutch-admin-notes'); ?></button></p>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div id="tab-wiki" class="kcan-tab-content">
                <div class="kcan-memo-row kcan-wiki-panel">
                    <h2 class="title"><?php esc_html_e('Wiki Feature Base Settings', 'kurashi-clutch-admin-notes'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Wiki', 'kurashi-clutch-admin-notes'); ?></th>
                            <td>
                                <input type="hidden" name="kcan_wiki_enabled" value="0">
                                <label><input type="checkbox" name="kcan_wiki_enabled" value="1" <?php checked($wiki_enabled, '1'); ?>> <?php esc_html_e('Use Wiki Feature', 'kurashi-clutch-admin-notes'); ?></label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Menu Title', 'kurashi-clutch-admin-notes'); ?></th>
                            <td>
                                <input type="text" name="kcan_wiki_title" value="<?php echo esc_attr($wiki_title); ?>" class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Edit Permission', 'kurashi-clutch-admin-notes'); ?></th>
                            <td>
                                <select name="kcan_wiki_capability">
                                    <option value="manage_options" <?php selected($wiki_cap, 'manage_options'); ?>><?php esc_html_e('Administrator Only', 'kurashi-clutch-admin-notes'); ?></option>
                                    <option value="edit_posts" <?php selected($wiki_cap, 'edit_posts'); ?>><?php esc_html_e('Editor / Author and Above', 'kurashi-clutch-admin-notes'); ?></option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div>
                <?php submit_button(esc_html__('Save Changes', 'kurashi-clutch-admin-notes'), 'primary large'); ?>
            </div>
        </form>
    </div>
    <?php
}