<?php
/**
 * Plugin Name: Zedd Plugins 🙂
 * Plugin URI:  https://example.com
 * Description: Plugin dasar WordPress buatan Zedd. 🙂
 * Version:     1.0
 * Author:      Zedd
 * Author URI:  https://example.com
 */

// Cegah akses langsung ke file
if (!defined('ABSPATH')) {
    exit;
}

// Fungsi saat plugin diaktifkan
function zedd_on_activate() {
    add_option('zedd_plugin_installed', time());
}
register_activation_hook(__FILE__, 'zedd_on_activate');

// Fungsi saat plugin dinonaktifkan
function zedd_on_deactivate() {
    delete_option('zedd_plugin_installed');
}
register_deactivation_hook(__FILE__, 'zedd_on_deactivate');

// Menu admin
function zedd_admin_menu() {
    add_menu_page(
        'Zedd Plugins 🙂',    // Page Title
        'Zedd Plugins 🙂',    // Menu Title
        'manage_options',
        'zedd-plugins',
        'zedd_settings_page'
    );
}
add_action('admin_menu', 'zedd_admin_menu');

// Halaman pengaturan
function zedd_settings_page() {
    echo "<h1>Zedd Plugins 🙂</h1>";
    echo "<p>Selamat datang di plugin buatan Zedd. Tetap semangat bro! 🙂</p>";
}
