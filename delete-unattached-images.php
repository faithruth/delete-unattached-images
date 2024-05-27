<?php
/**
 * Plugin Name:       Delete Unattached Images
 * Plugin URI:        https://github.com/faithruth/delete-unattached-images
 * Description:       Deletes unattached images in WordPress.
 * Author:            Imokol Faith Ruth
 * Author URI:        https://github.com/faithruth/delete-unattached-images
 * Version:           1.0
 * Requires PHP:      8.0
 * Requires at least: 6.0
 * Domain Path:       /languages/
 * Text Domain:       delete-unattached-images
 *
 * @package Streamline_Data_Sync
 */

// Enqueue the script
add_action('admin_enqueue_scripts', 'dui_enqueue_delete_unattached_images_script');

// Add submenu under Media Library
add_action('admin_menu', 'dui_delete_unattached_images_menu');

function dui_delete_unattached_images_menu() {
    add_submenu_page(
        'upload.php',
        'Delete Unattached Images',
        'Delete Unattached Images',
        'manage_options',
        'delete-unattached-images',
        'dui_delete_unattached_images_page'
    );
}

function dui_delete_unattached_images_page() {
    ?>
    <div class="wrap">
        <h1>Delete Unattached Images</h1>
        <p>Click the button below to delete all unattached and unused images. This action cannot be undone.</p>
        <button id="delete-unattached-images-button" class="button button-primary">Delete Unattached Images</button>
        <div id="progress-container" style="display:none;">
            <h2>Deleting Images</h2>
            <p>Total Unattached Images: <span id="total-count"></span></p>
            <p><span id="batch-count"></span></p>
        </div>
    </div>
    <?php
}

function dui_enqueue_delete_unattached_images_script($hook) {
    if ($hook !== 'media_page_delete-unattached-images') {
        return;
    }

    wp_enqueue_script('delete-unattached-images', plugins_url('/delete-unattached-images.js', __FILE__), array('jquery'), null, true);

    wp_localize_script('delete-unattached-images', 'deleteUnattachedImages', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('delete_unattached_images_nonce'),
    ));
}

// Define the background task hook
add_action('delete_unattached_images_task', 'dui_delete_unattached_images_task', 10, 2);

function dui_delete_unattached_images_task($offset, $batchSize) {
    global $wpdb;

    $attachment_ids = $wpdb->get_col($wpdb->prepare("
        SELECT ID
        FROM $wpdb->posts
        WHERE post_type = 'attachment'
        AND post_parent = 0
        AND post_status = 'inherit'
        ORDER BY ID ASC
        LIMIT %d, %d
    ", $offset, $batchSize));

    foreach ($attachment_ids as $attachment_id) {
        if (dui_is_image_unused($attachment_id)) {
            if (wp_delete_attachment($attachment_id, true)) {
                $deleted_count++;
            }
        }
    }

    return count($attachment_ids);
}

// Hook to start the deletion process
add_action('wp_ajax_start_delete_unattached_images', 'dui_start_delete_unattached_images');

function dui_start_delete_unattached_images() {
    check_ajax_referer('delete_unattached_images_nonce', 'nonce');

    $total_count = dui_get_total_unattached_images_count();

    $batch_size = 10;

    $batches = ceil( $total_count / $batch_size );

    for ( $i = 0; $i <= $batches; ++$i ) {
        $offset = $i * $batch_size;
        // Schedule the background task
        as_schedule_single_action(time(), 'delete_unattached_images_task', array( $offset, $batch_size ), 'action-scheduler');
    }

    
    wp_send_json_success(array(
        'message' => 'Batch processing started. Will be deleting ' . $batches . ' attachment batches',
        'total_count' => $total_count
    ));
}
add_filter( 'action_scheduler_queue_runner_concurrent_batches', 'dui_increase_action_scheduler_concurrent_batches' );

/**
 * Increase action scheduler concurrent batches.
 *
 * @param integer $concurrent_batches Number of concurrent batches.
 *
 * @return integer Number of concurrent batches.
 */
function dui_increase_action_scheduler_concurrent_batches( int $concurrent_batches ) {
    return 5;
}

function dui_get_total_unattached_images_count() {
    global $wpdb;

    return $wpdb->get_var("
        SELECT COUNT(*)
        FROM $wpdb->posts
        WHERE post_type = 'attachment'
        AND post_parent = 0
        AND post_status = 'inherit'
    ");
}
function dui_is_image_unused($image_id)
{
    global $wpdb;

    // Check post content
    $post_content_query = $wpdb->prepare(
        "SELECT COUNT(*) FROM $wpdb->posts WHERE post_content LIKE %s AND post_type != 'attachment'",
        '%' . $wpdb->esc_like(wp_get_attachment_url($image_id)) . '%'
    );

    // Check custom fields
    $post_meta_query = $wpdb->prepare(
        "SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_value LIKE %s",
        '%' . $wpdb->esc_like(wp_get_attachment_url($image_id)) . '%'
    );

    $is_used_in_posts = $wpdb->get_var($post_content_query) > 0;
    $is_used_in_meta = $wpdb->get_var($post_meta_query) > 0;

    return !($is_used_in_posts || $is_used_in_meta);
}