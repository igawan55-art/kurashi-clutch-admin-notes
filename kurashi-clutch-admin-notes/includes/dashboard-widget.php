<?php
if (!defined('ABSPATH')) exit;

add_action('wp_dashboard_setup', 'kcan_add_dashboard_widget');
/**
 * Register dashboard widgets / ダッシュボードウィジェットの登録
 */
function kcan_add_dashboard_widget() {
    $memos_config = get_option('kcan_dashboard_memos_config');
    
    // Fallback if settings are empty / 設定が空の場合のフォールバック
    if (!is_array($memos_config) || empty($memos_config)) {
        $memos_config = array(
            'default' => array(
                'title' => get_option('kcan_dashboard_widget_title', esc_html__('Shared Memo', 'kurashi-clutch-admin-notes')),
                'type'  => get_option('kcan_dashboard_memo_type', 'textarea'),
                'cap'   => get_option('kcan_edit_capability', 'edit_posts')
            )
        );
    }

    // Loop through configured memo blocks and register them / 設定された複数のメモ枠をループして登録
    foreach ($memos_config as $id => $conf) {
        if (current_user_can('read')) {
            wp_add_dashboard_widget(
                'kcan_dashboard_memo_widget_' . $id, // Make ID unique / IDをユニークにする
                esc_html($conf['title']),
                'kcan_render_dashboard_widget',
                null,
                array('id' => $id, 'config' => $conf) // Pass config data to callback / 設定データをコールバックに渡す
            );
        }
    }
}

/**
 * Render the dashboard widget content / ダッシュボードウィジェットの表示処理
 */
function kcan_render_dashboard_widget($post, $callback_args) {
    $memo_id = $callback_args['args']['id'];
    $config = $callback_args['args']['config'];
    
    $memo_type = $config['type'] ?? 'textarea';
    $edit_cap = $config['cap'] ?? 'edit_posts';
    $user_can_edit = current_user_can($edit_cap);
    
    // Change save key based on ID / IDごとに保存先のキーを変更
    if ($memo_id === 'default') {
        $memo_text = get_option('kcan_dashboard_memo_text', '');
        $list_data = get_option('kcan_dashboard_memo_list', array());
    } else {
        $memo_text = get_option('kcan_dashboard_memo_text_' . $memo_id, '');
        $list_data = get_option('kcan_dashboard_memo_list_' . $memo_id, array());
    }
    ?>

    <div class="kcan-dashboard-wrapper" id="kcan-wrapper-<?php echo esc_attr($memo_id); ?>" data-memo-id="<?php echo esc_attr($memo_id); ?>">
        
        <div class="kcan-mode-view">
            <?php if ($memo_type === 'list') : ?>
                <div class="kcan-list-container">
                    <?php if (empty($list_data)) : ?>
                        <p><?php esc_html_e('No memos available.', 'kurashi-clutch-admin-notes'); ?></p>
                    <?php else : ?>
                        <?php foreach ($list_data as $item) : 
                            $checked = isset($item['checked']) && $item['checked'] ? 'checked' : '';
                            ?>
                            <div class="kcan-list-item">
                                <input type="checkbox" <?php echo esc_attr($checked); ?> disabled>
                                <span class="kcan-text-readonly"><?php echo esc_html($item['text']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php else : ?>
                <div class="kcan-readonly-box"><?php echo $memo_text ? esc_html($memo_text) : esc_html__('No memos available.', 'kurashi-clutch-admin-notes'); ?></div>
            <?php endif; ?>

            <?php if ($user_can_edit) : ?>
                <div class="kcan-action-bar">
                    <button type="button" class="button button-secondary kcan-btn-edit"><?php esc_html_e('Edit', 'kurashi-clutch-admin-notes'); ?></button>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($user_can_edit) : ?>
            <div class="kcan-mode-edit">
                <form method="post" action="">
                    <?php wp_nonce_field('kcan_save_dashboard_memo_action', 'kcan_dashboard_memo_nonce'); ?>
                    <input type="hidden" name="kcan_memo_id" value="<?php echo esc_attr($memo_id); ?>">

                    <?php if ($memo_type === 'list') : ?>
                        <div class="kcan-list-container">
                            <?php if (!empty($list_data)) : ?>
                                <?php foreach ($list_data as $index => $item) : 
                                    $checked = isset($item['checked']) && $item['checked'] ? 'checked' : '';
                                    ?>
                                    <div class="kcan-list-item">
                                        <input type="checkbox" name="kcan_list_checked[<?php echo esc_attr($index); ?>]" value="1" <?php echo esc_attr($checked); ?>>
                                        <input type="text" name="kcan_list_text[<?php echo esc_attr($index); ?>]" value="<?php echo esc_attr($item['text']); ?>">
                                        <span class="kcan-remove-item">&times;</span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <button type="button" class="button kcan-btn-add-item"><?php esc_html_e('+ Add Item', 'kurashi-clutch-admin-notes'); ?></button>
                    <?php else : ?>
                        <textarea name="kcan_dashboard_memo"><?php echo esc_textarea($memo_text); ?></textarea>
                    <?php endif; ?>

                    <div class="kcan-action-bar">
                        <input type="submit" name="kcan_save_dashboard_memo_submit" class="button button-primary" value="<?php esc_attr_e('Save Memo', 'kurashi-clutch-admin-notes'); ?>">
                        <button type="button" class="button button-link-delete kcan-btn-cancel"><?php esc_html_e('Cancel', 'kurashi-clutch-admin-notes'); ?></button>
                    </div>
                </form>
            </div>
        <?php endif; ?>

    </div>
    <?php
}

/* ==========================================================================
   Save dashboard memo / 保存処理（IDに基づいて保存先を振り分け）
   ========================================================================== */
add_action('admin_init', 'kcan_save_dashboard_memo');
function kcan_save_dashboard_memo() {
    if (isset($_POST['kcan_save_dashboard_memo_submit']) && isset($_POST['kcan_memo_id'])) {
        
        $nonce = isset($_POST['kcan_dashboard_memo_nonce']) ? sanitize_text_field(wp_unslash($_POST['kcan_dashboard_memo_nonce'])) : '';
        if (!wp_verify_nonce($nonce, 'kcan_save_dashboard_memo_action')) return;
        
        $memo_id = sanitize_text_field(wp_unslash($_POST['kcan_memo_id']));
        
        $memos_config = get_option('kcan_dashboard_memos_config');
        
        if (!is_array($memos_config) || empty($memos_config)) {
            $memos_config = array(
                'default' => array(
                    'type' => get_option('kcan_dashboard_memo_type', 'textarea'),
                    'cap' => get_option('kcan_edit_capability', 'edit_posts')
                )
            );
        }
        
        if (!isset($memos_config[$memo_id])) return;
        
        $config = $memos_config[$memo_id];
        if (!current_user_can($config['cap'])) return; 
        
        if ($config['type'] === 'list') {
            $list_data = array();
            if (isset($_POST['kcan_list_text'])) {
                $posted_texts = array_map('sanitize_text_field', wp_unslash((array) $_POST['kcan_list_text']));
                
                foreach ($posted_texts as $index => $text) {
                    if (empty($text)) continue;
                    
                    $checked = isset($_POST['kcan_list_checked'][$index]) ? 1 : 0;
                    $list_data[] = array('text' => $text, 'checked' => $checked);
                }
            }
            if ($memo_id === 'default') {
                update_option('kcan_dashboard_memo_list', $list_data);
            } else {
                update_option('kcan_dashboard_memo_list_' . $memo_id, $list_data);
            }
            
        } else {
            if (isset($_POST['kcan_dashboard_memo'])) {
                $text = sanitize_textarea_field(wp_unslash($_POST['kcan_dashboard_memo']));
                if ($memo_id === 'default') {
                    update_option('kcan_dashboard_memo_text', $text);
                } else {
                    update_option('kcan_dashboard_memo_text_' . $memo_id, $text);
                }
            }
        }
        
        wp_safe_redirect(admin_url('index.php'));
        exit;
    }
}