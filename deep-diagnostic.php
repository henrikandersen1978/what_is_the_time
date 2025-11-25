<?php
/**
 * Deep Diagnostic - Find ALL references to world-time-ai
 * This will help us find why WordPress thinks the plugin exists
 */

require_once('wp-load.php');

if (!current_user_can('administrator')) {
    die('Admin only');
}

echo "<h2>Deep Diagnostic - World Time AI</h2>";
echo "<style>
    .good { color: green; font-weight: bold; }
    .bad { color: red; font-weight: bold; }
    pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
</style>";

// 1. Check all options in database
echo "<h3>1. Database Options Search</h3>";
global $wpdb;

$patterns = array('%world%time%', '%wta_%', '%what_is_the_time%');
foreach ($patterns as $pattern) {
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
        $pattern
    ));
    
    if ($results) {
        echo "<strong>Found " . count($results) . " entries matching '$pattern':</strong><br>";
        foreach ($results as $row) {
            echo "- {$row->option_name}<br>";
            if (strlen($row->option_value) < 200) {
                echo "<pre>" . esc_html($row->option_value) . "</pre>";
            }
        }
    }
}

// 2. Check active_plugins specifically
echo "<h3>2. Active Plugins</h3>";
$active = get_option('active_plugins', array());
echo "<pre>";
print_r($active);
echo "</pre>";

// 3. Check for plugin in file system
echo "<h3>3. File System Check</h3>";
$plugins_dir = WP_PLUGIN_DIR;
echo "Plugins directory: <code>$plugins_dir</code><br><br>";

$all_dirs = @scandir($plugins_dir);
$related = array();

foreach ($all_dirs as $dir) {
    if ($dir === '.' || $dir === '..') continue;
    
    if (stripos($dir, 'world') !== false || 
        stripos($dir, 'time') !== false || 
        stripos($dir, 'wta') !== false ||
        stripos($dir, 'what_is_the_time') !== false) {
        $related[] = $dir;
        $path = $plugins_dir . '/' . $dir;
        
        echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
        echo "<strong class='bad'>Found: $dir</strong><br>";
        echo "Full path: <code>$path</code><br>";
        
        if (is_dir($path)) {
            $files = @scandir($path);
            $file_count = count($files) - 2;
            echo "Type: Directory ($file_count items)<br>";
            
            // Check for main plugin file
            $possible_files = array(
                $dir . '.php',
                'world-time-ai.php',
                'what_is_the_time.php',
            );
            
            foreach ($possible_files as $pf) {
                if (file_exists($path . '/' . $pf)) {
                    echo "Main file: <code>$pf</code> <span class='bad'>EXISTS</span><br>";
                }
            }
        }
        echo "</div>";
    }
}

if (empty($related)) {
    echo "<span class='good'>✓ No related folders found</span><br>";
}

// 4. Check WordPress internal plugin cache
echo "<h3>4. WordPress Plugin Cache</h3>";
$all_plugins = get_plugins();
$found_in_wp = false;

foreach ($all_plugins as $plugin_path => $plugin_data) {
    if (stripos($plugin_path, 'world') !== false || 
        stripos($plugin_path, 'time') !== false ||
        stripos($plugin_path, 'wta') !== false) {
        echo "<div style='border: 1px solid orange; padding: 10px; margin: 10px 0;'>";
        echo "<strong>WordPress knows about:</strong> <code>$plugin_path</code><br>";
        echo "Name: {$plugin_data['Name']}<br>";
        echo "Version: {$plugin_data['Version']}<br>";
        echo "</div>";
        $found_in_wp = true;
    }
}

if (!$found_in_wp) {
    echo "<span class='good'>✓ No related plugins detected by WordPress</span><br>";
}

// 5. Check transients
echo "<h3>5. Transients</h3>";
$transients = $wpdb->get_results(
    "SELECT option_name FROM {$wpdb->options} 
     WHERE option_name LIKE '_transient_%' OR option_name LIKE '_site_transient_%'"
);

$plugin_transients = 0;
foreach ($transients as $t) {
    if (stripos($t->option_name, 'plugin') !== false) {
        $plugin_transients++;
    }
}

echo "Total transients: " . count($transients) . "<br>";
echo "Plugin-related transients: $plugin_transients<br>";

// 6. Suggest action
echo "<hr>";
echo "<h3>Recommended Actions:</h3>";

if (!empty($related)) {
    echo "<div style='background: #ffebee; padding: 15px; border-left: 4px solid #f44336;'>";
    echo "<strong>CRITICAL: Found " . count($related) . " related folder(s)!</strong><br><br>";
    echo "You MUST delete these folders via File Manager:<br>";
    foreach ($related as $folder) {
        echo "→ <code>/wp-content/plugins/$folder/</code><br>";
    }
    echo "<br><strong>After deleting, refresh this page to verify.</strong>";
    echo "</div>";
} else {
    echo "<div style='background: #e8f5e9; padding: 15px; border-left: 4px solid #4caf50;'>";
    echo "<strong>✓ File system is clean!</strong><br><br>";
    echo "The problem might be:<br>";
    echo "1. WordPress object cache (Memcached/Redis) - try restarting it<br>";
    echo "2. Server-level opcode cache - try restarting PHP-FPM<br>";
    echo "3. CDN/Proxy cache - clear CloudFlare or similar<br>";
    echo "</div>";
}

echo "<br><p><a href='" . admin_url('plugins.php') . "' class='button button-primary'>Go to Plugins Page</a></p>";
?>


