# World Time AI - Build Script for Windows
# Usage: .\build.ps1

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  World Time AI - Build Script v2.0.0" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# Check if we're in the right directory
if (-not (Test-Path "time-zone-clock.php")) {
    Write-Host "[ERROR] Run this script from the plugin root directory" -ForegroundColor Red
    exit 1
}

# Check for external libraries
Write-Host "[1/5] Checking external libraries..." -ForegroundColor Yellow

$actionScheduler = "includes\action-scheduler\action-scheduler.php"
$pluginUpdater = "includes\plugin-update-checker\plugin-update-checker.php"
$allLibsOk = $true

if (-not (Test-Path $actionScheduler)) {
    Write-Host "  [MISSING] Action Scheduler not found" -ForegroundColor Red
    Write-Host "  Required: Plugin will not work without it" -ForegroundColor Yellow
    Write-Host "  Run: cd includes; git clone https://github.com/woocommerce/action-scheduler.git" -ForegroundColor Cyan
    $allLibsOk = $false
}

if (-not (Test-Path $pluginUpdater)) {
    Write-Host "  [MISSING] Plugin Update Checker not found" -ForegroundColor Red
    Write-Host "  Optional: Automatic updates will not work without it" -ForegroundColor Yellow
    Write-Host "  Run: cd includes; git clone https://github.com/YahnisElsts/plugin-update-checker.git" -ForegroundColor Cyan
    $allLibsOk = $false
}

if (-not $allLibsOk) {
    Write-Host ""
    Write-Host "[ERROR] Missing required libraries. Install them first!" -ForegroundColor Red
    exit 1
}

Write-Host "  [OK] All external libraries found" -ForegroundColor Green
Write-Host ""

# Create build directory
Write-Host "[2/5] Creating build directory..." -ForegroundColor Yellow

$buildDir = "build\time-zone-clock"

if (Test-Path "build") {
    Remove-Item -Recurse -Force "build" | Out-Null
}

New-Item -ItemType Directory -Path "build" -Force | Out-Null
New-Item -ItemType Directory -Path $buildDir -Force | Out-Null

Write-Host "  [OK] Build directory created" -ForegroundColor Green
Write-Host ""

# Files and directories to copy
Write-Host "[3/5] Copying plugin files..." -ForegroundColor Yellow

$itemsToCopy = @(
    "time-zone-clock.php",
    "uninstall.php",
    "README.md",
    "SETUP-INSTRUCTIONS.md",
    "includes",
    "languages"
)

foreach ($item in $itemsToCopy) {
    if (-not (Test-Path $item)) {
        Write-Host "  [SKIP] $item (not found)" -ForegroundColor Gray
        continue
    }
    
    $target = Join-Path $buildDir $item
    
    if (Test-Path $item -PathType Container) {
        Copy-Item -Path $item -Destination $target -Recurse -Force | Out-Null
        Write-Host "  [OK] $item (directory)" -ForegroundColor Gray
    }
    else {
        Copy-Item -Path $item -Destination $target -Force | Out-Null
        Write-Host "  [OK] $item" -ForegroundColor Gray
    }
}

Write-Host "  [OK] All files copied" -ForegroundColor Green
Write-Host ""

# Remove plugin headers from bundled libraries so WordPress doesn't detect them
Write-Host "[3.5/5] Patching bundled libraries..." -ForegroundColor Yellow

$actionSchedulerFile = "$buildDir\includes\action-scheduler\action-scheduler.php"
if (Test-Path $actionSchedulerFile) {
    $content = Get-Content $actionSchedulerFile -Raw
    
    # Simply comment out "Plugin Name:" so WordPress doesn't detect it
    $content = $content -replace '\* Plugin Name:', '* Library Name:'
    
    Set-Content -Path $actionSchedulerFile -Value $content -NoNewline
    Write-Host "  [OK] Action Scheduler plugin header neutralized" -ForegroundColor Gray
}

$pucFile = "$buildDir\includes\plugin-update-checker\plugin-update-checker.php"
if (Test-Path $pucFile) {
    $content = Get-Content $pucFile -Raw
    
    # Remove any plugin header if it exists
    if ($content -match '/\*\*[^/]*\* Plugin Name:') {
        $content = $content -replace '(?s)/\*\*[^/]*\* Plugin Name:[^/]*\*/', '/**
 * Plugin Update Checker Library (Bundled)
 * Part of World Time AI - Not a standalone plugin
 */'
        
        Set-Content -Path $pucFile -Value $content -NoNewline
        Write-Host "  [OK] Plugin Update Checker header removed" -ForegroundColor Gray
    }
}

Write-Host "  [OK] Libraries patched" -ForegroundColor Green
Write-Host ""

# Create ZIP
Write-Host "[4/5] Creating ZIP file..." -ForegroundColor Yellow

$zipPath = "build\time-zone-clock.zip"
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Add-Type -Assembly System.IO.Compression.FileSystem
$compressionLevel = [System.IO.Compression.CompressionLevel]::Optimal
[System.IO.Compression.ZipFile]::CreateFromDirectory(
    (Resolve-Path $buildDir).Path,
    (Join-Path (Get-Location) $zipPath),
    $compressionLevel,
    $true
)

if (-not (Test-Path $zipPath)) {
    Write-Host "  [ERROR] Failed to create ZIP file" -ForegroundColor Red
    exit 1
}

$zipSize = (Get-Item $zipPath).Length
$zipSizeMB = [math]::Round($zipSize / 1MB, 2)

Write-Host "  [OK] ZIP created ($zipSizeMB MB)" -ForegroundColor Green
Write-Host ""

# Read version from plugin file
$pluginContent = Get-Content "time-zone-clock.php" -Raw
if ($pluginContent -match 'Version:\s*([0-9.]+)') {
    $version = $matches[1]
}
else {
    $version = "unknown"
}

# Final summary
Write-Host "[5/5] Build complete!" -ForegroundColor Green
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  BUILD SUMMARY" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Version   : $version" -ForegroundColor White
Write-Host "ZIP file  : build\time-zone-clock.zip" -ForegroundColor White
Write-Host "Size      : $zipSizeMB MB" -ForegroundColor White
Write-Host ""

Write-Host "NEXT STEPS:" -ForegroundColor Yellow
Write-Host "1. Test ZIP locally in WordPress" -ForegroundColor White
Write-Host "2. git add ." -ForegroundColor White
Write-Host "3. git commit -m 'Build v$version'" -ForegroundColor White
Write-Host "4. git tag -a v$version -m 'Version $version'" -ForegroundColor White
Write-Host "5. git push origin main" -ForegroundColor White
Write-Host "6. git push origin v$version" -ForegroundColor White
Write-Host "7. Create GitHub release" -ForegroundColor White
Write-Host "8. Upload build\time-zone-clock.zip" -ForegroundColor White
Write-Host ""
Write-Host "GitHub Releases:" -ForegroundColor Cyan
Write-Host "https://github.com/henrikandersen1978/what_is_the_time/releases/new" -ForegroundColor Blue
Write-Host ""

