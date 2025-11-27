# Release Files

This directory contains production-ready .zip files for WordPress plugin distribution.

## Files

- `time-zone-clock-X.Y.Z.zip` - Plugin releases

## Structure

Each .zip file contains:
```
time-zone-clock/              ← Plugin folder (matches server folder name)
├── time-zone-clock.php       ← Main plugin file
└── includes/                 ← All plugin files
    ├── class-wta-*.php
    ├── admin/
    ├── core/
    ├── frontend/
    ├── helpers/
    ├── scheduler/
    ├── action-scheduler/
    └── plugin-update-checker/
```

## Important

**The plugin folder MUST be named `time-zone-clock`** to match the server installation. If the folder name changes, WordPress will deactivate the plugin on update.

## Build Process

To build a new release:

```powershell
.\build-release.ps1 -Version X.Y.Z
```

This will:
1. Create a properly structured .zip file
2. Place it in `releases/time-zone-clock-X.Y.Z.zip`
3. Verify the structure

## Upload to GitHub

1. Create a new GitHub Release
2. Tag version: `vX.Y.Z`
3. Attach the .zip file from this directory
4. WordPress Plugin Update Checker will find it automatically




