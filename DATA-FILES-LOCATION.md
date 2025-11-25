# Data Files Location

## Important: JSON Files Storage

Starting with version 0.3.12, the World Time AI plugin stores its JSON data files in a **persistent location** that survives plugin updates and reinstallations.

### New Location (Recommended)

Place your JSON files in:
```
wp-content/uploads/world-time-ai-data/
```

Files:
- `countries.json`
- `states.json`
- `cities.json`

### Why This Change?

- ✅ **Survives plugin updates** - Files are not affected when updating via WordPress admin
- ✅ **Survives reinstallation** - Files remain even if you delete and reinstall the plugin  
- ✅ **WordPress best practice** - Using the uploads directory for user data
- ✅ **Protected** - Directory is automatically protected with `.htaccess`

### Automatic Migration

If you have files in the old location (`wp-content/plugins/world-time-ai/json/`), they will be **automatically migrated** to the new location when first accessed. The old files will remain in place (you can manually delete them after confirming the migration was successful).

### Manual Setup

1. Navigate to `wp-content/uploads/`
2. Create a folder named `world-time-ai-data` (the plugin will also create this automatically)
3. Place your JSON files (`countries.json`, `states.json`, `cities.json`) in this folder
4. The plugin will automatically detect and use these files

### Checking File Status

Go to **World Time AI → Data & Import** in your WordPress admin to see:
- Whether local files are detected
- The exact path where files should be placed
- File sizes and migration status

### GitHub URLs (Alternative)

If you prefer not to store large files locally, you can still configure GitHub URLs in the plugin settings. However, local files are recommended for better performance and to avoid memory issues with the large cities.json file (185MB).

### Backup Recommendation

Since these files are large and time-consuming to download, consider:
- Including `wp-content/uploads/world-time-ai-data/` in your backup strategy
- Keeping copies of these files separately
- The JSON files are available from the original data source if needed

## Upgrade Notes

### From versions before 0.3.12

- Your existing files in `wp-content/plugins/world-time-ai/json/` will continue to work
- They will be automatically migrated to the new persistent location on first use
- No action required from you

### During plugin updates

- New plugin versions will **not include** these JSON files in the download
- Your existing files will be preserved in the persistent location
- No need to re-upload these large files with each update

