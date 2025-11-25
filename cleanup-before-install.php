<?php
/**
 * Run this BEFORE uploading plugin via WordPress
 * Just visit this page in browser, then upload plugin normally
 */

require_once( 'wp-load.php' );

if ( ! current_user_can( 'manage_options' ) ) {
    die( 'Must be admin' );
}

echo '<h1>Cleanup World Time AI</h1>';
echo '<style>body{font-family:Arial;padding:40px;max-width:800px;margin:0 auto;}h2{color:#0073aa;}</style>';

// 1. Deactivate
$active = get_option( 'active_plugins', array() );
$active = array_diff( $active, array( 'world-time-ai/world-time-ai.php' ) );
update_option( 'active_plugins', $active );
echo '<h2>✅ Deactivated</h2>';

// 2. Delete directories
function delTree($dir) {
    if (!is_dir($dir)) return;
    $files = array_diff(scandir($dir), array('.','..'));
    foreach ($files as $file) {
        $path = "$dir/$file";
        is_dir($path) ? delTree($path) : unlink($path);
    }
    rmdir($dir);
}

$plugins_dir = WP_PLUGIN_DIR;
foreach (['world-time-ai', 'world-time-ai-2', 'world-time-ai-3', 'temp-wta-install'] as $dir) {
    $path = $plugins_dir . '/' . $dir;
    if (is_dir($path)) {
        delTree($path);
        echo "<h2>✅ Deleted: $dir</h2>";
    }
}

// 3. Clean database
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%world_time%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%wta_%'");
echo '<h2>✅ Database cleaned</h2>';

// 4. Delete temp files
$temp_zip = ABSPATH . 'temp-world-time-ai.zip';
if (file_exists($temp_zip)) {
    unlink($temp_zip);
    echo '<h2>✅ Temp ZIP deleted</h2>';
}

// 5. Clear cache
wp_cache_flush();
if (function_exists('litespeed_purge_all')) litespeed_purge_all();
echo '<h2>✅ Cache cleared</h2>';

// 6. Instructions
echo '<hr>';
echo '<h2>🎉 Ready for Installation!</h2>';
echo '<h3>NOW do this:</h3>';
echo '<ol style="font-size:18px;line-height:2;">';
echo '<li>Go to <strong>Plugins → Add New → Upload Plugin</strong></li>';
echo '<li>Upload <strong>world-time-ai.zip</strong></li>';
echo '<li>Click Install & Activate</li>';
echo '<li>It should work now!</li>';
echo '</ol>';
echo '<p><a href="' . admin_url('plugin-install.php') . '" style="background:#0073aa;color:white;padding:15px 30px;text-decoration:none;border-radius:5px;font-size:18px;">Go to Plugin Upload →</a></p>';
echo '<hr>';
echo '<p style="color:red;">DELETE cleanup-before-install.php after installation!</p>';

