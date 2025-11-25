<?php
/**
 * NUCLEAR CLEANUP - Last resort before manual installation
 * This will DESTROY all traces of World Time AI from WordPress
 * Upload to WordPress root and run: yoursite.com/nuclear-cleanup.php
 * DELETE THIS FILE immediately after use!
 */

require_once('wp-load.php');

if (!current_user_can('administrator')) {
    die('Admin access required');
}

echo "<h1>🔥 NUCLEAR CLEANUP - World Time AI</h1>";
echo "<style>
    body { font-family: monospace; background: #1e1e1e; color: #fff; padding: 20px; }
    h1 { color: #ff6b6b; }
    .success { color: #51cf66; }
    .warning { color: #ffd43b; }
    .error { color: #ff6b6b; }
    pre { background: #2d2d2d; padding: 10px; border: 1px solid #444; }
</style>";

global $wpdb;

echo "<h2>Step 1: Database Annihilation</h2>";
echo "<pre>";

// Kill ALL active plugin entries
$active_plugins = get_option('active_plugins', array());
$before = count($active_plugins);
$active_plugins = array_filter($active_plugins, function($plugin) {
    return stripos($plugin, 'world-time') === false && stripos($plugin, 'what_is_the_time') === false;
});
update_option('active_plugins', array_values($active_plugins));
echo "✓ Removed " . ($before - count($active_plugins)) . " active plugin entries\n";

// Delete ALL options
$patterns = array('%world%time%', '%wta_%', '%what_is_the_time%', '%world-time%');
$total_deleted = 0;
foreach ($patterns as $pattern) {
    $deleted = $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name LIKE %s", $pattern));
    $total_deleted += $deleted;
}
echo "✓ Deleted $total_deleted database options\n";

// Delete ALL transients (nuclear)
$deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_%'");
echo "✓ Deleted $deleted transients\n";

$deleted = $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_site_transient_%'");
echo "✓ Deleted $deleted site transients\n";

// Delete plugin caches
delete_option('recently_activated');
delete_option('uninstall_plugins');
delete_transient('plugin_slugs');
delete_site_transient('update_plugins');
echo "✓ Deleted plugin system caches\n";

// Flush everything
wp_cache_flush();
echo "✓ WordPress cache flushed\n";

echo "</pre>";

echo "<h2>Step 2: File System Check</h2>";
echo "<pre>";

$plugins_dir = WP_PLUGIN_DIR;
$found_folders = array();

$all_dirs = @scandir($plugins_dir);
foreach ($all_dirs as $dir) {
    if ($dir === '.' || $dir === '..') continue;
    
    if (stripos($dir, 'world-time') !== false || stripos($dir, 'what_is_the_time') !== false) {
        $found_folders[] = $dir;
    }
}

if (!empty($found_folders)) {
    echo "<span class='error'>✗ Found " . count($found_folders) . " folders:</span>\n";
    foreach ($found_folders as $folder) {
        $full_path = $plugins_dir . '/' . $folder;
        echo "  → /wp-content/plugins/$folder/\n";
        
        // Try to delete (might not have permission)
        $deleted = @rmdir($full_path);
        if ($deleted) {
            echo "    <span class='success'>DELETED</span>\n";
        } else {
            echo "    <span class='warning'>Cannot delete - use File Manager!</span>\n";
        }
    }
} else {
    echo "<span class='success'>✓ No plugin folders found</span>\n";
}

echo "</pre>";

echo "<h2>Step 3: Manual Cleanup Instructions</h2>";
echo "<div style='background: #2d2d2d; padding: 20px; border: 2px solid #ff6b6b;'>";
echo "<p class='warning'><strong>⚠ CRITICAL: You MUST do this manually:</strong></p>";
echo "<ol style='color: #ffd43b;'>";
echo "<li>Open your <strong>File Manager</strong> or <strong>FTP</strong></li>";
echo "<li>Go to: <code>/wp-content/plugins/</code></li>";
echo "<li><strong>DELETE</strong> these folders if they exist:";
echo "<ul>";
echo "<li><code>world-time-ai</code></li>";
echo "<li><code>world-time-ai-1</code></li>";
echo "<li><code>world-time-ai-2</code></li>";
echo "<li><code>what_is_the_time</code></li>";
echo "<li>ANY folder with 'world-time' or 'what_is_the_time' in the name</li>";
echo "</ul></li>";
echo "<li><strong>Empty Trash</strong> in File Manager if available</li>";
echo "<li><strong>Wait 30 seconds</strong></li>";
echo "<li><strong>Refresh this page</strong> to verify</li>";
echo "</ol>";
echo "</div>";

echo "<h2>Step 4: Verification</h2>";
echo "<pre>";

// Check WordPress plugin cache
$all_plugins = get_plugins();
$found_in_wp = false;

foreach ($all_plugins as $plugin_path => $plugin_data) {
    if (stripos($plugin_path, 'world-time') !== false || stripos($plugin_path, 'what_is_the_time') !== false) {
        echo "<span class='error'>✗ WordPress still caches: $plugin_path</span>\n";
        $found_in_wp = true;
    }
}

if (!$found_in_wp) {
    echo "<span class='success'>✓ WordPress plugin cache is clean</span>\n";
}

echo "</pre>";

// Final status
$can_proceed = empty($found_folders) && !$found_in_wp;

if ($can_proceed) {
    echo "<div style='background: #2d6a4f; padding: 20px; border: 2px solid #51cf66; margin-top: 20px;'>";
    echo "<h2 class='success'>✓ READY FOR CLEAN INSTALL</h2>";
    echo "<p>The system is now clean. You can proceed with installation:</p>";
    echo "<ol>";
    echo "<li><strong>DELETE THIS FILE</strong> (nuclear-cleanup.php) NOW!</li>";
    echo "<li>Go to: <strong>WordPress Admin → Plugins → Add New → Upload Plugin</strong></li>";
    echo "<li>Upload: <strong>world-time-ai.zip</strong></li>";
    echo "<li>Install and Activate</li>";
    echo "</ol>";
    echo "<p><a href='/wp-admin/plugin-install.php?tab=upload' style='background: #51cf66; color: #000; padding: 10px 20px; text-decoration: none; font-weight: bold;'>→ Go to Plugin Upload</a></p>";
    echo "</div>";
} else {
    echo "<div style='background: #c92a2a; padding: 20px; border: 2px solid #ff6b6b; margin-top: 20px;'>";
    echo "<h2 class='error'>⚠ MANUAL ACTION REQUIRED</h2>";
    echo "<p>Follow Step 3 above, then refresh this page.</p>";
    echo "<p><button onclick='location.reload()' style='background: #ffd43b; color: #000; padding: 10px 20px; border: none; cursor: pointer; font-weight: bold;'>↻ Refresh Page</button></p>";
    echo "</div>";
}

echo "<hr>";
echo "<p class='error' style='font-size: 18px;'><strong>🔥 DELETE THIS FILE (nuclear-cleanup.php) WHEN DONE! 🔥</strong></p>";
?>


