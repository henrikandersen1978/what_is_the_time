<?php
/**
 * Check World Time AI Plugin Status
 * Upload to: /wp-content/plugins/check-plugin-status.php
 * Run: https://yoursite.com/wp-content/plugins/check-plugin-status.php
 */

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Plugin Status Check</title>";
echo "<style>
    body { font-family: monospace; max-width: 900px; margin: 50px auto; padding: 20px; background: #1e1e1e; color: #ddd; }
    h1 { color: #61afef; }
    .ok { color: #98c379; }
    .error { color: #e06c75; }
    .warning { color: #e5c07b; }
    pre { background: #282c34; padding: 15px; border: 1px solid #3e4451; overflow-x: auto; }
    .box { background: #282c34; padding: 15px; margin: 15px 0; border-left: 4px solid #61afef; }
</style></head><body>";

echo "<h1>🔍 World Time AI - Status Check</h1>";

$plugin_dir = __DIR__ . '/world-time-ai';
$main_file = $plugin_dir . '/world-time-ai.php';
$puc_file = $plugin_dir . '/includes/plugin-update-checker/plugin-update-checker.php';

// Check 1: Plugin directory
echo "<div class='box'>";
echo "<h2>1. Plugin Directory</h2>";
if (is_dir($plugin_dir)) {
    echo "<p class='ok'>✓ Directory exists: /wp-content/plugins/world-time-ai/</p>";
    
    // List contents
    echo "<p>Contents:</p><pre>";
    $items = scandir($plugin_dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $plugin_dir . '/' . $item;
        echo (is_dir($path) ? "📁" : "📄") . " $item\n";
    }
    echo "</pre>";
} else {
    echo "<p class='error'>✗ Directory NOT found!</p>";
    echo "</div></body></html>";
    exit;
}
echo "</div>";

// Check 2: Main plugin file
echo "<div class='box'>";
echo "<h2>2. Main Plugin File</h2>";
if (file_exists($main_file)) {
    echo "<p class='ok'>✓ world-time-ai.php exists</p>";
    
    $content = file_get_contents($main_file);
    
    // Extract version
    if (preg_match('/Version:\s*(.+)/', $content, $matches)) {
        $version = trim($matches[1]);
        echo "<p class='ok'>Version in header: <strong>$version</strong></p>";
    }
    
    // Extract WTA_VERSION constant
    if (preg_match('/define\(\s*[\'"]WTA_VERSION[\'"]\s*,\s*[\'"](.+?)[\'"]\s*\)/', $content, $matches)) {
        $const_version = trim($matches[1]);
        echo "<p class='ok'>WTA_VERSION constant: <strong>$const_version</strong></p>";
    }
    
    // Check for Plugin Update Checker initialization
    if (strpos($content, 'PluginUpdateChecker') !== false) {
        echo "<p class='ok'>✓ Plugin Update Checker code found in main file</p>";
        
        if (strpos($content, 'enableReleaseAssets') !== false) {
            echo "<p class='ok'>✓ Release assets enabled</p>";
        } else {
            echo "<p class='warning'>⚠ Release assets NOT enabled</p>";
        }
    } else {
        echo "<p class='error'>✗ NO Plugin Update Checker initialization found!</p>";
        echo "<p class='warning'>The main file doesn't have the update checker code.</p>";
    }
    
} else {
    echo "<p class='error'>✗ world-time-ai.php NOT found!</p>";
}
echo "</div>";

// Check 3: Plugin Update Checker library
echo "<div class='box'>";
echo "<h2>3. Plugin Update Checker Library</h2>";

$puc_dir = $plugin_dir . '/includes/plugin-update-checker';

if (is_dir($puc_dir)) {
    echo "<p class='ok'>✓ Directory exists: /includes/plugin-update-checker/</p>";
    
    if (file_exists($puc_file)) {
        echo "<p class='ok'>✓ plugin-update-checker.php exists</p>";
        
        // Count files in PUC directory
        $file_count = countFilesRecursive($puc_dir);
        echo "<p>Files in library: <strong>$file_count</strong></p>";
        
        if ($file_count > 50) {
            echo "<p class='ok'>✓ Library appears complete (50+ files)</p>";
        } else {
            echo "<p class='warning'>⚠ Library seems incomplete (only $file_count files)</p>";
        }
        
    } else {
        echo "<p class='error'>✗ plugin-update-checker.php NOT found!</p>";
    }
} else {
    echo "<p class='error'>✗ Plugin Update Checker directory NOT found!</p>";
    echo "<p class='warning'>The update system will NOT work without this.</p>";
}
echo "</div>";

// Check 4: includes directory structure
echo "<div class='box'>";
echo "<h2>4. Includes Directory Structure</h2>";

$includes_dir = $plugin_dir . '/includes';

if (is_dir($includes_dir)) {
    echo "<p class='ok'>✓ /includes/ directory exists</p>";
    echo "<p>Contents:</p><pre>";
    $items = scandir($includes_dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $includes_dir . '/' . $item;
        echo (is_dir($path) ? "📁" : "📄") . " $item\n";
    }
    echo "</pre>";
} else {
    echo "<p class='error'>✗ /includes/ directory NOT found!</p>";
}
echo "</div>";

// Summary
echo "<div class='box' style='border-left-color: " . (file_exists($puc_file) ? "#98c379" : "#e06c75") . ";'>";
echo "<h2>📊 Summary</h2>";

$has_main = file_exists($main_file);
$has_puc = file_exists($puc_file);
$has_code = $has_main && strpos(file_get_contents($main_file), 'PluginUpdateChecker') !== false;

if ($has_main && $has_puc && $has_code) {
    echo "<p class='ok'><strong>✓ EVERYTHING IS CORRECT!</strong></p>";
    echo "<p>Plugin Update Checker should work.</p>";
    echo "<p>If you don't see updates in WordPress:</p>";
    echo "<ul>";
    echo "<li>Make sure you created GitHub Release v0.1.1</li>";
    echo "<li>Make sure you uploaded world-time-ai.zip to the release</li>";
    echo "<li>Wait a few minutes for WordPress cache to clear</li>";
    echo "<li>Go to: Dashboard → Updates and click 'Check Again'</li>";
    echo "</ul>";
} else {
    echo "<p class='error'><strong>✗ PROBLEMS DETECTED:</strong></p>";
    echo "<ul>";
    if (!$has_main) echo "<li class='error'>Main plugin file missing</li>";
    if (!$has_puc) echo "<li class='error'>Plugin Update Checker library missing</li>";
    if ($has_main && !$has_code) echo "<li class='error'>Update checker code not in main file</li>";
    echo "</ul>";
    
    echo "<h3>FIX:</h3>";
    echo "<p>The plugin needs to be reinstalled with the correct version.</p>";
    echo "<p>The auto-install-from-github.php script downloads from 'main' branch,</p>";
    echo "<p>which might not have Plugin Update Checker yet.</p>";
}
echo "</div>";

echo "<hr>";
echo "<p style='color: #e5c07b;'>Delete this file after checking: /wp-content/plugins/check-plugin-status.php</p>";

echo "</body></html>";

function countFilesRecursive($dir) {
    $count = 0;
    if (!is_dir($dir)) return 0;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            $count += countFilesRecursive($path);
        } else {
            $count++;
        }
    }
    return $count;
}
?>


