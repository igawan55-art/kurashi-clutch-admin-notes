<?php
/*
Plugin Name: kurashi-clutch Admin Notes and Wiki
Plugin URI: 
Description: A professional administration productivity suite featuring dashboard notes, page-specific memos, a secure hierarchical Wiki, and advanced color-coded admin alerts. / ダッシュボード、各ページの編集画面にメモを残す機能に加え、管理画面にアラートを表示し、階層化されたWikiを管理できるプラグインです。
Version: 1.0.1
Author: igawan55
Author URI: 
License: GPLv2 or later
Text Domain: kurashi-clutch-admin-notes
Domain Path: /languages
*/

// Prevent direct access / 直接アクセスを禁止
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin directory path / プラグインのディレクトリパスを定義（接頭辞を KCAN_ に変更）
define('KCAN_PLUGIN_DIR', plugin_dir_path(__FILE__));

/* ==========================================================================
   Add "Settings" link to the plugin action links / プラグイン一覧画面に「設定」リンクを追加
   ========================================================================== */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'kcan_add_settings_link');
/**
 * Add settings link / 設定リンクを追加
 */
function kcan_add_settings_link($links) {
    // Specify the URL to the settings page / 設定画面へのURLを指定（スラッグを変更）
    $settings_link = '<a href="' . admin_url('options-general.php?page=kurashi-clutch-admin-notes') . '">' . __('Settings', 'kurashi-clutch-admin-notes') . '</a>';
    
    // Add the settings link to the beginning of the existing links array / 既存のリンク配列の「先頭」に設定リンクを追加する
    array_unshift($links, $settings_link);
    
    return $links;
}
/* ==========================================================================
   Enqueue Admin Assets (CSS & JS) / 管理画面用のCSSとJavaScriptを読み込み
   ========================================================================== */
add_action('admin_enqueue_scripts', 'kcan_enqueue_admin_assets');

function kcan_enqueue_admin_assets($hook) {
    // 定数 KCAN_PLUGIN_URL が未定義の場合は、メインファイルのパスを基準に生成
    $plugin_url = defined('KCAN_PLUGIN_URL') ? KCAN_PLUGIN_URL : plugin_dir_url(__FILE__);

    // CSSファイルの読み込み
    wp_enqueue_style(
        'kcan-admin-style', 
        $plugin_url . 'assets/css/admin.css', 
        array(), 
        filemtime(plugin_dir_path(__FILE__) . 'assets/css/admin.css') // キャッシュ対策：ファイル更新日をバージョンにする
    );

    // JSファイルの読み込み
    wp_enqueue_script(
        'kcan-admin-script', 
        $plugin_url . 'assets/js/admin.js', 
        array('jquery', 'jquery-ui-sortable'), 
        filemtime(plugin_dir_path(__FILE__) . 'assets/js/admin.js'), // キャッシュ対策
        true
    );

    // JS側に翻訳テキストや変数を渡す
    wp_localize_script('kcan-admin-script', 'kcanData', array(
        'confirmDeleteAlert' => esc_html__('Are you sure you want to delete this alert?', 'kurashi-clutch-admin-notes'),
        'confirmDeleteWiki'  => esc_html__('Are you sure you want to delete this page?', 'kurashi-clutch-admin-notes')
    ));
}
/* ==========================================================================
   Include files for each feature / 機能ごとのファイルを読み込み
   ========================================================================== */
// Include each feature file / 各機能のファイルをインクルード（定数を KCAN_PLUGIN_DIR に変更）
require_once KCAN_PLUGIN_DIR . 'includes/admin-settings.php';
require_once KCAN_PLUGIN_DIR . 'includes/admin-notices.php';
require_once KCAN_PLUGIN_DIR . 'includes/dashboard-widget.php';
require_once KCAN_PLUGIN_DIR . 'includes/post-meta-box.php';
require_once KCAN_PLUGIN_DIR . 'includes/wiki-system.php';
