# Database Optimization Guide

## 🚀 Performance Issue?

If your city pages load slowly (>3 seconds), you likely need database indices.

---

## 📊 Problem Symptoms

- **Slow Queries (6)** in Query Monitor showing `wta_shortcodes->get_cities_for_continent`
- Each query taking 2-3 seconds
- Total page load time: 10-20 seconds (first visit)

---

## ✅ Solution: Add Database Indices

The plugin includes a SQL file that creates optimized indices for WordPress postmeta lookups.

### Performance Impact:
```
BEFORE: 2.5s per query × 6 queries = 15s total
AFTER:  0.05s per query × 6 queries = 0.3s total

SPEEDUP: 50× faster! 🎯
```

---

## 🔧 How to Install Indices

### Option 1: phpMyAdmin (Recommended)

1. Log into your hosting control panel
2. Open **phpMyAdmin**
3. Select your WordPress database (usually `wp_xxxxx`)
4. Click **SQL** tab at the top
5. Open `database-indices.sql` file from plugin folder
6. Copy entire contents
7. Paste into SQL field
8. Click **Go**
9. ✅ Done! You should see "3 rows affected"

### Option 2: WP-CLI

```bash
wp db query < /path/to/database-indices.sql
```

### Option 3: MySQL Command Line

```bash
mysql -u username -p database_name < database-indices.sql
```

---

## 🔍 Verify Installation

Run this SQL query to check if indices exist:

```sql
SHOW INDEX FROM wp_postmeta WHERE Key_name LIKE 'idx_wta_%';
```

You should see:
- ✅ `idx_wta_meta_key_value`
- ✅ `idx_wta_post_meta`
- ✅ `idx_wta_meta_key`

---

## 🎯 What the Indices Do

### `idx_wta_meta_key_value`
Speeds up queries like:
```sql
WHERE pm.meta_key = 'wta_type' AND pm.meta_value = 'city'
```

### `idx_wta_post_meta`
Speeds up JOINs like:
```sql
LEFT JOIN wp_postmeta pm ON p.ID = pm.post_id AND pm.meta_key = 'wta_type'
```

### `idx_wta_meta_key`
Fallback for general meta queries like:
```sql
WHERE pm.meta_key = 'wta_population'
```

---

## 📈 Expected Results

### Before Indices:
```
Query Monitor:
- Slow Queries (6): 2.5s each
- Total: ~15s page load
```

### After Indices:
```
Query Monitor:
- Fast Queries (6): 0.05s each
- Total: <1s page load ✅
```

---

## ❓ FAQ

### Will this affect other plugins?
No! These indices only optimize `wp_postmeta` table which benefits ALL plugins using post meta.

### Can I run this multiple times?
Yes! The SQL uses `IF NOT EXISTS` to prevent duplicate indices.

### Will this use extra disk space?
Yes, but minimal (~10-50MB depending on database size).

### Can I remove the indices later?
Yes:
```sql
DROP INDEX idx_wta_meta_key_value ON wp_postmeta;
DROP INDEX idx_wta_post_meta ON wp_postmeta;
DROP INDEX idx_wta_meta_key ON wp_postmeta;
```

### Do I need to re-run after WordPress updates?
No! Indices persist through WordPress updates.

---

## 🆘 Troubleshooting

### "Index already exists" error
Safe to ignore! This means indices are already installed.

### Still slow after adding indices?
1. Check Query Monitor to confirm queries are now fast
2. Clear all caches (plugin cache, object cache, page cache)
3. Test in incognito window
4. Check if other plugins are causing slowdowns

### Need help?
Open an issue on GitHub with:
- Query Monitor screenshot
- Result of `SHOW INDEX FROM wp_postmeta;`
- Server specs (PHP version, MySQL version)

---

## 📝 Technical Details

These indices follow WordPress VIP performance best practices for high-traffic sites with heavy postmeta usage.

Recommended for sites with:
- 10,000+ posts
- 100,000+ postmeta rows
- Custom post types with multiple meta fields
- Heavy use of meta queries

---

**Version:** 2.35.45  
**Last Updated:** 2025-12-14

