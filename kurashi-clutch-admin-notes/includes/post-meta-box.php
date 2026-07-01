<?php
if (!defined('ABSPATH')) exit;

add_action('add_meta_boxes', 'kcan_add_post_memo_meta_box');
function kcan_add_post_memo_meta_box() {
    $screens = get_option('kcan_enabled_post_types', array('post', 'page'));
    $context = get_option('kcan_meta_box_context', 'side'); 
    
    if (empty($screens) || !is_array($screens)) return;

    foreach ($screens as $screen) {
        add_meta_box(
            'kcan_post_memo_box',
            __('このページに関するメモ', 'kurashi-clutch-admin-notes'),
            'kcan_render_post_memo_meta_box',
            $screen,
            $context,
            'high'
        );
    }
}

function kcan_render_post_memo_meta_box($post) {
    wp_nonce_field('kcan_save_post_memo_action', 'kcan_post_memo_nonce');
    $memo_text = get_post_meta($post->ID, '_kcan_post_memo_text', true);
    ?>
    <textarea name="kcan_post_memo" class="kcan-post-memo-textarea"><?php echo esc_textarea($memo_text); ?></textarea>
    <p class="description"><?php esc_html_e('このメモは公開側のページには表示されません。', 'kurashi-clutch-admin-notes'); ?></p>
    <?php
}

add_action('save_post', 'kcan_save_post_memo');
function kcan_save_post_memo($post_id) {
    if (!isset($_POST['kcan_post_memo_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['kcan_post_memo_nonce'])), 'kcan_save_post_memo_action')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    
    if (isset($_POST['post_type']) && 'page' == $_POST['post_type']) {
        if (!current_user_can('edit_page', $post_id)) return;
    } else {
        if (!current_user_can('edit_post', $post_id)) return;
    }
    
    if (isset($_POST['kcan_post_memo'])) {
        update_post_meta($post_id, '_kcan_post_memo_text', sanitize_textarea_field(wp_unslash($_POST['kcan_post_memo'])));
    }
}