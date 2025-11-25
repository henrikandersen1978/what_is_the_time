<?php
/**
 * Force Manual Installation Helper
 * This script helps you install World Time AI by bypassing WordPress's plugin installer
 * 
 * INSTRUCTIONS:
 * 1. Upload THIS file to: /wp-content/plugins/
 * 2. Upload world-time-ai.zip to: /wp-content/plugins/
 * 3. Run: https://yoursite.com/wp-content/plugins/force-manual-install.php
 * 4. Delete this file and the zip when done!
 */

// No WordPress load needed - we work directly with filesystem

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Force Manual Install</title>";
echo "<style>
    body { font-family: -apple-system, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f0f0f0; }
    .step { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .success { color: #46b450; font-weight: bold; }
    .error { color: #dc3232; font-weight: bold; }
    .warning { color: #f0b849; font-weight: bold; }
    pre { background: #f5f5f5; padding: 10px; overflow-x: auto; border: 1px solid #ddd; }
    .button { background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-block; margin: 10px 5px; }
    .button:hover { background: #005a87; }
</style></head><body>";

echo "<h1>🔧 Force Manual Installation - World Time AI</h1>";

$plugins_dir = __DIR__;
$zip_file = $plugins_dir . '/world-time-ai.zip';
$target_dir = $plugins_dir . '/world-time-ai';

// Step 1: Check if zip file exists
echo "<div class='step'>";
echo "<h2>Step 1: Locate ZIP File</h2>";

if (file_exists($zip_file)) {
    echo "<p class='success'>✓ Found: world-time-ai.zip</p>";
    $size = filesize($zip_file);
    echo "<p>Size: " . number_format($size / 1024, 2) . " KB</p>";
} else {
    echo "<p class='error'>✗ world-time-ai.zip NOT FOUND!</p>";
    echo "<p>Please upload world-time-ai.zip to: <code>/wp-content/plugins/</code></p>";
    echo "<p>Current directory: <code>$plugins_dir</code></p>";
    echo "</div></body></html>";
    exit;
}
echo "</div>";

// Step 2: Remove old installations
echo "<div class='step'>";
echo "<h2>Step 2: Remove Old Installations</h2>";

$patterns = ['world-time-ai', 'world-time-ai-1', 'world-time-ai-2', 'world-time-ai-3'];
$removed = [];

foreach ($patterns as $pattern) {
    $dir_path = $plugins_dir . '/' . $pattern;
    if (is_dir($dir_path)) {
        // Try to remove recursively
        $success = removeDirectory($dir_path);
        if ($success) {
            $removed[] = $pattern;
            echo "<p class='success'>✓ Removed: $pattern/</p>";
        } else {
            echo "<p class='warning'>⚠ Could not remove: $pattern/ (might need manual deletion)</p>";
        }
    }
}

if (empty($removed)) {
    echo "<p class='success'>✓ No old installations found</p>";
}

echo "</div>";

// Step 3: Extract ZIP
echo "<div class='step'>";
echo "<h2>Step 3: Extract Plugin</h2>";

if (!class_exists('ZipArchive')) {
    echo "<p class='error'>✗ ZipArchive class not available. Server doesn't support ZIP extraction.</p>";
    echo "<p>You must extract manually via File Manager or FTP.</p>";
    echo "</div></body></html>";
    exit;
}

$zip = new ZipArchive;
$result = $zip->open($zip_file);

if ($result === TRUE) {
    echo "<p>Opening ZIP file...</p>";
    
    // Extract to plugins directory
    $extract_result = $zip->extractTo($plugins_dir);
    $zip->close();
    
    if ($extract_result) {
        echo "<p class='success'>✓ ZIP extracted successfully!</p>";
    } else {
        echo "<p class='error'>✗ Failed to extract ZIP</p>";
        echo "</div></body></html>";
        exit;
    }
} else {
    echo "<p class='error'>✗ Failed to open ZIP file (Error code: $result)</p>";
    echo "</div></body></html>";
    exit;
}

echo "</div>";

// Step 4: Verify installation
echo "<div class='step'>";
echo "<h2>Step 4: Verify Installation</h2>";

if (is_dir($target_dir)) {
    echo "<p class='success'>✓ Directory exists: /wp-content/plugins/world-time-ai/</p>";
    
    $main_file = $target_dir . '/world-time-ai.php';
    if (file_exists($main_file)) {
        echo "<p class='success'>✓ Main file exists: world-time-ai.php</p>";
        
        // Check for plugin update checker
        $puc_file = $target_dir . '/includes/plugin-update-checker/plugin-update-checker.php';
        if (file_exists($puc_file)) {
            echo "<p class='success'>✓ Plugin Update Checker found</p>";
        } else {
            echo "<p class='warning'>⚠ Plugin Update Checker not found (updates might not work)</p>";
        }
        
        // Read plugin version
        $content = file_get_contents($main_file);
        if (preg_match('/Version:\s*(.+)/', $content, $matches)) {
            $version = trim($matches[1]);
            echo "<p>Version: <strong>$version</strong></p>";
        }
        
        echo "<hr>";
        echo "<h3 class='success'>✓ INSTALLATION SUCCESSFUL!</h3>";
        echo "<p>The plugin files are now in place.</p>";
        
        // Check if we can load wp-load to activate
        $wp_load = dirname($plugins_dir) . '/../wp-load.php';
        if (file_exists($wp_load)) {
            echo "<p><a href='/wp-admin/plugins.php' class='button'>→ Go to Plugins Page to Activate</a></p>";
        }
        
    } else {
        echo "<p class='error'>✗ Main plugin file NOT found!</p>";
        echo "<p>Expected: <code>$main_file</code></p>";
        listDirectory($target_dir);
    }
} else {
    echo "<p class='error'>✗ Plugin directory NOT created!</p>";
    echo "<p>Expected: <code>$target_dir</code></p>";
    echo "<p>Contents of plugins directory:</p>";
    listDirectory($plugins_dir);
}

echo "</div>";

// Step 5: Cleanup
echo "<div class='step'>";
echo "<h2>Step 5: Cleanup</h2>";
echo "<p class='warning'>⚠ IMPORTANT: Delete these files for security:</p>";
echo "<ul>";
echo "<li><code>/wp-content/plugins/world-time-ai.zip</code></li>";
echo "<li><code>/wp-content/plugins/force-manual-install.php</code> (this file)</li>";
echo "<li>Any other cleanup scripts you uploaded</li>";
echo "</ul>";

if (isset($_GET['cleanup']) && $_GET['cleanup'] == 'yes') {
    @unlink($zip_file);
    echo "<p class='success'>✓ Deleted world-time-ai.zip</p>";
    echo "<p class='warning'>Now delete this file (force-manual-install.php) manually!</p>";
} else {
    echo "<p><a href='?cleanup=yes' class='button'>Delete ZIP File Now</a></p>";
}

echo "</div>";

echo "</body></html>";

// Helper function to remove directory recursively
function removeDirectory($dir) {
    if (!is_dir($dir)) {
        return false;
    }
    
    $files = array_diff(scandir($dir), ['.', '..']);
    
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            removeDirectory($path);
        } else {
            @unlink($path);
        }
    }
    
    return @rmdir($dir);
}

// Helper function to list directory contents
function listDirectory($dir) {
    echo "<pre>";
    if (is_dir($dir)) {
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                echo "📁 $item/\n";
            } else {
                echo "📄 $item\n";
            }
        }
    } else {
        echo "Directory not found: $dir\n";
    }
    echo "</pre>";
}
?>


