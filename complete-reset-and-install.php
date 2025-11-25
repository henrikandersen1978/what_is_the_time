<?php
/**
 * Complete Reset and Clean Install for World Time AI
 * This script completely removes all traces and prepares for fresh install
 * Upload to WordPress root and run: https://yoursite.com/complete-reset-and-install.php
 * DELETE THIS FILE AFTER USE!
 */

require_once('wp-load.php');

if (!current_user_can('administrator')) {
    die('You must be an administrator.');
}

echo "<h2>World Time AI - Complete Reset</h2>";

// 1. Remove from active plugins
echo "<h3>Step 1: Deactivate Plugin</h3>";
$active_plugins = get_option('active_plugins', array());
$updated = false;

foreach ($active_plugins as $key => $plugin_path) {
    if (strpos($plugin_path, 'world-time') !== false || 
        strpos($plugin_path, 'what_is_the_time') !== false ||
        strpos($plugin_path, 'wta-') !== false) {
        unset($active_plugins[$key]);
        $updated = true;
        echo "Removed from active: $plugin_path<br>";
    }
}

if ($updated) {
    update_option('active_plugins', array_values($active_plugins));
    echo "<strong style='color:green'>✓ Plugin deactivated</strong><br>";
} else {
    echo "No active entries found<br>";
}

// 2. Clean database
echo "<h3>Step 2: Clean Database</h3>";
global $wpdb;

$queries = array(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%world%time%'",
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%wta_%'",
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%what_is_the_time%'",
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%plugin%'",
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_%plugin%'",
    "DELETE FROM {$wpdb->options} WHERE option_name = 'recently_activated'",
);

foreach ($queries as $query) {
    $deleted = $wpdb->query($query);
    if ($deleted > 0) {
        echo "Deleted $deleted rows<br>";
    }
}

// 3. Clear all caches
echo "<h3>Step 3: Clear Caches</h3>";
wp_cache_flush();
delete_transient('plugin_slugs');
delete_site_transient('update_plugins');
wp_cache_delete('plugins', 'plugins');
echo "<strong style='color:green'>✓ All caches cleared</strong><br>";

// 4. Check file system
echo "<h3>Step 4: Check File System</h3>";
$plugins_dir = WP_PLUGIN_DIR;
$folders_to_check = array(
    'world-time-ai',
    'world-time-ai-1', 
    'world-time-ai-2',
    'what_is_the_time',
);

echo "Plugins directory: $plugins_dir<br><br>";
echo "<strong>Found folders:</strong><br>";

$found_folders = array();
foreach ($folders_to_check as $folder) {
    $path = $plugins_dir . '/' . $folder;
    if (is_dir($path)) {
        $found_folders[] = $folder;
        echo "- <span style='color:orange'>$folder/</span> EXISTS<br>";
        
        // Check if has files
        $files = scandir($path);
        $file_count = count($files) - 2; // exclude . and ..
        echo "  → Contains $file_count items<br>";
    }
}

if (empty($found_folders)) {
    echo "<strong style='color:green'>✓ No old plugin folders found</strong><br>";
} else {
    echo "<br><strong style='color:red'>⚠ WARNING: Old folders still exist!</strong><br>";
    echo "You must delete these folders manually via File Manager:<br>";
    foreach ($found_folders as $folder) {
        echo "- /wp-content/plugins/<strong>$folder</strong>/<br>";
    }
}

echo "<br><h3>✓ RESET COMPLETE!</h3>";
echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "<ol>";
echo "<li><strong style='color:red'>Via File Manager or FTP:</strong> Delete ALL folders listed above from /wp-content/plugins/</li>";
echo "<li><strong>Go to:</strong> WordPress Admin → Plugins → Add New → Upload Plugin</li>";
echo "<li><strong>Upload:</strong> world-time-ai.zip (the one with 66 KB)</li>";
echo "<li><strong>Verify:</strong> WordPress creates folder named 'world-time-ai' (NOT world-time-ai-1)</li>";
echo "<li><strong>Activate:</strong> The plugin</li>";
echo "<li><strong style='color:red'>DELETE THIS FILE</strong> (complete-reset-and-install.php) for security!</li>";
echo "</ol>";

echo "<p><a href='/wp-admin/plugins.php?page=plugin-install.php' style='background:#0073aa;color:white;padding:10px 20px;text-decoration:none;border-radius:3px;'>Go to Upload Plugin →</a></p>";
?>


