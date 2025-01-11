<?php
/**
 * Plugin Name: Pixabay Media Downloader
 * Plugin URI: https://kaminoweb.com/
 * Description: Download images from Pixabay directly into your WordPress Media Library.
 * Version: 1.0.0
 * Author: KAMINOWEB INC
 * Author URI: https://kaminoweb.com/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class PMD_Pixabay_Media_Downloader {

    private $api_key;
    const VERSION = '1.0.0';
    const OPTION_NAME = 'pmd_pixabay_api_key';
    const SETTINGS_GROUP = 'pmd_pixabay_settings_group';
    const SCRIPT_HANDLE = 'pmd-pixabay-scripts';
    const LOCALIZE_HANDLE = 'pmd_pixabay_ajax';
    const NONCE_NAME = 'pmd_pixabay_nonce';

    public function __construct() {
        $this->api_key = get_option( self::OPTION_NAME, '' );

        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action( 'wp_ajax_pmd_pixabay_search', array( $this, 'search_pixabay' ) );
        add_action( 'wp_ajax_pmd_pixabay_download_images', array( $this, 'download_images' ) );
    }

    public function register_settings() {
        register_setting( self::SETTINGS_GROUP, self::OPTION_NAME, array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );

        add_settings_section(
            'pmd_pixabay_main_section',
            __( 'Pixabay API Settings', 'pixabay-media-downloader' ),
            null,
            self::SETTINGS_GROUP
        );

        add_settings_field(
            'pmd_pixabay_api_key_field',
            __( 'Pixabay API Key', 'pixabay-media-downloader' ),
            array( $this, 'api_key_field_callback' ),
            self::SETTINGS_GROUP,
            'pmd_pixabay_main_section'
        );
    }

    public function api_key_field_callback() {
        ?>
        <input type="text" name="<?php echo esc_attr( self::OPTION_NAME ); ?>" value="<?php echo esc_attr( $this->api_key ); ?>" size="50" required />
        <p class="description"><?php esc_html_e( 'Obtain your Pixabay API key from the ', 'pixabay-media-downloader' ); ?><a href="https://pixabay.com/api/docs/" target="_blank"><?php esc_html_e( 'Pixabay API Documentation', 'pixabay-media-downloader' ); ?></a>.</p>
        <?php
    }

    public function add_menu_page() {
        add_options_page(
            __( 'Pixabay Media Downloader Settings', 'pixabay-media-downloader' ),
            __( 'Pixabay Downloader', 'pixabay-media-downloader' ),
            'manage_options',
            'pmd_pixabay_settings',
            array( $this, 'render_settings_page' )
        );

        add_media_page(
            __( 'Pixabay Media Downloader', 'pixabay-media-downloader' ),
            __( 'Pixabay Downloader', 'pixabay-media-downloader' ),
            'manage_options',
            'pmd_pixabay_downloader',
            array( $this, 'render_plugin_page' )
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Pixabay Media Downloader Settings', 'pixabay-media-downloader' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( self::SETTINGS_GROUP );
                do_settings_sections( self::SETTINGS_GROUP );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public function render_plugin_page() {
        if ( empty( $this->api_key ) ) {
            echo '<div class="notice notice-warning"><p>' . sprintf( __( 'Please set your Pixabay API key in the <a href="%s">settings page</a>.', 'pixabay-media-downloader' ), admin_url( 'options-general.php?page=pmd_pixabay_settings' ) ) . '</p></div>';
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Pixabay Media Downloader', 'pixabay-media-downloader' ); ?></h1>
            <form id="pmd-pixabay-search-form" class="pmd-search-form">
                <input type="text" id="pmd-pixabay-search-query" placeholder="<?php esc_attr_e( 'Search for images...', 'pixabay-media-downloader' ); ?>" required />

                <select id="pmd-pixabay-orientation" name="orientation">
                    <option value=""><?php esc_html_e( 'Any Orientation', 'pixabay-media-downloader' ); ?></option>
                    <option value="horizontal"><?php esc_html_e( 'Horizontal', 'pixabay-media-downloader' ); ?></option>
                    <option value="vertical"><?php esc_html_e( 'Vertical', 'pixabay-media-downloader' ); ?></option>
                </select>

                <input type="number" id="pmd-pixabay-min-width" name="min_width" placeholder="<?php esc_attr_e( 'Min Width (px)', 'pixabay-media-downloader' ); ?>" min="0" />
                <input type="number" id="pmd-pixabay-min-height" name="min_height" placeholder="<?php esc_attr_e( 'Min Height (px)', 'pixabay-media-downloader' ); ?>" min="0" />

                <button type="submit" class="button button-primary"><?php esc_html_e( 'Search', 'pixabay-media-downloader' ); ?></button>
            </form>
            <div id="pmd-pixabay-results"></div>
            <button id="pmd-pixabay-download-selected" class="button button-primary"><strong><?php esc_html_e( 'Download Selected', 'pixabay-media-downloader' ); ?></strong></button>
        </div>
        <?php
    }

    public function enqueue_scripts( $hook ) {
        if ( $hook !== 'media_page_pmd_pixabay_downloader' && $hook !== 'settings_page_pmd_pixabay_settings' ) {
            return;
        }

        wp_enqueue_style( 'pmd-pixabay-styles', plugin_dir_url( __FILE__ ) . 'css/styles.css', array(), self::VERSION );
        wp_enqueue_style( 'dashicons' );
        wp_enqueue_script( self::SCRIPT_HANDLE, plugin_dir_url( __FILE__ ) . 'js/scripts-pixabay.js', array( 'jquery' ), self::VERSION, true );

        wp_localize_script( self::SCRIPT_HANDLE, self::LOCALIZE_HANDLE, array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( self::NONCE_NAME ),
        ) );
    }

    public function search_pixabay() {
        check_ajax_referer( self::NONCE_NAME, 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( __( 'You do not have sufficient permissions to perform this action.', 'pixabay-media-downloader' ) );
        }

        $query      = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';
        $page       = isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1;
        $orientation = isset( $_POST['orientation'] ) ? sanitize_text_field( wp_unslash( $_POST['orientation'] ) ) : '';
        $min_width  = isset( $_POST['min_width'] ) ? intval( $_POST['min_width'] ) : '';
        $min_height = isset( $_POST['min_height'] ) ? intval( $_POST['min_height'] ) : '';

        if ( empty( $query ) ) {
            wp_send_json_error( __( 'Search query cannot be empty.', 'pixabay-media-downloader' ) );
        }

        $transient_key = 'pmd_pixabay_' . md5( $query . '_' . $page . '_' . $orientation . '_' . $min_width . '_' . $min_height );
        $cached_data   = get_transient( $transient_key );

        if ( false !== $cached_data ) {
            wp_send_json_success( $cached_data );
        }

        $api_url = add_query_arg( array(
            'key'        => $this->api_key,
            'q'          => urlencode( $query ),
            'image_type' => 'photo',
            'per_page'   => 20,
            'page'       => $page,
        ), 'https://pixabay.com/api/' );

        if ( ! empty( $orientation ) && in_array( $orientation, array( 'horizontal', 'vertical' ), true ) ) {
            $api_url = add_query_arg( 'orientation', $orientation, $api_url );
        }

        if ( ! empty( $min_width ) ) {
            $api_url = add_query_arg( 'min_width', $min_width, $api_url );
        }

        if ( ! empty( $min_height ) ) {
            $api_url = add_query_arg( 'min_height', $min_height, $api_url );
        }

        $response = wp_remote_get( $api_url );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( __( 'Failed to connect to Pixabay API: ', 'pixabay-media-downloader' ) . $response->get_error_message() );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( isset( $data['error'] ) ) {
            wp_send_json_error( __( 'Pixabay API Error: ', 'pixabay-media-downloader' ) . $data['error'] );
        }

        if ( ! isset( $data['hits'] ) ) {
            wp_send_json_error( __( 'Unexpected response from Pixabay API.', 'pixabay-media-downloader' ) );
        }

        set_transient( $transient_key, $data, HOUR_IN_SECONDS );

        wp_send_json_success( $data );
    }

    public function download_images() {
        check_ajax_referer( self::NONCE_NAME, 'nonce' );

        if ( ! current_user_can( 'upload_files' ) ) {
            wp_send_json_error( __( 'You do not have sufficient permissions to perform this action.', 'pixabay-media-downloader' ) );
        }

        $images = array();

        if ( isset( $_POST['images'] ) && is_array( $_POST['images'] ) ) {
            foreach ( wp_unslash( $_POST['images'] ) as $image ) {
                $images[] = array(
                    'url' => isset( $image['url'] ) ? esc_url_raw( $image['url'] ) : '',
                    'id'  => isset( $image['id'] ) ? sanitize_text_field( $image['id'] ) : uniqid(),
                );
            }
        }

        $query  = isset( $_POST['query'] ) ? sanitize_text_field( wp_unslash( $_POST['query'] ) ) : '';

        if ( empty( $images ) || ! is_array( $images ) ) {
            wp_send_json_error( __( 'No images were selected for download.', 'pixabay-media-downloader' ) );
        }

        if ( empty( $query ) ) {
            wp_send_json_error( __( 'Search query is missing. Cannot proceed with downloads.', 'pixabay-media-downloader' ) );
        }

        $sanitized_query = sanitize_title( $query );

        $downloaded = 0;
        $failed     = 0;

        foreach ( $images as $image ) {
            $url = isset( $image['url'] ) ? esc_url_raw( $image['url'] ) : '';
            $id  = isset( $image['id'] ) ? sanitize_text_field( $image['id'] ) : uniqid();

            if ( empty( $url ) ) {
                $failed++;
                continue;
            }

            $extension = pathinfo( parse_url( $url, PHP_URL_PATH ), PATHINFO_EXTENSION );
            $extension = strtolower( $extension ) ? strtolower( $extension ) : 'jpg';

            $filename = "{$sanitized_query}_{$id}.{$extension}";

            $upload_dir = wp_upload_dir();
            $file_path  = trailingslashit( $upload_dir['path'] ) . $filename;

            if ( file_exists( $file_path ) ) {
                $filename  = "{$sanitized_query}_{$id}_" . uniqid() . ".{$extension}";
                $file_path = trailingslashit( $upload_dir['path'] ) . $filename;
            }

            $image_response = wp_remote_get( $url );

            if ( is_wp_error( $image_response ) ) {
                $failed++;
                continue;
            }

            $image_data = wp_remote_retrieve_body( $image_response );

            if ( $image_data ) {
                $saved = file_put_contents( $file_path, $image_data );

                if ( false === $saved ) {
                    $failed++;
                    continue;
                }

                $wp_filetype = wp_check_filetype( $filename, null );

                $attachment = array(
                    'post_mime_type' => $wp_filetype['type'],
                    'post_title'     => sanitize_file_name( $filename ),
                    'post_content'   => '',
                    'post_status'    => 'inherit',
                );

                $attach_id = wp_insert_attachment( $attachment, $file_path );

                if ( is_wp_error( $attach_id ) ) {
                    $failed++;
                    continue;
                }

                require_once( ABSPATH . 'wp-admin/includes/image.php' );
                $attach_data = wp_generate_attachment_metadata( $attach_id, $file_path );
                wp_update_attachment_metadata( $attach_id, $attach_data );

                $downloaded++;
            } else {
                $failed++;
            }
        }

        if ( $downloaded > 0 && $failed === 0 ) {
            wp_send_json_success( sprintf( __( 'Successfully downloaded %d image(s).', 'pixabay-media-downloader' ), $downloaded ) );
        } elseif ( $downloaded > 0 && $failed > 0 ) {
            wp_send_json_success( sprintf( __( 'Successfully downloaded %d image(s). %d image(s) failed to download.', 'pixabay-media-downloader' ), $downloaded, $failed ) );
        } else {
            wp_send_json_error( __( 'Failed to download images.', 'pixabay-media-downloader' ) );
        }
    }
}

new PMD_Pixabay_Media_Downloader();
