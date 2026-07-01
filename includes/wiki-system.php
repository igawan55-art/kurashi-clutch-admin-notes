<?php
if (!defined('ABSPATH')) exit;

/* ==========================================================================
   1. Create Database Table / データベーステーブルの作成
   ========================================================================== */
register_activation_hook(KCAN_PLUGIN_DIR . 'kurashi-clutch-admin-notes.php', 'kcan_create_wiki_table');
function kcan_create_wiki_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kcan_wiki';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        title varchar(255) NOT NULL,
        content longtext NOT NULL,
        parent_id mediumint(9) DEFAULT 0,
        status varchar(50) DEFAULT 'publish' NOT NULL,
        tags text,
        menu_order int(9) DEFAULT 0 NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/* ==========================================================================
   1.5 Auto Table Creation / テーブルの自動生成
   ========================================================================== */
add_action('admin_init', 'kcan_check_wiki_table');
function kcan_check_wiki_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kcan_wiki';

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name)) != $table_name) {
        kcan_create_wiki_table();
    }
}

/* ==========================================================================
   2. Add Admin Menu / 管理画面メニューの追加
   ========================================================================== */
add_action('admin_menu', 'kcan_add_wiki_menu');
function kcan_add_wiki_menu() {
    if (get_option('kcan_wiki_enabled', '1') != '1') return;

    $wiki_title = get_option('kcan_wiki_title', esc_html__('Internal Wiki', 'kurashi-clutch-admin-notes'));
    
    add_menu_page(
        $wiki_title,
        $wiki_title,
        'read',
        'kcan-wiki',
        'kcan_wiki_router',
        'dashicons-book',
        99
    );
}

/* ==========================================================================
   3. AJAX: Save Sort Order / AJAX: 並び順の保存処理
   ========================================================================== */
add_action('wp_ajax_kcan_wiki_update_order', 'kcan_wiki_update_order');
function kcan_wiki_update_order() {
    check_ajax_referer('kcan_wiki_order_nonce', 'nonce');
    
    $wiki_cap = get_option('kcan_wiki_capability', 'edit_posts');
    if (!current_user_can($wiki_cap)) wp_die();

    $order = isset($_POST['order']) ? array_map('intval', wp_unslash($_POST['order'])) : array();
    
    if (!empty($order)) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kcan_wiki';
        foreach ($order as $index => $id) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->update($table_name, array('menu_order' => $index), array('id' => $id), array('%d'), array('%d'));
        }
    }
    wp_send_json_success();
}

/* ==========================================================================
   4. Router (Dispatching and Save Processing) / ルーティング
   ========================================================================== */
function kcan_wiki_router() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'kcan_wiki';
    
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $action = isset($_GET['action']) ? sanitize_text_field(wp_unslash($_GET['action'])) : 'list';
    
    $wiki_cap = get_option('kcan_wiki_capability', 'edit_posts');
    $can_edit = current_user_can($wiki_cap);
    $is_post  = isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST';

    $is_save_action = $is_post && isset($_POST['kcan_wiki_save']);

    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if (in_array($action, ['new', 'edit', 'delete']) || $is_save_action) {
        if (!$can_edit) {
            wp_die(esc_html(__('You do not have permission to perform this action.', 'kurashi-clutch-admin-notes')));
        }
    }

    $saved_id = 0;
    
    if ($is_save_action) {
        check_admin_referer('kcan_wiki_save_action', 'kcan_wiki_nonce');
        
        $id        = isset($_POST['kcan_wiki_id']) ? intval(wp_unslash($_POST['kcan_wiki_id'])) : 0;
        $title     = isset($_POST['kcan_wiki_title']) ? sanitize_text_field(wp_unslash($_POST['kcan_wiki_title'])) : '';
        $content   = isset($_POST['kcan_wiki_content']) ? wp_kses_post(wp_unslash($_POST['kcan_wiki_content'])) : '';
        $parent_id = isset($_POST['kcan_wiki_parent_id']) ? intval(wp_unslash($_POST['kcan_wiki_parent_id'])) : 0;
        $status    = isset($_POST['kcan_wiki_status']) ? sanitize_text_field(wp_unslash($_POST['kcan_wiki_status'])) : 'publish';
        $tags      = isset($_POST['kcan_wiki_tags']) ? sanitize_text_field(wp_unslash($_POST['kcan_wiki_tags'])) : '';

        $allowed_statuses = array('publish', 'draft', 'archived');
        $status = in_array($status, $allowed_statuses, true) ? $status : 'publish';

        if ($id > 0 && $id === $parent_id) { $parent_id = 0; }

        if (empty($title)) {
            echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(__('Please enter a title.', 'kurashi-clutch-admin-notes')) . '</p></div>';
        } else {
            $data = array(
                'title' => $title, 'content' => $content, 'parent_id' => $parent_id, 'status' => $status, 'tags' => $tags
            );
            $format = array('%s', '%s', '%d', '%s', '%s');

            if ($id > 0) {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $result = $wpdb->update($table_name, $data, array('id' => $id), $format, array('%d'));
                if ($result !== false) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(__('Wiki updated successfully.', 'kurashi-clutch-admin-notes')) . '</p></div>';
                    $saved_id = $id;
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(__('Database Error:', 'kurashi-clutch-admin-notes') . ' ' . $wpdb->last_error) . '</p></div>';
                }
            } else {
                // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                $result = $wpdb->insert($table_name, $data, $format);
                if ($result) {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(__('New Wiki created successfully.', 'kurashi-clutch-admin-notes')) . '</p></div>';
                    $saved_id = $wpdb->insert_id;
                } else {
                    echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(__('Database Error:', 'kurashi-clutch-admin-notes') . ' ' . $wpdb->last_error) . '</p></div>';
                }
            }
            $action = 'view';
        }
    }

    if ($action === 'delete' && isset($_GET['id'])) {
        check_admin_referer('kcan_wiki_delete_' . sanitize_text_field(wp_unslash($_GET['id'])));
        $deleted_id = intval(wp_unslash($_GET['id']));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->update($table_name, array('parent_id' => 0), array('parent_id' => $deleted_id), array('%d'), array('%d'));
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $wpdb->delete($table_name, array('id' => $deleted_id), array('%d'));
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(__('Wiki deleted successfully.', 'kurashi-clutch-admin-notes')) . '</p></div>';
        $action = 'list';
    }

    if ($action === 'edit' || $action === 'new') {
        kcan_wiki_render_form($table_name, $action);
    } elseif ($action === 'view') {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $view_id = ($saved_id > 0) ? $saved_id : (isset($_GET['id']) ? intval(wp_unslash($_GET['id'])) : 0);
        
        if ($view_id === 0) {
            kcan_wiki_render_list($table_name, $can_edit);
        } else {
            kcan_wiki_render_view($table_name, $view_id, $can_edit);
        }
    } else {
        kcan_wiki_render_list($table_name, $can_edit);
    }
}

/* ==========================================================================
   5. View: Tree List / ビュー: ツリー型一覧画面
   ========================================================================== */
function kcan_wiki_render_tree_rows($parent_id, $tree, $depth = 0, $can_edit) {
    if (!isset($tree[$parent_id])) return;

    foreach ($tree[$parent_id] as $wiki) {
        $view_url = admin_url('admin.php?page=kcan-wiki&action=view&id=' . $wiki->id);
        $edit_url = admin_url('admin.php?page=kcan-wiki&action=edit&id=' . $wiki->id);
        $delete_url = wp_nonce_url(admin_url('admin.php?page=kcan-wiki&action=delete&id=' . $wiki->id), 'kcan_wiki_delete_' . $wiki->id);
        
        $indent = '';
        if ($depth > 0) {
            $indent = str_repeat('<span class="kcan-indent-dash">—</span>', $depth);
        }

        $status_label = esc_html__('Published', 'kurashi-clutch-admin-notes');
        $status_class = 'kcan-status-publish';
        
        if ($wiki->status === 'draft') {
            $status_label = esc_html__('Draft', 'kurashi-clutch-admin-notes');
            $status_class = 'kcan-status-draft';
        } elseif ($wiki->status === 'archived') {
            $status_label = esc_html__('Archived', 'kurashi-clutch-admin-notes');
            $status_class = 'kcan-status-archived';
        }
        ?>
        <tr class="kcan-wiki-row" data-id="<?php echo esc_attr($wiki->id); ?>" data-depth="<?php echo esc_attr($depth); ?>">
            <td class="title column-title has-row-actions column-primary page-title">
                <?php if ($can_edit): ?>
                    <span class="dashicons dashicons-menu kcan-drag-handle" title="<?php esc_attr_e('Drag to reorder', 'kurashi-clutch-admin-notes'); ?>"></span>
                <?php endif; ?>
                
                <?php echo wp_kses_post($indent); ?>
                <strong><a class="row-title" href="<?php echo esc_url($view_url); ?>"><?php echo esc_html($wiki->title); ?></a></strong>
                
                <div class="row-actions">
                    <span class="view"><a href="<?php echo esc_url($view_url); ?>"><?php esc_html_e('View', 'kurashi-clutch-admin-notes'); ?></a></span>
                    <?php if ($can_edit): ?>
                        <span class="edit"> | <a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Edit', 'kurashi-clutch-admin-notes'); ?></a></span>
                        <span class="trash"> | <a href="<?php echo esc_url($delete_url); ?>" class="kcan-submitdelete kcan-text-danger"><?php esc_html_e('Delete', 'kurashi-clutch-admin-notes'); ?></a></span>
                    <?php endif; ?>
                </div>
                <button type="button" class="toggle-row"><span class="screen-reader-text"><?php esc_html_e('Show details', 'kurashi-clutch-admin-notes'); ?></span></button>
            </td>
            <td><span class="kcan-status-label <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_label); ?></span></td>
            <td><?php echo esc_html($wiki->tags); ?></td>
            <td><?php echo esc_html(wp_date('Y/m/d H:i', strtotime($wiki->updated_at))); ?></td>
        </tr>
        <?php
        kcan_wiki_render_tree_rows($wiki->id, $tree, $depth + 1, $can_edit);
    }
}

function kcan_wiki_render_list($table_name, $can_edit) {
    global $wpdb;

    if ($can_edit) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $wikis = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}kcan_wiki ORDER BY menu_order ASC, updated_at DESC");
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $wikis = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}kcan_wiki WHERE status != 'draft' ORDER BY menu_order ASC, updated_at DESC");
    }
    
    $tree = array();
    $valid_ids = array();
    foreach ($wikis as $w) {
        $valid_ids[] = $w->id;
    }

    foreach ($wikis as $w) {
        $pid = intval($w->parent_id);
        if ($pid > 0 && !in_array($pid, $valid_ids)) {
            $pid = 0;
        }
        $tree[$pid][] = $w;
    }
    
    $wiki_title = get_option('kcan_wiki_title', esc_html__('Internal Wiki', 'kurashi-clutch-admin-notes'));
    ?>

    <div class="wrap">
        <h1 class="wp-heading-inline"><?php echo esc_html($wiki_title); ?></h1>
        <?php if ($can_edit): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=kcan-wiki&action=new')); ?>" class="page-title-action"><?php esc_html_e('Add New', 'kurashi-clutch-admin-notes'); ?></a>
            <span class="spinner kcan-wiki-spinner kcan-spinner-inline"></span>
        <?php endif; ?>
        
        <hr class="wp-header-end">
        <?php if ($can_edit): ?>
            <p class="kcan-text-muted"><?php
                /* translators: %s: HTML for the drag icon */
                echo wp_kses_post(sprintf(__('Use the %s icon to drag and drop for reordering.', 'kurashi-clutch-admin-notes'), '<span class="dashicons dashicons-menu kcan-icon-inline"></span>'));
            ?></p>
            <input type="hidden" id="kcan_wiki_order_nonce" value="<?php echo esc_attr(wp_create_nonce('kcan_wiki_order_nonce')); ?>">
        <?php endif; ?>

        <table class="wp-list-table widefat fixed striped table-view-list kcan-wiki-list kcan-mt-5">
            <thead>
                <tr>
                    <th scope="col" class="manage-column column-title column-primary kcan-col-50"><?php esc_html_e('Title', 'kurashi-clutch-admin-notes'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Status', 'kurashi-clutch-admin-notes'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Tags', 'kurashi-clutch-admin-notes'); ?></th>
                    <th scope="col" class="manage-column"><?php esc_html_e('Last Updated', 'kurashi-clutch-admin-notes'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($wikis)) : ?>
                    <tr><td colspan="4"><?php esc_html_e('No Wiki pages found.', 'kurashi-clutch-admin-notes'); ?></td></tr>
                <?php else : ?>
                    <?php kcan_wiki_render_tree_rows(0, $tree, 0, $can_edit); ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
}

/* ==========================================================================
   5.5 View: Simple Tree (Sidebar) / ビュー: サイドバー用ツリー
   ========================================================================== */
function kcan_wiki_render_simple_tree($parent_id, $tree, $current_id) {
    if (!isset($tree[$parent_id])) return;
    
    $ul_class = ($parent_id == 0) ? 'kcan-simple-tree-root' : 'kcan-simple-tree-child';
    
    echo '<ul class="' . esc_attr($ul_class) . '">';
    foreach ($tree[$parent_id] as $wiki) {
        $view_url = admin_url('admin.php?page=kcan-wiki&action=view&id=' . $wiki->id);
        $is_current = ($wiki->id == $current_id);
        
        $link_class = $is_current ? 'kcan-tree-link-current' : 'kcan-tree-link';
        
        echo '<li class="kcan-simple-tree-item">';
        if ($parent_id > 0) {
            echo '<span class="kcan-tree-dash">-</span>';
        }
        echo '<a href="' . esc_url($view_url) . '" class="' . esc_attr($link_class) . '">' . esc_html($wiki->title) . '</a>';
        
        kcan_wiki_render_simple_tree($wiki->id, $tree, $current_id);
        
        echo '</li>';
    }
    echo '</ul>';
}

/* ==========================================================================
   6. View: Viewing Mode / ビュー: 閲覧モード
   ========================================================================== */
function kcan_wiki_render_view($table_name, $id, $can_edit) {
    global $wpdb;
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
    $wiki = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kcan_wiki WHERE id = %d", $id));
    
    if (!$wiki || (!$can_edit && $wiki->status === 'draft')) {
        echo '<div class="wrap"><p>' . esc_html(__('Wiki page not found or access denied.', 'kurashi-clutch-admin-notes')) . '</p></div>';
        return;
    }

    $edit_url = admin_url('admin.php?page=kcan-wiki&action=edit&id=' . $wiki->id);
    $parent_title = esc_html__('None', 'kurashi-clutch-admin-notes');
    if ($wiki->parent_id > 0) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $parent_wiki = $wpdb->get_row($wpdb->prepare("SELECT title FROM {$wpdb->prefix}kcan_wiki WHERE id = %d", $wiki->parent_id));
        if ($parent_wiki) $parent_title = $parent_wiki->title;
    }

    if ($can_edit) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $all_wikis = $wpdb->get_results("SELECT id, title, parent_id FROM {$wpdb->prefix}kcan_wiki ORDER BY menu_order ASC, updated_at DESC");
    } else {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $all_wikis = $wpdb->get_results("SELECT id, title, parent_id FROM {$wpdb->prefix}kcan_wiki WHERE status != 'draft' ORDER BY menu_order ASC, updated_at DESC");
    }
    
    $sidebar_tree = array();
    $valid_ids = array();
    foreach ($all_wikis as $w) { $valid_ids[] = $w->id; }
    foreach ($all_wikis as $w) {
        $pid = intval($w->parent_id);
        if ($pid > 0 && !in_array($pid, $valid_ids)) { $pid = 0; }
        $sidebar_tree[$pid][] = $w;
    }
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php echo esc_html($wiki->title); ?></h1>
        <?php if ($can_edit): ?>
            <a href="<?php echo esc_url($edit_url); ?>" class="page-title-action"><?php esc_html_e('Edit this Wiki', 'kurashi-clutch-admin-notes'); ?></a>
        <?php endif; ?>
        <a href="<?php echo esc_url(admin_url('admin.php?page=kcan-wiki')); ?>" class="page-title-action"><?php esc_html_e('Back to List', 'kurashi-clutch-admin-notes'); ?></a>
        <hr class="wp-header-end">

        <div class="kcan-flex-container">
            <div class="kcan-flex-main">
                <?php echo wp_kses_post(wpautop($wiki->content)); ?>
            </div>
            <div class="kcan-flex-sidebar">
                <div class="kcan-sidebar-box">
                    <h3 class="kcan-sidebar-box-title"><?php esc_html_e('Wiki Info', 'kurashi-clutch-admin-notes'); ?></h3>
                    <p><strong><?php esc_html_e('Status:', 'kurashi-clutch-admin-notes'); ?></strong> <?php echo ($wiki->status === 'draft') ? esc_html(__('Draft', 'kurashi-clutch-admin-notes')) : (($wiki->status === 'archived') ? esc_html(__('Archived', 'kurashi-clutch-admin-notes')) : esc_html(__('Published', 'kurashi-clutch-admin-notes'))); ?></p>
                    <p><strong><?php esc_html_e('Parent Folder:', 'kurashi-clutch-admin-notes'); ?></strong> <?php echo esc_html($parent_title); ?></p>
                    <p><strong><?php esc_html_e('Tags:', 'kurashi-clutch-admin-notes'); ?></strong> <?php echo esc_html($wiki->tags ?: __('None', 'kurashi-clutch-admin-notes')); ?></p>
                    <p class="kcan-sidebar-meta"><strong><?php esc_html_e('Updated:', 'kurashi-clutch-admin-notes'); ?></strong> <?php echo esc_html(wp_date('Y/m/d H:i', strtotime($wiki->updated_at))); ?></p>
                </div>

                <div class="kcan-sidebar-box">
                    <h3 class="kcan-sidebar-box-title"><?php esc_html_e('Wiki Index', 'kurashi-clutch-admin-notes'); ?></h3>
                    <?php kcan_wiki_render_simple_tree(0, $sidebar_tree, $id); ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/* ==========================================================================
   7. View: Form / ビュー: 編集・新規追加フォーム
   ========================================================================== */
function kcan_wiki_render_form($table_name, $action) {
    global $wpdb;
    
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $id = isset($_GET['id']) ? intval(wp_unslash($_GET['id'])) : 0;
    
    $title = ''; $content = ''; $parent_id = 0; $status = 'publish'; $tags = ''; $page_title = esc_html__('Add New Wiki', 'kurashi-clutch-admin-notes');

    if ($action === 'edit' && $id > 0) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        $wiki = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}kcan_wiki WHERE id = %d", $id));
        if ($wiki) {
            $title = $wiki->title; $content = $wiki->content; $parent_id = $wiki->parent_id; 
            $status = $wiki->status; $tags = $wiki->tags; $page_title = esc_html__('Edit Wiki', 'kurashi-clutch-admin-notes');
        }
    }

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
    $all_wikis = $wpdb->get_results("SELECT id, title FROM {$wpdb->prefix}kcan_wiki ORDER BY title ASC");

    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
    $all_tags_raw = $wpdb->get_col("SELECT tags FROM {$wpdb->prefix}kcan_wiki WHERE tags IS NOT NULL AND tags != ''");
    $cloud_tags = array();
    foreach ($all_tags_raw as $raw) {
        $parts = explode(',', $raw);
        foreach ($parts as $p) {
            $p = trim($p);
            if (!empty($p)) $cloud_tags[] = $p;
        }
    }
    $cloud_tags = array_unique($cloud_tags);
    sort($cloud_tags);
    ?>
    <div class="wrap">
        <h1 class="wp-heading-inline"><?php echo esc_html($page_title); ?></h1>
        <?php if ($action === 'edit'): ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=kcan-wiki&action=view&id=' . $id)); ?>" class="page-title-action"><?php esc_html_e('Back to View', 'kurashi-clutch-admin-notes'); ?></a>
        <?php else: ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=kcan-wiki')); ?>" class="page-title-action"><?php esc_html_e('Back to List', 'kurashi-clutch-admin-notes'); ?></a>
        <?php endif; ?>
        <hr class="wp-header-end">

        <form method="post" action="<?php echo esc_url(admin_url('admin.php?page=kcan-wiki')); ?>" class="kcan-flex-container">
            <?php wp_nonce_field('kcan_wiki_save_action', 'kcan_wiki_nonce'); ?>
            <input type="hidden" name="kcan_wiki_id" value="<?php echo esc_attr($id); ?>">
            
            <div class="kcan-flex-main kcan-form-container">
                <div class="kcan-title-wrapper">
                    <input type="text" name="kcan_wiki_title" size="30" value="<?php echo esc_attr($title); ?>" spellcheck="true" autocomplete="off" placeholder="<?php esc_attr_e('Enter Title', 'kurashi-clutch-admin-notes'); ?>" required class="kcan-title-input">
                </div>
                <?php 
                add_filter('wp_default_editor', function() { return 'tinymce'; });
                wp_editor($content, 'kcan_wiki_content', array(
                    'media_buttons' => true,
                    'textarea_rows' => 25,
                    'tinymce' => true,
                    'quicktags' => true
                )); 
                ?>
            </div>

            <div class="kcan-flex-sidebar">
                <div class="kcan-sidebar-box">
                    <p class="submit kcan-submit-wrapper">
                        <input type="submit" name="kcan_wiki_save" id="submit" class="button button-primary button-large kcan-btn-full" value="<?php esc_attr_e('Save', 'kurashi-clutch-admin-notes'); ?>">
                    </p>
                    
                    <h4 class="kcan-label-heading"><?php esc_html_e('Status', 'kurashi-clutch-admin-notes'); ?></h4>
                    <select name="kcan_wiki_status" class="kcan-w-100">
                        <option value="publish" <?php selected($status, 'publish'); ?>><?php esc_html_e('Published', 'kurashi-clutch-admin-notes'); ?></option>
                        <option value="draft" <?php selected($status, 'draft'); ?>><?php esc_html_e('Draft', 'kurashi-clutch-admin-notes'); ?></option>
                        <option value="archived" <?php selected($status, 'archived'); ?>><?php esc_html_e('Archived', 'kurashi-clutch-admin-notes'); ?></option>
                    </select>

                    <h4 class="kcan-label-heading"><?php esc_html_e('Parent Folder', 'kurashi-clutch-admin-notes'); ?></h4>
                    <select name="kcan_wiki_parent_id" class="kcan-w-100">
                        <option value="0">— <?php esc_html_e('None (Root)', 'kurashi-clutch-admin-notes'); ?> —</option>
                        <?php foreach ($all_wikis as $w): 
                            if ($id === intval($w->id)) continue;
                        ?>
                            <option value="<?php echo esc_attr($w->id); ?>" <?php selected($parent_id, $w->id); ?>>
                                <?php echo esc_html($w->title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <h4 class="kcan-label-heading"><?php esc_html_e('Tags (Comma Separated)', 'kurashi-clutch-admin-notes'); ?></h4>
                    <input type="text" name="kcan_wiki_tags" id="kcan_wiki_tags_input" value="<?php echo esc_attr($tags); ?>" placeholder="<?php esc_attr_e('e.g. Sales, Manual', 'kurashi-clutch-admin-notes'); ?>" class="kcan-w-100">
                    
                    <?php if (!empty($cloud_tags)): ?>
                        <div class="kcan-tag-cloud-wrapper">
                            <p class="kcan-tag-cloud-title"><?php esc_html_e('Common Tags (Click to insert):', 'kurashi-clutch-admin-notes'); ?></p>
                            <div class="kcan-tag-cloud">
                                <?php foreach ($cloud_tags as $t): ?>
                                    <span class="kcan-tag-cloud-item"><?php echo esc_html($t); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
    <?php
}