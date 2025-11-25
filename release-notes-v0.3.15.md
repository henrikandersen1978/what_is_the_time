# World Time AI - Release Notes v0.3.15

**Release Date:** November 25, 2025  
**Type:** Critical Fix Release  

---

## 🎯 Highlights

**CRITICAL FIX**: Locations now get Danish names and Danish URLs from the start of import. This fixes the fundamental issue where posts were created with English names, resulting in permanent English URLs.

---

## 🐛 Critical Fixes

### Posts Now Created with Danish Names and URLs

**Problem:**  
Posts were created with English names first, then translated later by AI. This caused:
- ❌ **Permanent English URLs** (e.g., `/europe/denmark/` instead of `/europa/danmark/`)
- ❌ URLs could NOT be changed after creation
- ❌ Posts displayed in English in WordPress admin before AI processing

**Solution:**  
- ✅ New `WTA_Quick_Translate` helper class with common location translations
- ✅ Posts are now created with **Danish names immediately**
- ✅ URLs are **Danish from the start**
- ✅ **Cannot be undone** for existing posts - they must be recreated

**Examples:**
| English | Danish | URL |
|---------|--------|-----|
| Europe | Europa | `/europa/` |
| Denmark | Danmark | `/europa/danmark/` |
| Germany | Tyskland | `/europa/tyskland/` |
| United Kingdom | Storbritannien | `/europa/storbritann ien/` |

---

## ✨ New Features

### Quick Translation System

Added `class-wta-quick-translate.php` with:
- Static translation map for 50+ common locations
- Instant translation without API calls
- Covers all continents and major European countries
- Extensible for adding custom translations

---

## 🔧 Technical Changes

### Modified Files

**New:**
- `includes/helpers/class-wta-quick-translate.php` - New translation helper

**Updated:**
- `includes/core/class-wta-queue-processor.php` - Uses Danish names for post creation
- `includes/class-wta-core.php` - Loads quick translate helper
- `world-time-ai.php` - Version bump to 0.3.15

**Metadata:**
- Added `wta_name_danish` meta field to all posts
- `wta_name_original` still stores English name

---

## ⚠️ IMPORTANT: Breaking Change

**Existing posts MUST be deleted and reimported** to get Danish URLs.

### Why?
WordPress **permalinks cannot be changed** after post creation. Your existing posts have English URLs that are permanent.

### How to Update:

1. **Backup your site** (if you have any important data)

2. **Delete all existing location posts:**
   - Go to **World Time AI → Tools**
   - Click **"Reset All Data"** (deletes posts and queue)

3. **Run new import:**
   - Go to **World Time AI → Data & Import**
   - Configure your import settings
   - Click **"Prepare Import Queue"**

4. **Wait for processing:**
   - Structure import creates posts with Danish names
   - AI content generation runs automatically
   - Posts published with Danish URLs

### Expected Result:

**Before v0.3.15:**
```
europe/ (English URL, draft)
europe/denmark/ (English URL, draft)
```

**After v0.3.15:**
```
europa/ (Danish URL, published after AI)
europa/danmark/ (Danish URL, published after AI)
```

---

## 📊 Translation Coverage

### Currently Supported:

**Continents:** All 7 continents  
**Countries:** 50+ countries (focus on Europe)

### Not in Translation Map:

Cities and less common countries will:
- Keep English name initially
- Get proper Danish translation when AI processes them
- Still get Danish-transliterated URLs (safe characters)

---

## 🔍 Testing Performed

- ✅ Verified Danish names in post creation
- ✅ Confirmed Danish URLs are generated
- ✅ Tested with multiple continents and countries
- ✅ Verified backward compatibility with metadata

---

## 📋 Known Issues

**Hierarchical Display in Admin:**
WordPress admin list may show items with dashes instead of proper hierarchy. This is a WordPress admin UI limitation and does NOT affect the actual parent-child relationships or front-end URLs. The hierarchy works correctly.

---

## 🔜 What's Next

v0.3.16 may include:
- Expanded translation map (more countries and major cities)
- Custom translation management in admin
- Better hierarchical display in WordPress admin

---

## 📄 Full Changelog

```
v0.3.15 - November 25, 2025
- BREAKING: Posts now created with Danish names and URLs
- Added: WTA_Quick_Translate helper class
- Added: Translation map for 50+ common locations
- Added: wta_name_danish meta field to all posts
- Fixed: Posts no longer have permanent English URLs
- Fixed: URLs are now Danish from creation
- Changed: Queue processor uses Danish names immediately
- Note: Existing posts must be deleted and reimported
```

---

**⚠️ BREAKING CHANGE - MUST REIMPORT**

Delete existing posts and reimport to get Danish URLs. This cannot be avoided due to WordPress permalink limitations.

