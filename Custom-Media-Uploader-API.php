<?php
/**
 * Plugin Name: Custom Media Uploader API
 * Description: A secure and efficient custom media uploader via REST API.
 * Version: 1.0
 * Author: Manoj & ChatGPT
 * Author URI: https://manojlk.work
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Admin notice for WP_MEDIA_API_SLUG definition and API usage
add_action('admin_init', 'acmu_check_slug_defined');
function acmu_check_slug_defined() {
    if (is_admin() && current_user_can('activate_plugins') && !defined('WP_MEDIA_API_SLUG')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>Custom Media Uploader API:</strong> </br></br>Please define WP_MEDIA_API_SLUG in your wp-config.php for enhanced security. Add the following line:</p>';
            echo '<code>define(\'WP_MEDIA_API_SLUG\', \'replace_your_unique_value_here\');</code>';
            echo '<p>Once defined, you can upload files via the API using the following curl command (replace <em>your_wp_url</em> with your WordPress site URL, <em>your_username</em> with your actual username, and <em>your_password</em> with your actual password):</p>';
            echo '<pre>curl --location \'https://your_wp_url/wp-json/wp/v2/replace_your_unique_value_here\' \\' . "\n";
            echo '--header \'Content-Type: application/json\' \\' . "\n";
            echo '--data \'{ "file_url": "http://example.com/image.jpg" }\' \\' . "\n";
            echo '--user \'your_username:your_password\'</pre>';
            echo '</div>';
        });
    }
}

// Initializes a custom REST API endpoint for media upload. It uses a predefined slug or defaults to 'cmv_api'. The endpoint allows file uploads by users with the 'upload_files' capability, and it includes validation and sanitization for the file URL.
add_action('rest_api_init', function () {
    $route_slug = defined('WP_MEDIA_API_SLUG') ? WP_MEDIA_API_SLUG : 'cmv_api'; // Fallback to 'cmv_api' if not defined

    register_rest_route('wp/v2', '/' . $route_slug, array(
        'methods' => WP_REST_Server::CREATABLE,
        'callback' => 'acmu_upload_media',
        'permission_callback' => function () { return current_user_can('upload_files'); },
        'args' => array(
            'file_url' => array(
                'required' => true,
                'validate_callback' => 'acmu_validate_file_url',
                'sanitize_callback' => 'esc_url_raw'
            ),
        ),
    ));
});



// Validates a provided URL for file upload through a request, checking for valid URL format and allowed file types (JPEG, PNG, GIF images, MP4 video, MP3 audio). Returns true if valid, or a WP_Error with a 400 status and a descriptive error message if not.

function acmu_validate_file_url($file_url, $request, $key) {
    if (!filter_var($file_url, FILTER_VALIDATE_URL)) {
        return new WP_Error('invalid_url', 'Invalid URL provided.', array('status' => 400));
    }

    $file_type = wp_check_filetype(basename($file_url));
    $allowed_types = [
        'image/jpeg', 
        'image/png', 
        'image/gif',
        'video/mp4',  
        'audio/mp3'   
    ]; // Extend as needed

    if (!in_array($file_type['type'], $allowed_types)) {
        return new WP_Error('invalid_file_type', 'Unsupported file type.', array('status' => 400));
    }

    return true;
}


// Function 'acmu_upload_media': Handles media file upload via a REST API request.
// 1. Downloads the file from the provided URL in the request.
// 2. Checks for errors during download and logs them if any.
// 3. Prepares the file for upload by setting up necessary information like name, type, and size.
// 4. Sets override parameters to bypass default WordPress upload checks.
// 5. Performs the file upload process and handles any errors by logging them and cleaning up temporary files.
// 6. Prepares attachment post data to integrate the uploaded file into the WordPress media library.
// 7. Inserts the attachment post into the database, making the file a part of the media library.
// 8. For image files, generates metadata (like thumbnails) and updates the attachment's metadata in the database.
// 9. Cleans up the temporary file used during the upload process.
// 10. Logs any errors encountered while inserting the attachment post.
// 11. Returns a success response with the attachment ID and URL if the upload is successful.

function acmu_upload_media(WP_REST_Request $request) {
    $file_url = $request['file_url'];
    $tmp_file = download_url($file_url);

    if (is_wp_error($tmp_file)) {
        acmu_log('Error downloading file: ' . $tmp_file->get_error_message());
        return $tmp_file; // Return the WP_Error.
    }

    $file = array(
        'name'     => basename($file_url), // The file name.
        'type'     => wp_check_filetype(basename($file_url))['type'], // Mime type.
        'tmp_name' => $tmp_file, // Temporary file path.
        'error'    => 0,
        'size'     => filesize($tmp_file),
    );

    $overrides = array(
        'test_form' => false,
        'test_size' => true,
        'test_upload' => true,
    );

    // Handle the file upload.
    $results = wp_handle_sideload($file, $overrides);

    if (!empty($results['error'])) {
        @unlink($tmp_file); // Delete the temporary file.
        acmu_log('Upload error: ' . $results['error']);
        return new WP_Error('upload_error', $results['error'], array('status' => 400));
    }

    // Prepare an array of post data for the attachment.
    $wp_upload_dir = wp_upload_dir();
    $attachment = array(
        'guid'           => $wp_upload_dir['url'] . '/' . basename($results['file']),
        'post_mime_type' => $results['type'],
        'post_title'     => preg_replace('/\.[^.]+$/', '', basename($results['file'])),
        'post_content'   => '',
        'post_status'    => 'inherit',
    );

    // Insert the attachment.
    $attach_id = wp_insert_attachment($attachment, $results['file']);

    // For images, generate metadata and update the database record.
    if (strpos($results['type'], 'image') !== false) {
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attach_data = wp_generate_attachment_metadata($attach_id, $results['file']);
        wp_update_attachment_metadata($attach_id, $attach_data);
    }

    @unlink($tmp_file); // Delete the temporary file.

    if (is_wp_error($attach_id)) {
        acmu_log('Error inserting attachment: ' . $attach_id->get_error_message());
        return new WP_Error('attachment_error', 'Error inserting attachment.', array('status' => 400));
    }

    // Return a successful response.
    return new WP_REST_Response(array(
        'message' => 'File uploaded successfully',
        'attachment_id' => $attach_id,
        'url' => wp_get_attachment_url($attach_id)
    ), 200);
}

// This function logs a custom message to the WordPress debug log if WP_DEBUG_LOG is enabled. It prefixes the message with '[Custom Media Uploader API]'.
function acmu_log($message) {
    if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('[Custom Media Uploader API] ' . $message);
    }
}
