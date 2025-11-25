# Manual Installation Guide for World Time AI

## Why Manual Installation?

If WordPress keeps creating `world-time-ai-1` folder, bypass WordPress installer completely.

## Method 1: Via File Manager (Easiest)

### Step 1: Prepare Local Files

1. **Download** `world-time-ai.zip` to your computer
2. **Extract** the zip file
3. You should see a folder: `world-time-ai/`
4. Inside should be:
   - `world-time-ai.php`
   - `includes/` folder
   - `languages/` folder
   - `README.md`
   - `uninstall.php`

### Step 2: Upload via File Manager

1. Log into your **hosting control panel** (cPanel, Plesk, etc.)
2. Open **File Manager**
3. Navigate to: `/wp-content/plugins/`
4. **Delete** any existing:
   - `world-time-ai/`
   - `world-time-ai-1/`
   - `world-time-ai-2/`
   - etc.
5. **Create new folder**: `world-time-ai` (exact name, no `-1`!)
6. **Upload ALL files** from your extracted folder INTO this new folder

### Step 3: Verify Structure

In File Manager, check that you have:
```
/wp-content/plugins/world-time-ai/
  ├── world-time-ai.php      ← MUST be here!
  ├── includes/
  │   ├── class-wta-core.php
  │   ├── plugin-update-checker/
  │   └── ...
  ├── languages/
  ├── README.md
  └── uninstall.php
```

**CRITICAL:** The path MUST be:
`/wp-content/plugins/world-time-ai/world-time-ai.php`

NOT:
- `/wp-content/plugins/world-time-ai-1/world-time-ai.php` ❌
- `/wp-content/plugins/world-time-ai/world-time-ai/world-time-ai.php` ❌

### Step 4: Activate in WordPress

1. Go to: **WordPress Admin → Plugins**
2. You should now see: **World Time AI**
3. Click: **Activate**
4. Done! ✅

---

## Method 2: Via FTP (Alternative)

### Step 1: Connect via FTP

1. Use an FTP client (FileZilla, WinSCP, etc.)
2. Connect to your server
3. Navigate to: `/wp-content/plugins/`

### Step 2: Upload

1. **Delete** any existing `world-time-ai*` folders
2. **Extract** `world-time-ai.zip` on your local computer
3. **Upload** the entire `world-time-ai/` folder to `/wp-content/plugins/`
4. Ensure permissions are: **755** for folders, **644** for files

### Step 3: Activate

Same as Method 1, Step 4.

---

## Method 3: Via SSH/WP-CLI (Advanced)

```bash
# Go to plugins directory
cd /path/to/wordpress/wp-content/plugins/

# Remove old versions
rm -rf world-time-ai*

# Upload your zip file first, then:
unzip world-time-ai.zip

# Set permissions
chmod 755 world-time-ai
chmod 644 world-time-ai/world-time-ai.php

# Activate via WP-CLI
wp plugin activate world-time-ai
```

---

## Troubleshooting

### "Plugin file does not exist" error

**Fix:** The folder structure is wrong. You must have:
```
/wp-content/plugins/world-time-ai/world-time-ai.php
```

### Plugin appears as "world-time-ai-1"

**Fix:** 
1. Deactivate plugin
2. Delete `world-time-ai-1` folder completely
3. Run `nuclear-cleanup.php` to clear WordPress cache
4. Re-upload manually to `world-time-ai` (no -1)

### Updates don't work

After manual installation, the Plugin Update Checker should work automatically.
If not, check that `/includes/plugin-update-checker/` folder exists.

---

## After Installation

1. **Delete** these security files:
   - `nuclear-cleanup.php`
   - `cleanup-before-install.php`
   - `MANUAL-INSTALL-GUIDE.md` (this file)

2. **Test** the plugin works

3. **Future updates** should work automatically via GitHub!

---

## Need Help?

If manual installation still fails, check:
- File permissions
- WordPress error logs
- PHP error logs
- Server disk space


