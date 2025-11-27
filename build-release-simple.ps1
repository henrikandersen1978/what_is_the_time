# Build Release Script for World Time AI
# Creates properly structured .zip file for WordPress

param([string]$Version = "2.1.1")

$PluginSlug = "time-zone-clock"
$TempDir = "build\temp-release"
$ReleasesDir = "releases"
$OutputZip = "$ReleasesDir\$PluginSlug-$Version.zip"

# Create releases directory
if (!(Test-Path $ReleasesDir)) {
    New-Item -ItemType Directory -Path $ReleasesDir | Out-Null
}

# Clean temp
if (Test-Path $TempDir) {
    Remove-Item -Path $TempDir -Recurse -Force
}

# Create plugin folder in temp
$PluginTempDir = "$TempDir\$PluginSlug"
New-Item -ItemType Directory -Path $PluginTempDir -Force | Out-Null

Write-Host "Copying files..."

# Copy files
Copy-Item -Path "time-zone-clock.php" -Destination $PluginTempDir
Copy-Item -Path "includes" -Destination "$PluginTempDir\includes" -Recurse -Force

Write-Host "Creating zip..."

# Delete old zip
if (Test-Path $OutputZip) {
    Remove-Item $OutputZip -Force
}

# Create zip
Compress-Archive -Path "$TempDir\*" -DestinationPath $OutputZip -CompressionLevel Optimal

# Clean temp
Remove-Item -Path $TempDir -Recurse -Force

$fileSize = [math]::Round((Get-Item $OutputZip).Length / 1MB, 2)

Write-Host ""
Write-Host "SUCCESS!" -ForegroundColor Green
Write-Host "File: $OutputZip" -ForegroundColor White
Write-Host "Size: $fileSizeMB MB" -ForegroundColor White
Write-Host "Plugin folder: $PluginSlug" -ForegroundColor White




