<?php
if (!defined('ABSPATH')) exit;

/* ==========================================================================
   1. Common Helper Functions / 共通ヘルパー関数
   ========================================================================== */

function kcan_get_valid_alerts_config() {
    $alerts_config = get_option('kcan_alerts_config');
    
    // Fallback for old text / 設定が空の場合のフォールバック
    if (!is_array($alerts_config)) {
        $old_text = get_option('kcan_alert_text', '');
        if (empty($old_text)) return array();
        
        $alerts_config = array(
            'default' => array(
                'enabled' => get_option('kcan_alert_enabled', '0'),
                'rule' => get_option('kcan_alert_display_rule', 'all'),
                'target_pts' => get_option('kcan_alert_target_post_types', array('post', 'page')),
                'target_screens' => get_option('kcan_alert_target_screens', array('list', 'edit')),
                'type' => get_option('kcan_alert_type', 'notice-info'),
                'text' => $old_text,
            )
        );
    }

    $valid_configs = array();
    foreach ($alerts_config as $id => $conf) {
        if ($id === 'new') continue;
        if (empty($conf['enabled'])) continue;
        if (empty($conf['text'])) continue;
        $valid_configs[$id] = $conf;
    }
    
    return $valid_configs;
}

function kcan_is_alert_visible_on_current_screen($conf, $screen) {
    $alert_rule = $conf['rule'] ?? 'all';

    if ($alert_rule === 'all') {
        return true;
    } elseif ($alert_rule === 'dashboard') {
        if ($screen->id === 'dashboard') return true;
    } elseif ($alert_rule === 'specific') {
        $alert_pts = $conf['target_pts'] ?? array();
        $alert_screens = $conf['target_screens'] ?? array();
        
        if (is_array($alert_pts) && in_array($screen->post_type, $alert_pts)) {
            if (is_array($alert_screens)) {
                if (in_array('list', $alert_screens) && $screen->base === 'edit') return true;
                if (in_array('edit', $alert_screens) && $screen->base === 'post') return true;
            }
        }
    }
    return false;
}

/* ==========================================================================
   2. Standard Alert Display (For Dashboard and Top of List Pages) / 標準のアラート表示 (ダッシュボードや一覧ページの上部用)
   ========================================================================== */
add_action('admin_notices', 'kcan_display_admin_notices');
function kcan_display_admin_notices() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen) return;

    // Skip here for edit/new post screens (base: post) to display in the right column / 編集・新規追加画面(base: post)の場合は、右カラムに出すためここではスキップ
    if ($screen->base === 'post') {
        return;
    }

    $alerts_config = kcan_get_valid_alerts_config();

    foreach ($alerts_config as $conf) {
        if (!kcan_is_alert_visible_on_current_screen($conf, $screen)) continue;

        $alert_type = $conf['type'] ?? 'notice-info';

        echo '<div class="notice ' . esc_attr($alert_type) . ' is-dismissible">';
        // Fix: Apply wp_kses_post() directly on output to resolve escaping warnings / 出力時に直接wp_kses_post()を適用してエスケープ警告を解消
        echo wp_kses_post(wpautop($conf['text']));
        echo '</div>';
    }
}

/* ==========================================================================
   3. Alert Display for Edit Screens (Meta box in the right column) / 編集画面用のアラート表示 (右カラムのメタボックス)
   ========================================================================== */
add_action('add_meta_boxes', 'kcan_add_alert_meta_box');
function kcan_add_alert_meta_box() {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    if (!$screen) return;

    $alerts_config = kcan_get_valid_alerts_config();
    if (empty($alerts_config)) return;

    $has_alert = false;
    foreach ($alerts_config as $conf) {
        if (kcan_is_alert_visible_on_current_screen($conf, $screen)) {
            $has_alert = true;
            break; // Add meta box if there is at least one / 1つでもあればメタボックスを追加
        }
    }

    if ($has_alert) {
        add_meta_box(
            'kcan_alerts_meta_box', // Apply CSS to this ID / このIDに対してCSSを当てます
            __('Notice', 'kurashi-clutch-admin-notes'), // Change title / タイトルを変更
            'kcan_render_alerts_meta_box',
            $screen->post_type,
            'side',
            'high'
        );
    }
}

function kcan_render_alerts_meta_box($post) {
    $screen = function_exists('get_current_screen') ? get_current_screen() : null;
    $alerts_config = kcan_get_valid_alerts_config();

    // Collect alerts to display / 表示するアラートを収集
    $display_alerts = array();
    foreach ($alerts_config as $conf) {
        if (kcan_is_alert_visible_on_current_screen($conf, $screen)) {
            $display_alerts[] = $conf;
        }
    }

    if (empty($display_alerts)) return;

    // Get the color of the first alert to determine the border color of the meta box / メタボックスの枠自体の色を決めるため、1つ目のアラートの色を取得
    $primary_alert_type = $display_alerts[0]['type'] ?? 'notice-info';
    
    $border_color = '#72aee6'; // info (Blue / 青)
    $bg_color     = '#f0f6fc'; // info background / info背景
    
    if ($primary_alert_type === 'notice-success') {
        $border_color = '#46b450';
        $bg_color     = '#f1fcf2';
    } elseif ($primary_alert_type === 'notice-warning') {
        $border_color = '#dba617';
        $bg_color     = '#fff8e5';
    } elseif ($primary_alert_type === 'notice-error') {
        $border_color = '#d63638';
        $bg_color     = '#fcf0f1';
    }
    
    // Output alert text / アラートのテキストを出力（JSやCSSに渡す色情報をdata属性としてセット）
    echo '<div class="kcan-alerts-meta-container" data-border-color="' . esc_attr($border_color) . '" data-bg-color="' . esc_attr($bg_color) . '">';
    foreach ($display_alerts as $index => $conf) {
        
        // Add a separator line if there are multiple / 複数ある場合は区切り線を入れる
        if ($index > 0) {
            echo '<hr>';
        }
        
        echo '<div class="kcan-alert-meta-item">';
        // Fix: Apply wp_kses_post() directly on output to resolve escaping warnings / 出力時に直接wp_kses_post()を適用してエスケープ警告を解消
        echo wp_kses_post(wpautop($conf['text']));
        echo '</div>';
    }
    echo '</div>';
}