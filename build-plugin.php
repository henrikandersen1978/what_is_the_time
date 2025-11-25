#!/usr/bin/env php
<?php
/**
 * Build script for World Time AI plugin.
 * 
 * This script creates a deployable ZIP file with all dependencies.
 * 
 * Usage: php build-plugin.php
 */

echo "🚀 World Time AI - Build Script\n";
echo "================================\n\n";

// Check if we're in the right directory
if (!file_exists('world-time-ai.php')) {
    die("❌ Error: Run this script from the plugin root directory\n");
}

// Check for external libraries
echo "📦 Checking external libraries...\n";

$action_scheduler = 'includes/action-scheduler/action-scheduler.php';
$plugin_updater = 'includes/plugin-update-checker/plugin-update-checker.php';

if (!file_exists($action_scheduler)) {
    echo "❌ Action Scheduler not found!\n";
    echo "   Run: cd includes && git clone https://github.com/woocommerce/action-scheduler.git\n";
    exit(1);
}

if (!file_exists($plugin_updater)) {
    echo "❌ Plugin Update Checker not found!\n";
    echo "   Run: cd includes && git clone https://github.com/YahnisElsts/plugin-update-checker.git\n";
    exit(1);
}

echo "✅ All external libraries found\n\n";

// Create build directory
echo "📁 Creating build directory...\n";
$build_dir = 'build/world-time-ai';

if (file_exists('build')) {
    echo "   Cleaning old build directory...\n";
    system('rm -rf build');
}

mkdir('build', 0755, true);
mkdir($build_dir, 0755, true);

// Files and directories to copy
$items_to_copy = [
    'world-time-ai.php',
    'uninstall.php',
    'README.md',
    'includes/',
    'languages/',
];

echo "📋 Copying files...\n";
foreach ($items_to_copy as $item) {
    if (!file_exists($item)) {
        echo "   ⚠️  Skipping missing: $item\n";
        continue;
    }
    
    $target = $build_dir . '/' . $item;
    
    if (is_dir($item)) {
        echo "   Copying directory: $item\n";
        system("cp -r " . escapeshellarg($item) . " " . escapeshellarg($target));
    } else {
        echo "   Copying file: $item\n";
        copy($item, $target);
    }
}

// Create ZIP
echo "\n📦 Creating ZIP file...\n";
chdir('build');

$zip_file = 'world-time-ai.zip';
if (file_exists($zip_file)) {
    unlink($zip_file);
}

system('zip -r world-time-ai.zip world-time-ai/ -q');

if (!file_exists($zip_file)) {
    echo "❌ Failed to create ZIP file\n";
    exit(1);
}

$zip_size = filesize($zip_file);
$zip_size_mb = round($zip_size / 1024 / 1024, 2);

echo "✅ ZIP created: build/world-time-ai.zip ({$zip_size_mb} MB)\n\n";

// Read version from plugin file
chdir('..');
$plugin_content = file_get_contents('world-time-ai.php');
preg_match('/Version:\s*([0-9.]+)/', $plugin_content, $matches);
$version = $matches[1] ?? 'unknown';

echo "🎉 Build complete!\n";
echo "================================\n";
echo "Version: {$version}\n";
echo "ZIP file: build/world-time-ai.zip\n";
echo "Size: {$zip_size_mb} MB\n\n";

echo "📋 Next steps:\n";
echo "1. Test the ZIP file locally\n";
echo "2. Commit and tag: git tag -a v{$version} -m \"Version {$version}\"\n";
echo "3. Push: git push origin main && git push origin v{$version}\n";
echo "4. Create GitHub release at: https://github.com/henrikandersen1978/what_is_the_time/releases/new\n";
echo "5. Upload build/world-time-ai.zip to the release\n\n";

echo "✨ Ready for release!\n";

