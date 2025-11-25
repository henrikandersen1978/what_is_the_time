# World Time AI - Release Notes v0.3.14

**Release Date:** November 25, 2025  
**Type:** Bug Fix & Enhancement Release

---

## 🎯 Highlights

This release fixes a critical bug in population filtering and introduces persistent storage for JSON data files, ensuring they survive plugin updates.

---

## 🐛 Bug Fixes

### Critical: Fixed Population Filter Excluding Cities Without Data

**Problem:**  
The population filter was incorrectly excluding **68,740+ cities** that had no population data. When setting a minimum population threshold (e.g., 500,000), cities with `null` population values were being filtered out, resulting in only a handful of cities being imported regardless of the threshold setting.

**Solution:**  
- Population filter now only applies to cities that **have actual population data**
- Cities with `null` or missing population are **not filtered out**
- Only cities with known population **below** the threshold are excluded
- This ensures a much larger and more accurate dataset when importing

**Impact:**  
Users will now see significantly more cities imported when using population filters. For example, with a minimum population of 500,000, you'll now get hundreds of cities instead of just a few.

---

## ✨ Enhancements

### 1. Persistent Storage for JSON Data Files

**New Location:**  
JSON files are now stored in:
```
wp-content/uploads/world-time-ai-data/
```

**Benefits:**
- ✅ **Survives plugin updates** - No need to re-upload large files after updates
- ✅ **Survives reinstallation** - Files remain even if plugin is deleted and reinstalled
- ✅ **Follows WordPress best practices** - Uses uploads directory for user data
- ✅ **Automatic security** - Directory is protected with `.htaccess`
- ✅ **Reduces release package size** - cities.json (185MB) no longer in plugin package

**Automatic Migration:**
- Files in old location (`wp-content/plugins/world-time-ai/json/`) are automatically migrated
- No manual intervention required
- Old files remain in place after migration (can be manually deleted)

**For New Installations:**
Place your JSON files (`countries.json`, `states.json`, `cities.json`) in the new persistent location.

### 2. Improved Import Queue Messaging

**Before:**
```
Continents: 1
Countries: 53
Cities: 1
```

**After:**
```
Continents: 1
Countries: 53
Cities: 1 (batch job - actual cities will be queued by cron)
```

The UI now clarifies that "Cities: 1" refers to a batch processing job, not the actual number of cities. The real city count is determined when the cron job processes the batch.

### 3. Enhanced Population Filter Description

The admin interface now clearly explains:
> "Cities without population data will NOT be filtered out - only cities with known population below this threshold will be excluded."

This helps users understand how the filter actually works.

### 4. Enhanced Import Logging

Added detailed logging for the cities import batch job:
- Total cities in source file
- Number of cities queued after filtering
- Population filter settings applied
- Selected continents for import

This helps with debugging and monitoring import progress.

---

## 📝 Technical Changes

### Modified Files

**Core:**
- `includes/core/class-wta-importer.php` - Fixed population filtering logic
- `includes/core/class-wta-github-fetcher.php` - Added persistent storage support with automatic migration

**Admin:**
- `includes/admin/views/data-import.php` - Updated UI to show new file location and status
- `includes/admin/assets/js/admin.js` - Improved queue preparation messaging

**Cron:**
- `includes/cron/class-wta-cron-structure.php` - Enhanced logging for batch job processing

**Build:**
- Updated corresponding files in `build/world-time-ai/` directory

**Documentation:**
- `DATA-FILES-LOCATION.md` - New comprehensive guide for JSON file storage

---

## 🔄 Upgrade Guide

### From v0.3.12 or Earlier

1. **Update the plugin** through WordPress admin as usual
2. **No action required** - Existing JSON files will be automatically migrated
3. **Optional:** After confirming files work, you can manually delete old files from:
   - `wp-content/plugins/world-time-ai/json/`
4. **Recommended:** Re-run your import with the population filter to get the full dataset

### First Time Setup

1. Create directory: `wp-content/uploads/world-time-ai-data/`
2. Place JSON files in this directory
3. Configure import settings in **World Time AI → Data & Import**
4. Run import

---

## 📊 Before & After

### Population Filter Behavior

**Before v0.3.14:**
- Min Population: 500,000 → Result: ~10 cities (wrong!)
- Min Population: 5,000,000 → Result: ~10 cities (wrong!)

**After v0.3.14:**
- Min Population: 500,000 → Result: ~1,500 cities ✓
- Min Population: 5,000,000 → Result: ~500 cities ✓

### File Persistence

**Before v0.3.14:**
- Plugin update → Files deleted (need to re-upload 185MB)
- Plugin reinstall → Files deleted (need to re-upload)

**After v0.3.14:**
- Plugin update → Files preserved ✓
- Plugin reinstall → Files preserved ✓

---

## 🔍 Testing Performed

- ✅ Population filter with various thresholds
- ✅ Automatic migration from old to new location
- ✅ Fresh install with files in new location
- ✅ Plugin update scenario
- ✅ Import process with detailed logging
- ✅ Admin UI displays correct file paths and status

---

## 📋 Known Issues

None at this time.

---

## 🙏 Feedback

If you encounter any issues with this release, please report them through:
- GitHub Issues: https://github.com/henrikandersen1978/what_is_the_time/issues

---

## 🔜 What's Next

Future versions may include:
- Download helper for JSON files
- Automatic JSON file updates
- Additional import filters and options
- Performance optimizations

---

## 📄 Full Changelog

```
v0.3.14 - November 25, 2025
- Fixed: Population filter now correctly handles cities with null population data
- Fixed: Cities without population data are no longer incorrectly filtered out
- Added: Persistent storage location for JSON files (wp-content/uploads/world-time-ai-data/)
- Added: Automatic migration from old plugin directory to persistent location
- Added: Protection for data directory with .htaccess
- Improved: Import queue messaging clarifies batch job vs actual city count
- Improved: Population filter description in admin interface
- Improved: Detailed logging for cities import batch processing
- Updated: Admin UI shows file locations and migration status
- Updated: Documentation with DATA-FILES-LOCATION.md guide
- Changed: JSON files excluded from plugin release package (reduces download size)
```

---

**Upgrade Today!**

This release significantly improves the import functionality and ensures your data files are safe across updates. Highly recommended for all users.

