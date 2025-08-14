<?php
/**
 * Plugin Name: Learndash Group Slot Picker
 * Plugin URI:  https://example.com
 * Description: Adds a slot picker with assigned course support to LearnDash Groups.
 * Version:     1.0.0
 * Author:      Angelic Sanoy
 * Author URI:  https://bookwormhead.com/
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ld-group-schedule
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

/**
 * Load plugin only if LearnDash is active.
 */
add_action( 'plugins_loaded', function () {
    // Check if LearnDash main class exists.
    if ( ! class_exists( 'SFWD_LMS' ) && ! class_exists( 'LearnDash' ) ) {
        return;
    }

    // Include required files.
    require_once plugin_dir_path( __FILE__ ) . 'ld-group-schedule.php';
    require_once plugin_dir_path( __FILE__ ) . 'ld-group-shortcode.php';
} );
