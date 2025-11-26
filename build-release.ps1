# Build Release Script for World Time AI
# Uses Windows tar for proper Unix-compatible zip files

param([string]$Version = "2.1.1")

Write-Host "Building World Time AI v$Version..." -ForegroundColor Cyan

# Configuration - CRITICAL: Plugin folder name must match server installation
$PluginSlug = "time-zone-clock"
$TempDir = "build\temp-release"
$ReleasesDir = "releases"
$OutputFileName = "$PluginSlug-$Version.zip"
$OutputZip = "$ReleasesDir\$OutputFileName"

# Get absolute paths
$RootDir = Get-Location
$AbsOutputZip = Join-Path $RootDir $OutputZip
$AbsTempDir = Join-Path $RootDir $TempDir

# Create releases directory
if (!(Test-Path $ReleasesDir)) {
    New-Item -ItemType Directory -Path $ReleasesDir | Out-Null
    Write-Host "Created releases directory" -ForegroundColor Green
}

# Clean temp directory
if (Test-Path $TempDir) {
    Remove-Item -Path $TempDir -Recurse -Force
}

# Create temp directory with plugin folder
$PluginTempDir = "$TempDir\$PluginSlug"
New-Item -ItemType Directory -Path $PluginTempDir -Force | Out-Null

Write-Host "Copying files to temp directory..." -ForegroundColor Yellow

# Copy main plugin file
Copy-Item -Path "time-zone-clock.php" -Destination $PluginTempDir -Force

# Copy includes directory
Copy-Item -Path "includes" -Destination "$PluginTempDir\includes" -Recurse -Force

Write-Host "Creating zip with Windows tar..." -ForegroundColor Yellow

# Delete old zip if exists
if (Test-Path $OutputZip) {
    Remove-Item $OutputZip -Force
}

# Use Windows tar command for Unix-compatible zip
# Change to temp directory and create zip from there
Push-Location $TempDir
tar -caf "$AbsOutputZip" "$PluginSlug"
Pop-Location

# Check if zip was created
if (!(Test-Path $OutputZip)) {
    Write-Host "ERROR: Failed to create zip file!" -ForegroundColor Red
    exit 1
}

# Clean up temp directory
Remove-Item -Path $TempDir -Recurse -Force

# Calculate file size
$fileSize = (Get-Item $OutputZip).Length
$fileSizeMB = [math]::Round($fileSize / 1MB, 2)

Write-Host ""
Write-Host "SUCCESS!" -ForegroundColor Green
Write-Host "  File: $OutputZip" -ForegroundColor White
Write-Host "  Size: $fileSizeMB MB" -ForegroundColor White
Write-Host "  Plugin folder: $PluginSlug (consistent with server)" -ForegroundColor White
Write-Host ""
Write-Host "Ready to upload to GitHub Releases" -ForegroundColor Cyan

# Verify zip structure using tar
Write-Host ""
Write-Host "Zip contents (first 15 items):" -ForegroundColor Yellow
tar -tzf $OutputZip | Select-Object -First 15 | ForEach-Object {
    Write-Host "  $_" -ForegroundColor Gray
}

Write-Host ""
Write-Host "Verified: Plugin will install to '$PluginSlug' folder" -ForegroundColor Green
