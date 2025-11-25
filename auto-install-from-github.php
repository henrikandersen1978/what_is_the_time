<?php
/**
 * Auto-Install World Time AI from GitHub
 * This downloads the plugin directly from GitHub and installs it
 * 
 * INSTRUCTIONS:
 * 1. Create new file in: /wp-content/plugins/auto-install-from-github.php
 * 2. Paste this entire code
 * 3. Run: https://yoursite.com/wp-content/plugins/auto-install-from-github.php
 * 4. Delete this file when done!
 */

set_time_limit(300); // 5 minutes timeout

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Auto Install from GitHub</title>";
echo "<style>
    body { font-family: -apple-system, sans-serif; max-width: 900px; margin: 50px auto; padding: 20px; background: #f0f0f0; }
    .step { background: white; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
    .success { color: #46b450; font-weight: bold; }
    .error { color: #dc3232; font-weight: bold; }
    .warning { color: #f0b849; font-weight: bold; }
    .info { color: #0073aa; }
    pre { background: #f5f5f5; padding: 10px; overflow-x: auto; border: 1px solid #ddd; font-size: 12px; }
    .button { background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; display: inline-block; margin: 10px 5px; }
    .progress { background: #e0e0e0; height: 20px; border-radius: 10px; overflow: hidden; margin: 10px 0; }
    .progress-bar { background: #0073aa; height: 100%; transition: width 0.3s; }
</style></head><body>";

echo "<h1>🚀 Auto-Install World Time AI from GitHub</h1>";

$plugins_dir = __DIR__;
$target_dir = $plugins_dir . '/world-time-ai';
$temp_zip = $plugins_dir . '/temp-world-time-ai.zip';

// GitHub repository details
$github_repo = 'henrikandersen1978/what_is_the_time';
$github_tag = 'v0.3.1'; // Fixed: Now uses correct asset from releases
$download_url = "https://github.com/$github_repo/archive/refs/tags/$github_tag.zip";

echo "<p class='info'>📦 Repository: <code>$github_repo</code></p>";
echo "<p class='info'>📥 Download URL: <code>$download_url</code></p>";

// Load WordPress if available (to access database for settings backup)
$wp_load_path = dirname(dirname(__DIR__)) . '/wp-load.php';
$wp_available = false;
if (file_exists($wp_load_path)) {
    require_once($wp_load_path);
    $wp_available = true;
}

// Step 0: Backup existing settings
$backup_settings = array();
if ($wp_available) {
    echo "<div class='step'>";
    echo "<h2>Step 0: Backup Existing Settings</h2>";
    
    // List of all plugin options to backup
    $option_keys = array(
        // GitHub URLs
        'wta_github_countries_url',
        'wta_github_states_url',
        'wta_github_cities_url',
        // TimeZoneDB
        'wta_timezonedb_api_key',
        'wta_complex_countries',
        // Base settings
        'wta_base_country_name',
        'wta_base_timezone',
        'wta_base_language',
        'wta_base_language_description',
        // OpenAI
        'wta_openai_api_key',
        'wta_openai_model',
        'wta_openai_temperature',
        'wta_openai_max_tokens',
        // Import filters
        'wta_selected_continents',
        'wta_min_population',
        'wta_max_cities_per_country',
        // Yoast
        'wta_yoast_integration_enabled',
        'wta_yoast_allow_overwrite',
        // DB version
        'wta_db_version',
    );
    
    // Add prompt options (9 prompts × 2 types)
    $prompt_ids = array(
        'translate_location_name',
        'city_page_title',
        'city_page_content',
        'country_page_title',
        'country_page_content',
        'continent_page_title',
        'continent_page_content',
        'yoast_seo_title',
        'yoast_meta_description',
    );
    
    foreach ($prompt_ids as $prompt_id) {
        $option_keys[] = "wta_prompt_{$prompt_id}_system";
        $option_keys[] = "wta_prompt_{$prompt_id}_user";
    }
    
    // Backup all existing values
    $backed_up = 0;
    foreach ($option_keys as $key) {
        $value = get_option($key, null);
        if ($value !== null && $value !== false && $value !== '') {
            $backup_settings[$key] = $value;
            $backed_up++;
        }
    }
    
    if ($backed_up > 0) {
        echo "<p class='success'>✓ Backed up {$backed_up} settings</p>";
        echo "<p class='info'>Your API keys and configurations will be preserved!</p>";
    } else {
        echo "<p class='info'>No existing settings found (fresh install)</p>";
    }
    
    echo "</div>";
}

// Step 1: Remove old installations
echo "<div class='step'>";
echo "<h2>Step 1: Clean Old Installations</h2>";

$patterns = ['world-time-ai', 'world-time-ai-1', 'world-time-ai-2', 'world-time-ai-3', 'what_is_the_time-main'];
$removed = [];

foreach ($patterns as $pattern) {
    $dir_path = $plugins_dir . '/' . $pattern;
    if (is_dir($dir_path)) {
        $success = removeDirectory($dir_path);
        if ($success) {
            $removed[] = $pattern;
            echo "<p class='success'>✓ Removed: $pattern/</p>";
        } else {
            echo "<p class='warning'>⚠ Could not fully remove: $pattern/</p>";
        }
    }
}

// Remove temp files
@unlink($temp_zip);

if (empty($removed)) {
    echo "<p class='success'>✓ No old installations found</p>";
}

echo "</div>";

// Step 2: Download from GitHub
echo "<div class='step'>";
echo "<h2>Step 2: Download from GitHub</h2>";
echo "<p>Downloading... (this may take a moment)</p>";
echo "<div class='progress'><div class='progress-bar' style='width: 30%;'></div></div>";

flush();
ob_flush();

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $download_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
curl_setopt($ch, CURLOPT_TIMEOUT, 300);

$zip_content = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($http_code == 200 && $zip_content) {
    $size = strlen($zip_content);
    file_put_contents($temp_zip, $zip_content);
    echo "<p class='success'>✓ Downloaded: " . number_format($size / 1024, 2) . " KB</p>";
} else {
    echo "<p class='error'>✗ Download failed!</p>";
    echo "<p>HTTP Code: $http_code</p>";
    if ($curl_error) {
        echo "<p>Error: $curl_error</p>";
    }
    echo "<p class='warning'>Trying alternative method (file_get_contents)...</p>";
    
    // Fallback to file_get_contents
    $zip_content = @file_get_contents($download_url);
    if ($zip_content) {
        $size = strlen($zip_content);
        file_put_contents($temp_zip, $zip_content);
        echo "<p class='success'>✓ Downloaded with fallback method: " . number_format($size / 1024, 2) . " KB</p>";
    } else {
        echo "<p class='error'>✗ Both download methods failed!</p>";
        echo "<p>Your server might not allow external downloads. Contact your hosting provider.</p>";
        echo "</div></body></html>";
        exit;
    }
}

echo "</div>";

// Step 3: Extract ZIP
echo "<div class='step'>";
echo "<h2>Step 3: Extract Plugin Files</h2>";
echo "<div class='progress'><div class='progress-bar' style='width: 60%;'></div></div>";

if (!class_exists('ZipArchive')) {
    echo "<p class='error'>✗ ZipArchive not available on this server</p>";
    echo "<p>Contact your hosting provider to enable ZIP support.</p>";
    @unlink($temp_zip);
    echo "</div></body></html>";
    exit;
}

$zip = new ZipArchive;
$result = $zip->open($temp_zip);

if ($result === TRUE) {
    echo "<p>Extracting files...</p>";
    
    // Extract to temporary location
    $temp_extract = $plugins_dir . '/temp-extract';
    @mkdir($temp_extract, 0755, true);
    
    $zip->extractTo($temp_extract);
    $zip->close();
    
    echo "<p class='success'>✓ ZIP extracted</p>";
    
    // Find the actual plugin folder (GitHub adds repo name as folder)
    $extracted_items = scandir($temp_extract);
    $plugin_source = null;
    
    foreach ($extracted_items as $item) {
        if ($item != '.' && $item != '..' && is_dir($temp_extract . '/' . $item)) {
            $plugin_source = $temp_extract . '/' . $item;
            echo "<p>Found extracted folder: <code>$item</code></p>";
            break;
        }
    }
    
    if (!$plugin_source) {
        echo "<p class='error'>✗ Could not find plugin files in extracted ZIP</p>";
        removeDirectory($temp_extract);
        @unlink($temp_zip);
        echo "</div></body></html>";
        exit;
    }
    
    // Move to correct location
    if (is_dir($target_dir)) {
        removeDirectory($target_dir);
    }
    
    $move_success = rename($plugin_source, $target_dir);
    
    if ($move_success) {
        echo "<p class='success'>✓ Plugin moved to: /wp-content/plugins/world-time-ai/</p>";
    } else {
        echo "<p class='error'>✗ Failed to move plugin to final location</p>";
        echo "<p>Trying to copy instead...</p>";
        
        // Try copying instead
        $copy_success = copyDirectory($plugin_source, $target_dir);
        if ($copy_success) {
            echo "<p class='success'>✓ Plugin copied successfully</p>";
        } else {
            echo "<p class='error'>✗ Copy also failed</p>";
        }
    }
    
    // Cleanup temp files
    removeDirectory($temp_extract);
    @unlink($temp_zip);
    
} else {
    echo "<p class='error'>✗ Failed to open ZIP file</p>";
    echo "<p>Error code: $result</p>";
    @unlink($temp_zip);
    echo "</div></body></html>";
    exit;
}

echo "</div>";

// Step 4: Verify Installation
echo "<div class='step'>";
echo "<h2>Step 4: Verify Installation</h2>";
echo "<div class='progress'><div class='progress-bar' style='width: 100%;'></div></div>";

$main_file = $target_dir . '/world-time-ai.php';

if (is_dir($target_dir)) {
    echo "<p class='success'>✓ Directory exists: /wp-content/plugins/world-time-ai/</p>";
    
    if (file_exists($main_file)) {
        echo "<p class='success'>✓ Main plugin file exists: world-time-ai.php</p>";
        
        // Read version
        $content = file_get_contents($main_file);
        if (preg_match('/Version:\s*(.+)/', $content, $matches)) {
            $version = trim($matches[1]);
            echo "<p>📌 Version: <strong>$version</strong></p>";
        }
        
        // Check for Plugin Update Checker
        $puc_file = $target_dir . '/includes/plugin-update-checker/plugin-update-checker.php';
        if (file_exists($puc_file)) {
            echo "<p class='success'>✓ Plugin Update Checker included</p>";
        } else {
            echo "<p class='warning'>⚠ Plugin Update Checker not found</p>";
        }
        
        // Count files
        $file_count = countFiles($target_dir);
        echo "<p>📊 Total files: <strong>$file_count</strong></p>";
        
        echo "<hr>";
        echo "<h3 style='color: #46b450;'>✅ INSTALLATION SUCCESSFUL!</h3>";
        echo "<p>The plugin is now installed in the correct location.</p>";
        
        echo "<h3>Next Steps:</h3>";
        echo "<ol>";
        echo "<li><strong>Delete this file</strong> (auto-install-from-github.php) for security</li>";
        echo "<li><a href='/wp-admin/plugins.php' class='button'>Go to Plugins Page</a></li>";
        echo "<li>Find <strong>World Time AI</strong> in the list</li>";
        echo "<li>Click <strong>Activate</strong></li>";
        echo "</ol>";
        
    } else {
        echo "<p class='error'>✗ Main plugin file NOT found!</p>";
        echo "<p>Expected: <code>$main_file</code></p>";
        listDirectory($target_dir);
    }
} else {
    echo "<p class='error'>✗ Plugin directory was not created!</p>";
    echo "<p>Contents of plugins directory:</p>";
    listDirectory($plugins_dir);
}

echo "</div>";

// Cleanup warning
echo "<div class='step' style='background: #fff3cd; border-left-color: #f0b849;'>";
echo "<h3>⚠️ IMPORTANT - Delete This File!</h3>";
echo "<p>For security, delete this file immediately:</p>";
echo "<p><code>/wp-content/plugins/auto-install-from-github.php</code></p>";
echo "</div>";

echo "</body></html>";

// ===== Helper Functions =====

function removeDirectory($dir) {
    if (!is_dir($dir)) return false;
    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? removeDirectory($path) : @unlink($path);
    }
    return @rmdir($dir);
}

function copyDirectory($src, $dst) {
    if (!is_dir($src)) return false;
    @mkdir($dst, 0755, true);
    $files = array_diff(scandir($src), ['.', '..']);
    foreach ($files as $file) {
        $srcPath = $src . '/' . $file;
        $dstPath = $dst . '/' . $file;
        if (is_dir($srcPath)) {
            copyDirectory($srcPath, $dstPath);
        } else {
            @copy($srcPath, $dstPath);
        }
    }
    return true;
}

function listDirectory($dir) {
    echo "<pre>";
    if (is_dir($dir)) {
        $items = array_diff(scandir($dir), ['.', '..']);
        $count = 0;
        foreach ($items as $item) {
            if ($count++ > 20) {
                echo "... (showing first 20 items)\n";
                break;
            }
            $path = $dir . '/' . $item;
            echo (is_dir($path) ? "📁" : "📄") . " $item\n";
        }
    } else {
        echo "Directory not found\n";
    }
    echo "</pre>";
}

function countFiles($dir) {
    $count = 0;
    if (!is_dir($dir)) return 0;
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            $count += countFiles($path);
        } else {
            $count++;
        }
    }
    return $count;
}
?>

