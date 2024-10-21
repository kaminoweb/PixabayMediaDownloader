<?php
/*
Plugin Name: Pixabay Media Downloader
Description: Search and download Pixabay images directly to your WordPress media library.
Version: 1.2
Author: Your Name
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class PixabayMediaDownloader {
    private $api_key;

    public function __construct() {
        // Define constants
        define('PMD_PLUGIN_DIR', plugin_dir_path(__FILE__));
        define('PMD_PLUGIN_URL', plugin_dir_url(__FILE__));

        // Initialize plugin
        $this->api_key = $this->get_api_key();

        // Hook into WordPress
        add_action('admin_menu', array($this, 'add_menu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_pmd_search_pixabay', array($this, 'search_pixabay'));
        add_action('wp_ajax_pmd_download_images', array($this, 'download_images'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    // Register settings for API key
    public function register_settings() {
        register_setting('pmd_settings_group', 'pmd_api_key', array(
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => '',
        ));
    }

    // Get API key from settings
    private function get_api_key() {
        return get_option('pmd_api_key', '');
    }

    // Add menu and settings page
    public function add_menu_page() {
        // Add settings page
        add_options_page(
            'Pixabay Media Downloader Settings',
            'Pixabay Downloader',
            'manage_options',
            'pmd-settings',
            array($this, 'render_settings_page')
        );

        // Add main plugin page under Media
        add_media_page(
            'Pixabay Downloader',
            'Pixabay Downloader',
            'manage_options',
            'pixabay-downloader',
            array($this, 'render_plugin_page')
        );
    }

    // Render settings page
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1>Pixabay Media Downloader Settings</h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('pmd_settings_group');
                do_settings_sections('pmd_settings_group');
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Pixabay API Key</th>
                        <td>
                            <input type="text" name="pmd_api_key" value="<?php echo esc_attr($this->api_key); ?>" size="50" required />
                            <p class="description">Obtain your Pixabay API key from <a href="https://pixabay.com/api/docs/" target="_blank">Pixabay API Documentation</a>.</p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    // Enqueue scripts and styles
    public function enqueue_scripts($hook) {
        // Load scripts only on our plugin pages
        if ($hook !== 'media_page_pixabay-downloader' && $hook !== 'settings_page_pmd-settings') {
            return;
        }

        // Enqueue CSS
        wp_enqueue_style('pmd-styles', PMD_PLUGIN_URL . 'css/styles.css', array(), '1.2');

        // Enqueue Dashicons
        wp_enqueue_style('dashicons');

        // Enqueue JavaScript
        wp_enqueue_script('pmd-scripts', PMD_PLUGIN_URL . 'js/scripts.js', array('jquery'), '1.2', true);

        // Localize script with AJAX URL and nonce
        wp_localize_script('pmd-scripts', 'pmd_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('pmd_nonce'),
        ));
    }

    // Render the main plugin page
    public function render_plugin_page() {
        if (!$this->api_key) {
            echo '<div class="notice notice-warning"><p>Please set your Pixabay API key in the <a href="' . admin_url('options-general.php?page=pmd-settings') . '">settings page</a>.</p></div>';
            return;
        }
        ?>
        <div class="wrap">
            <h1>Pixabay Media Downloader</h1>
            <form id="pmd-search-form" class="pmd-search-form">
                <input type="text" id="pmd-search-query" placeholder="Search for images..." required />
                
                <!-- Orientation Selection -->
                <select id="pmd-orientation" name="orientation">
                    <option value="">Any Orientation</option>
                    <option value="horizontal">Horizontal</option>
                    <option value="vertical">Vertical</option>
                </select>
                
                <!-- Picture Size Inputs -->
                <input type="number" id="pmd-min-width" name="min_width" placeholder="Min Width (px)" min="0" />
                <input type="number" id="pmd-min-height" name="min_height" placeholder="Min Height (px)" min="0" />
                
                <button type="submit" class="button button-primary"><span class="dashicons dashicons-search"></span> Search</button>
            </form>
            <div id="pmd-results"></div>
            <button id="pmd-download-selected" class="button button-success"><span class="dashicons dashicons-download"></span> Download Selected</button>
        </div>
        <?php
    }

    // Handle Pixabay API search
    public function search_pixabay() {
        check_ajax_referer('pmd_nonce', 'nonce');

        if (!$this->api_key) {
            wp_send_json_error('API key not set.');
        }

        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 20;
        $orientation = isset($_POST['orientation']) ? sanitize_text_field($_POST['orientation']) : '';
        $min_width = isset($_POST['min_width']) ? intval($_POST['min_width']) : '';
        $min_height = isset($_POST['min_height']) ? intval($_POST['min_height']) : '';

        if (empty($query)) {
            wp_send_json_error('Empty search query.');
        }

        // Build the API URL with additional parameters
        $api_url = "https://pixabay.com/api/?key={$this->api_key}&q=" . urlencode($query) . "&image_type=photo&per_page={$per_page}&page={$page}";

        if (!empty($orientation) && in_array($orientation, ['horizontal', 'vertical'])) {
            $api_url .= "&orientation={$orientation}";
        }

        if (!empty($min_width)) {
            $api_url .= "&min_width={$min_width}";
        }

        if (!empty($min_height)) {
            $api_url .= "&min_height={$min_height}";
        }

        $response = wp_remote_get($api_url);

        if (is_wp_error($response)) {
            wp_send_json_error($response->get_error_message());
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            wp_send_json_error($data['error']);
        }

        wp_send_json_success($data);
    }

    // Handle image downloads
    public function download_images() {
        check_ajax_referer('pmd_nonce', 'nonce');

        if (!current_user_can('upload_files')) {
            wp_send_json_error('Unauthorized user.');
        }

        $images = isset($_POST['images']) ? $_POST['images'] : array();
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';

        if (empty($images) || !is_array($images)) {
            wp_send_json_error('No images selected.');
        }

        if (empty($query)) {
            wp_send_json_error('Search query not provided.');
        }

        // Sanitize the search query to use in filenames
        $sanitized_query = sanitize_title($query); // Converts to lowercase, removes special chars, replaces spaces with hyphens

        $downloaded = 0;
        $failed = 0;

        foreach ($images as $image) {
            $url = esc_url_raw($image['url']);
            $id = isset($image['id']) ? sanitize_text_field($image['id']) : uniqid();
            $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
            $extension = strtolower($extension) ? strtolower($extension) : 'jpg'; // Default to jpg if extension is missing

            // Generate a shorter, meaningful filename
            $filename = "{$sanitized_query}_{$id}.{$extension}";

            $upload_dir = wp_upload_dir();
            $file_path = $upload_dir['path'] . '/' . $filename;

            // Avoid filename collisions
            if (file_exists($file_path)) {
                $filename = "{$sanitized_query}_{$id}_" . uniqid() . ".{$extension}";
                $file_path = $upload_dir['path'] . '/' . $filename;
            }

            // Download image
            $image_response = wp_remote_get($url);

            if (is_wp_error($image_response)) {
                $failed++;
                continue;
            }

            $image_data = wp_remote_retrieve_body($image_response);

            if ($image_data) {
                // Save the image to the uploads directory
                $saved = file_put_contents($file_path, $image_data);

                if ($saved === false) {
                    $failed++;
                    continue;
                }

                // Check the file type
                $wp_filetype = wp_check_filetype($filename, null);

                // Prepare attachment data
                $attachment = array(
                    'post_mime_type' => $wp_filetype['type'],
                    'post_title'     => sanitize_file_name($filename),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                );

                // Insert the attachment
                $attach_id = wp_insert_attachment($attachment, $file_path);

                if (is_wp_error($attach_id)) {
                    $failed++;
                    continue;
                }

                // Generate metadata and update attachment
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
                wp_update_attachment_metadata($attach_id, $attach_data);

                $downloaded++;
            } else {
                $failed++;
            }
        }

        if ($downloaded > 0 && $failed === 0) {
            wp_send_json_success("Successfully downloaded {$downloaded} image(s).");
        } elseif ($downloaded > 0 && $failed > 0) {
            wp_send_json_success("Successfully downloaded {$downloaded} image(s). {$failed} image(s) failed to download.");
        } else {
            wp_send_json_error('Failed to download images.');
        }
    }
}

// Initialize the plugin
new PixabayMediaDownloader();

