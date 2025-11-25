<?php
/**
 * Clean up WordPress before installing World Time AI v0.1.8
 * Run this BEFORE uploading the new plugin
 * Visit: https://yoursite.com/cleanup-before-install.php
 * DELETE THIS FILE after use!
 */

require_once('wp-load.php');

if (!current_user_can('administrator')) {
    die('Admin access required');
}

echo "<h2>World Time AI - Pre-Installation Cleanup</h2>";
echo "<style>
    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 20px; }
    .success { color: #46b450; font-weight: bold; }
    .warning { color: #f0b849; font-weight: bold; }
    .error { color: #dc3232; font-weight: bold; }
    .step { background: #f5f5f5; padding: 15px; margin: 15px 0; border-left: 4px solid #0073aa; }
    pre { background: #f0f0f0; padding: 10px; overflow-x: auto; }
</style>";

// Step 1: Check file system
echo "<div class='step'>";
echo "<h3>Step 1: File System Check</h3>";
$plugins_dir = WP_PLUGIN_DIR;
$related_folders = array();

$all_dirs = @scandir($plugins_dir);
foreach ($all_dirs as $dir) {
    if ($dir === '.' || $dir === '..') continue;
    
    if (stripos($dir, 'world-time') !== false || 
        stripos($dir, 'what_is_the_time') !== false) {
        $related_folders[] = $dir;
    }
}

if (!empty($related_folders)) {
    echo "<p class='warning'>⚠ Found " . count($related_folders) . " related folder(s):</p>";
    echo "<ul>";
    foreach ($related_folders as $folder) {
        echo "<li><code>/wp-content/plugins/$folder/</code></li>";
    }
    echo "</ul>";
    echo "<p class='error'><strong>ACTION REQUIRED:</strong> You must delete these folders via File Manager or FTP before continuing!</p>";
} else {
    echo "<p class='success'>✓ No old plugin folders found - File system is clean!</p>";
}
echo "</div>";

// Step 2: Clean database
echo "<div class='step'>";
echo "<h3>Step 2: Database Cleanup</h3>";

global $wpdb;

// Deactivate plugin
$active_plugins = get_option('active_plugins', array());
$cleaned = false;

foreach ($active_plugins as $key => $plugin_path) {
    if (stripos($plugin_path, 'world-time') !== false || 
        stripos($plugin_path, 'what_is_the_time') !== false) {
        unset($active_plugins[$key]);
        $cleaned = true;
        echo "Removed from active: <code>$plugin_path</code><br>";
    }
}

if ($cleaned) {
    update_option('active_plugins', array_values($active_plugins));
    echo "<p class='success'>✓ Plugin deactivated</p>";
} else {
    echo "<p class='success'>✓ Plugin not active</p>";
}

// Clean all related options
$deleted_total = 0;

$patterns = array(
    '%world%time%',
    '%wta_%',
    '%what_is_the_time%',
);

foreach ($patterns as $pattern) {
    $deleted = $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $pattern
    ));
    $deleted_total += $deleted;
}

if ($deleted_total > 0) {
    echo "<p class='success'>✓ Deleted $deleted_total database entries</p>";
} else {
    echo "<p class='success'>✓ No old database entries found</p>";
}

// Clean transients
$deleted = $wpdb->query(
    "DELETE FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_%plugin%' 
     OR option_name LIKE '_site_transient_%plugin%'"
);

if ($deleted > 0) {
    echo "<p class='success'>✓ Deleted $deleted plugin transients</p>";
}

// Clear ALL plugin-related caches aggressively
wp_cache_flush();
delete_transient('plugin_slugs');
delete_site_transient('update_plugins');

// Clear plugin cache directory hash
delete_transient('plugins_delete_result');

// Force WordPress to rebuild plugin list
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%plugin%cache%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_site_transient_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_%'");

// Clear object cache if available
if (function_exists('wp_cache_flush')) {
    wp_cache_flush();
}

// Force WordPress to forget about plugin folder structure
delete_option('recently_activated');
delete_option('uninstall_plugins');

echo "<p class='success'>✓ ALL caches and transients cleared (aggressive mode)</p>";
echo "</div>";

// Step 3: Verification
echo "<div class='step'>";
echo "<h3>Step 3: Verification</h3>";

$all_plugins = get_plugins();
$found = false;

foreach ($all_plugins as $plugin_path => $plugin_data) {
    if (stripos($plugin_path, 'world-time') !== false || 
        stripos($plugin_path, 'what_is_the_time') !== false) {
        echo "<p class='warning'>⚠ WordPress still sees: <code>$plugin_path</code></p>";
        $found = true;
    }
}

if (!$found) {
    echo "<p class='success'>✓ WordPress doesn't detect any old plugin files</p>";
}

echo "</div>";

// Final instructions
echo "<hr>";
echo "<h3>✓ Cleanup Complete!</h3>";

if (!empty($related_folders)) {
    echo "<div style='background: #fff3cd; padding: 15px; border-left: 4px solid #f0b849;'>";
    echo "<strong>⚠ IMPORTANT: Delete these folders first!</strong><br><br>";
    echo "Via File Manager or FTP, delete:<br>";
    foreach ($related_folders as $folder) {
        echo "→ <code>/wp-content/plugins/$folder/</code><br>";
    }
    echo "<br>After deletion, refresh this page to verify.";
    echo "</div>";
} else {
    echo "<div style='background: #d4edda; padding: 15px; border-left: 4px solid #46b450;'>";
    echo "<strong>✓ Ready for clean installation!</strong><br><br>";
    echo "<ol>";
    echo "<li><strong>Delete this file</strong> (cleanup-before-install.php) for security</li>";
    echo "<li>Go to: <strong>WordPress Admin → Plugins → Add New → Upload Plugin</strong></li>";
    echo "<li>Upload: <strong>world-time-ai.zip</strong></li>";
    echo "<li>Click: <strong>Install Now</strong></li>";
    echo "<li>WordPress will create: <code>/wp-content/plugins/world-time-ai/</code> (NOT -1!)</li>";
    echo "<li>Click: <strong>Activate</strong></li>";
    echo "</ol>";
    echo "<p><a href='/wp-admin/plugin-install.php?tab=upload' class='button button-primary' style='background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;'>Go to Upload Plugin →</a></p>";
    echo "</div>";
}

echo "<br><p style='color: #dc3232;'><strong>IMPORTANT:</strong> Delete this file (cleanup-before-install.php) when done!</p>";
?>

