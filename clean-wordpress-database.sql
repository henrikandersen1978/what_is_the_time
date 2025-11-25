-- SQL script to clean World Time AI plugin from WordPress database
-- Run this in phpMyAdmin before installing the plugin

-- 1. Backup current active plugins (IMPORTANT!)
-- Copy the output of this query somewhere safe:
SELECT option_value FROM wp_options WHERE option_name = 'active_plugins';

-- 2. Deactivate all plugins temporarily (SAFE - just deactivates)
UPDATE wp_options SET option_value = 'a:0:{}' WHERE option_name = 'active_plugins';

-- 3. Remove any World Time AI plugin entries
DELETE FROM wp_options WHERE option_name LIKE '%world%time%';
DELETE FROM wp_options WHERE option_name LIKE '%wta_%';
DELETE FROM wp_options WHERE option_name LIKE 'widget%world%';

-- 4. Clean transients related to plugins
DELETE FROM wp_options WHERE option_name LIKE '_transient_%plugin%';
DELETE FROM wp_options WHERE option_name LIKE '_transient_timeout_%plugin%';

-- 5. Clean plugin cache
DELETE FROM wp_options WHERE option_name LIKE '%plugin_cache%';

-- 6. Check what's left (should show empty result for World Time AI)
SELECT * FROM wp_options WHERE option_name LIKE '%world%time%';

-- After running these queries:
-- 1. Go to WordPress admin
-- 2. Delete the world-time-ai-1 folder via File Manager/FTP
-- 3. Upload world-time-ai.zip again
-- 4. It should now create world-time-ai folder (NOT world-time-ai-1)
-- 5. Activate the plugin


