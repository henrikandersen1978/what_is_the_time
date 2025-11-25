# World Time AI - Build Script for Windows
# Usage: .\build-plugin.ps1

Write-Host "🚀 World Time AI - Build Script" -ForegroundColor Cyan
Write-Host "================================`n" -ForegroundColor Cyan

# Check if we're in the right directory
if (-not (Test-Path "world-time-ai.php")) {
    Write-Host "❌ Error: Run this script from the plugin root directory" -ForegroundColor Red
    exit 1
}

# Check for external libraries
Write-Host "📦 Checking external libraries..." -ForegroundColor Yellow

$actionScheduler = "includes\action-scheduler\action-scheduler.php"
$pluginUpdater = "includes\plugin-update-checker\plugin-update-checker.php"

if (-not (Test-Path $actionScheduler)) {
    Write-Host "❌ Action Scheduler not found!" -ForegroundColor Red
    Write-Host "   Run: cd includes; git clone https://github.com/woocommerce/action-scheduler.git" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Skal jeg downloade det nu? (y/n)" -ForegroundColor Yellow
    $response = Read-Host
    if ($response -eq 'y') {
        Push-Location includes
        git clone https://github.com/woocommerce/action-scheduler.git
        Pop-Location
        Write-Host "✅ Action Scheduler downloaded" -ForegroundColor Green
    } else {
        exit 1
    }
}

if (-not (Test-Path $pluginUpdater)) {
    Write-Host "❌ Plugin Update Checker not found!" -ForegroundColor Red
    Write-Host "   Run: cd includes; git clone https://github.com/YahnisElsts/plugin-update-checker.git" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Skal jeg downloade det nu? (y/n)" -ForegroundColor Yellow
    $response = Read-Host
    if ($response -eq 'y') {
        Push-Location includes
        git clone https://github.com/YahnisElsts/plugin-update-checker.git
        Pop-Location
        Write-Host "✅ Plugin Update Checker downloaded" -ForegroundColor Green
    } else {
        exit 1
    }
}

Write-Host "✅ All external libraries found`n" -ForegroundColor Green

# Create build directory
Write-Host "📁 Creating build directory..." -ForegroundColor Yellow
$buildDir = "build\world-time-ai"

if (Test-Path "build") {
    Write-Host "   Cleaning old build directory..." -ForegroundColor Gray
    Remove-Item -Recurse -Force "build"
}

New-Item -ItemType Directory -Path "build" -Force | Out-Null
New-Item -ItemType Directory -Path $buildDir -Force | Out-Null

# Files and directories to copy
$itemsToCopy = @(
    "world-time-ai.php",
    "uninstall.php",
    "README.md",
    "SETUP-INSTRUCTIONS.md",
    "includes",
    "languages"
)

Write-Host "📋 Copying files..." -ForegroundColor Yellow
foreach ($item in $itemsToCopy) {
    if (-not (Test-Path $item)) {
        Write-Host "   ⚠️  Skipping missing: $item" -ForegroundColor Yellow
        continue
    }
    
    $target = Join-Path $buildDir $item
    
    if (Test-Path $item -PathType Container) {
        Write-Host "   Copying directory: $item" -ForegroundColor Gray
        Copy-Item -Path $item -Destination $target -Recurse -Force
    } else {
        Write-Host "   Copying file: $item" -ForegroundColor Gray
        Copy-Item -Path $item -Destination $target -Force
    }
}

# Create ZIP
Write-Host "`n📦 Creating ZIP file..." -ForegroundColor Yellow

$zipPath = "build\world-time-ai.zip"
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

# Use .NET to create ZIP
Add-Type -Assembly System.IO.Compression.FileSystem
$compressionLevel = [System.IO.Compression.CompressionLevel]::Optimal
[System.IO.Compression.ZipFile]::CreateFromDirectory(
    (Resolve-Path $buildDir).Path,
    (Join-Path (Get-Location) $zipPath),
    $compressionLevel,
    $false
)

if (-not (Test-Path $zipPath)) {
    Write-Host "❌ Failed to create ZIP file" -ForegroundColor Red
    exit 1
}

$zipSize = (Get-Item $zipPath).Length
$zipSizeMB = [math]::Round($zipSize / 1MB, 2)

Write-Host "✅ ZIP created: build\world-time-ai.zip ($zipSizeMB MB)`n" -ForegroundColor Green

# Read version from plugin file
$pluginContent = Get-Content "world-time-ai.php" -Raw
if ($pluginContent -match 'Version:\s*([0-9.]+)') {
    $version = $matches[1]
} else {
    $version = "unknown"
}

Write-Host "🎉 Build complete!" -ForegroundColor Cyan
Write-Host "================================" -ForegroundColor Cyan
Write-Host "Version: $version" -ForegroundColor White
Write-Host "ZIP file: build\world-time-ai.zip" -ForegroundColor White
Write-Host "Size: $zipSizeMB MB`n" -ForegroundColor White

Write-Host "📋 Next steps:" -ForegroundColor Yellow
Write-Host "1. Test the ZIP file locally" -ForegroundColor White
Write-Host "2. Commit: git add . && git commit -m 'Version $version'" -ForegroundColor White
Write-Host "3. Tag: git tag -a v$version -m 'Version $version'" -ForegroundColor White
Write-Host "4. Push: git push origin main && git push origin v$version" -ForegroundColor White
Write-Host "5. Create GitHub release: https://github.com/henrikandersen1978/what_is_the_time/releases/new" -ForegroundColor White
Write-Host "6. Upload build\world-time-ai.zip to the release`n" -ForegroundColor White

Write-Host "✨ Ready for release!" -ForegroundColor Green
Write-Host ""

# Ask if user wants to proceed with git operations
Write-Host "Vil du committe og tagge nu? (y/n)" -ForegroundColor Yellow
$response = Read-Host

if ($response -eq 'y') {
    Write-Host "`n🔧 Git operations..." -ForegroundColor Cyan
    
    git add .
    git commit -m "Build v$version"
    git tag -a "v$version" -m "Version $version"
    
    Write-Host "`n✅ Committed and tagged!" -ForegroundColor Green
    Write-Host ""
    Write-Host "Push til GitHub? (y/n)" -ForegroundColor Yellow
    $pushResponse = Read-Host
    
    if ($pushResponse -eq 'y') {
        git push origin main
        git push origin "v$version"
        
        Write-Host "`n✅ Pushed til GitHub!" -ForegroundColor Green
        Write-Host ""
        Write-Host "🌐 Opret nu GitHub release her:" -ForegroundColor Cyan
        Write-Host "   https://github.com/henrikandersen1978/what_is_the_time/releases/new" -ForegroundColor White
        Write-Host ""
        Write-Host "   Tag: v$version" -ForegroundColor White
        Write-Host "   Upload: build\world-time-ai.zip" -ForegroundColor White
    }
}

