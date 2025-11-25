# World Time AI - Version 2.1.1

## 🔧 Hotfix Release

Version 2.1.1 fixes a critical issue where frontend files were missing from the repository.

---

## 🐛 Bug Fixes

### Missing Frontend Files
- **Fixed**: Added missing frontend files that were accidentally omitted in v2.1.0
- **Added**: `includes/frontend/class-wta-shortcodes.php`
- **Added**: `includes/frontend/class-wta-template-loader.php`
- **Added**: `includes/frontend/templates/single-world_time_location.php`
- **Added**: `includes/frontend/assets/css/frontend.css`
- **Added**: `includes/frontend/assets/js/clock.js`

### Impact
Without these files, the plugin would fail to load with:
```
Fatal error: Failed opening required '.../includes/class-wta-core.php'
```

This hotfix resolves the activation error and restores full functionality.

---

## 📦 What's Included

This release includes all features from v2.1.0 plus the critical fix:

### From v2.1.0:
- ✨ AI-Powered Translation for all locations
- ⚡ Faster processing (1-minute intervals)
- 🔗 Clean URL structure
- 📝 Better AI content generation
- 🎯 Smart translation caching

### New in v2.1.1:
- ✅ **Critical Fix**: All required files now included
- ✅ Plugin activates without errors
- ✅ Frontend templates work correctly
- ✅ Clock JavaScript and CSS loaded properly

---

## 🚀 Installation

### Fresh Installation
1. Download `world-time-ai-2.1.1.zip` from releases
2. Upload to `/wp-content/plugins/`
3. Extract
4. Activate plugin
5. Configure OpenAI API key
6. Go to Settings → Permalinks → Save Changes

### Updating from 2.1.0
**If you're experiencing the activation error:**

#### Option 1: Automatic Update (Recommended)
1. Go to Dashboard → Updates in WordPress
2. Click "Check Again"
3. You should see "World Time AI 2.1.1" available
4. Click "Update Now"

#### Option 2: Manual Update via Git
```bash
cd /path/to/wp-content/plugins/time-zone-clock
git pull origin main
```

#### Option 3: Manual Upload
1. Deactivate plugin
2. Delete old plugin folder
3. Upload new version
4. Activate plugin

---

## 🔍 Verification

After update, verify these files exist:
```
includes/frontend/class-wta-shortcodes.php
includes/frontend/class-wta-template-loader.php
includes/frontend/templates/single-world_time_location.php
includes/frontend/assets/css/frontend.css
includes/frontend/assets/js/clock.js
```

---

## 📝 Commits in This Release

- `2f0dac0` - Fix: Add missing frontend files (templates, shortcodes, assets)
- `88acd52` - Version 2.1.1 - Hotfix: Add missing frontend files

---

## 🙏 Apologies

We apologize for the inconvenience caused by the missing files in v2.1.0. This hotfix ensures all users can activate and use the plugin successfully.

---

## 📅 Release Date

November 25, 2025

---

**Full Changelog**: https://github.com/henrikandersen1978/what_is_the_time/compare/v2.1.0...v2.1.1

