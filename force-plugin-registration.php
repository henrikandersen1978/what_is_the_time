<?php
/**
 * Force WordPress to recognize World Time AI plugin
 * Upload this file to your WordPress root directory
 * Run it by visiting: https://yoursite.com/force-plugin-registration.php
 * Delete this file after use!
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is admin
if (!current_user_can('administrator')) {
    die('You must be an administrator to run this script.');
}

echo "<h2>World Time AI - Force Plugin Registration</h2>";

// 1. Check if plugin files exist
$plugin_dir = WP_PLUGIN_DIR . '/world-time-ai';
$plugin_file = $plugin_dir . '/world-time-ai.php';

echo "<h3>Step 1: Check Files</h3>";
echo "Plugin directory: " . $plugin_dir . "<br>";
echo "Plugin directory exists: " . (is_dir($plugin_dir) ? '<strong style="color:green">YES ✓</strong>' : '<strong style="color:red">NO ✗</strong>') . "<br>";
echo "Plugin file exists: " . (file_exists($plugin_file) ? '<strong style="color:green">YES ✓</strong>' : '<strong style="color:red">NO ✗</strong>') . "<br><br>";

if (!file_exists($plugin_file)) {
    die('<strong style="color:red">ERROR: Plugin file not found! Make sure the folder is named "world-time-ai" (without -1)</strong>');
}

// 2. Get plugin data
echo "<h3>Step 2: Read Plugin Data</h3>";
if (!function_exists('get_plugin_data')) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');
}

$plugin_data = get_plugin_data($plugin_file);
echo "<pre>";
print_r($plugin_data);
echo "</pre>";

// 3. Clean up database
echo "<h3>Step 3: Clean Database</h3>";

// Remove all World Time AI options
global $wpdb;
$deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%world%time%'");
echo "Deleted old World Time AI options: $deleted<br>";

$deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%wta_%'");
echo "Deleted WTA options: $deleted<br>";

// Clean plugin caches
$deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%plugin%'");
echo "Deleted plugin transients: $deleted<br>";

delete_transient('plugin_slugs');
wp_cache_delete('plugins', 'plugins');

echo "<strong style='color:green'>Database cleaned! ✓</strong><br><br>";

// 4. Get current active plugins
echo "<h3>Step 4: Active Plugins</h3>";
$active_plugins = get_option('active_plugins', array());
echo "Currently active plugins:<br><pre>";
print_r($active_plugins);
echo "</pre>";

// Remove any references to world-time-ai or world-time-ai-1
$updated = false;
foreach ($active_plugins as $key => $plugin_path) {
    if (strpos($plugin_path, 'world-time-ai') !== false || strpos($plugin_path, 'what_is_the_time') !== false) {
        unset($active_plugins[$key]);
        $updated = true;
        echo "Removed: $plugin_path<br>";
    }
}

if ($updated) {
    update_option('active_plugins', array_values($active_plugins));
    echo "<strong style='color:green'>Removed old plugin references ✓</strong><br><br>";
}

// 5. Force refresh plugin cache
echo "<h3>Step 5: Refresh Plugin Cache</h3>";
wp_cache_delete('plugins', 'plugins');
delete_site_transient('update_plugins');
echo "<strong style='color:green'>Plugin cache refreshed! ✓</strong><br><br>";

// 6. Get all plugins
echo "<h3>Step 6: Verify Plugin Detection</h3>";
$all_plugins = get_plugins();
$found = false;

foreach ($all_plugins as $plugin_path => $plugin_info) {
    if (strpos($plugin_path, 'world-time-ai') !== false) {
        echo "<strong style='color:green'>✓ Plugin FOUND in WordPress!</strong><br>";
        echo "Path: $plugin_path<br>";
        echo "Name: {$plugin_info['Name']}<br>";
        echo "Version: {$plugin_info['Version']}<br>";
        $found = true;
        break;
    }
}

if (!$found) {
    echo "<strong style='color:red'>✗ Plugin NOT detected by WordPress!</strong><br>";
    echo "This might be a permissions or file corruption issue.<br>";
}

echo "<br><h3>✓ DONE!</h3>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ol>";
echo "<li>Go to WordPress Admin → Plugins</li>";
echo "<li>Refresh the page (F5)</li>";
echo "<li>You should now see 'World Time AI' with an 'Activate' link</li>";
echo "<li>Click Activate</li>";
echo "<li><strong style='color:red'>DELETE THIS FILE (force-plugin-registration.php) for security!</strong></li>";
echo "</ol>";

echo "<p><a href='/wp-admin/plugins.php' style='background:#0073aa;color:white;padding:10px 20px;text-decoration:none;border-radius:3px;'>Go to Plugins Page →</a></p>";
?>


