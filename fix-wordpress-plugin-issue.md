# Fix: "Plugin-filen eksisterer ikke" Problem

## Problemet
WordPress kan ikke finde plugin-filen selvom den fysisk er der. Dette skyldes sandsynligvis databasecache.

## Løsning 1: Rens WordPress Database

Kør disse SQL-queries i phpMyAdmin:

```sql
-- 1. Tjek aktive plugins
SELECT * FROM wp_options WHERE option_name = 'active_plugins';

-- 2. Se værdien (den ser ud som: a:1:{i:0;s:35:"world-time-ai/world-time-ai.php";})

-- 3. Deaktiver ALLE plugins midlertidigt
UPDATE wp_options SET option_value = 'a:0:{}' WHERE option_name = 'active_plugins';

-- 4. Rens plugin cache
DELETE FROM wp_options WHERE option_name LIKE '%plugin%cache%';

-- 5. Rens transients
DELETE FROM wp_options WHERE option_name LIKE '%transient%plugin%';
```

Efter disse queries:
1. Genindlæs WordPress admin
2. Gå til Plugins - den skulle nu være der og inaktiv
3. Aktiver plugin'et

## Løsning 2: Genaktiver via wp-config.php

Tilføj midlertidigt til wp-config.php (før "That's all, stop editing!"):

```php
define('WP_ADMIN', true);
define('FORCE_SSL_ADMIN', false);
```

Dette kan hjælpe WordPress med at genkende plugin'et.

## Løsning 3: Brug WP-CLI (hvis tilgængeligt)

```bash
# List plugins
wp plugin list

# Deactivate all
wp plugin deactivate --all

# Activate specific plugin
wp plugin activate world-time-ai
```

## Løsning 4: Start helt forfra

1. I phpMyAdmin, kør:
```sql
-- Backup først!
SELECT * FROM wp_options WHERE option_name = 'active_plugins' INTO OUTFILE '/tmp/backup_plugins.txt';

-- Nulstil active_plugins
UPDATE wp_options SET option_value = 'a:0:{}' WHERE option_name = 'active_plugins';

-- Slet alle plugin options
DELETE FROM wp_options WHERE option_name LIKE 'wta_%';
DELETE FROM wp_options WHERE option_name LIKE 'world_time%';
```

2. Via FTP/File Manager:
   - Slet: `/wp-content/plugins/world-time-ai/`
   - Upload frisk kopi fra `manual-install/world-time-ai/`

3. WordPress admin:
   - Gå til Plugins
   - Aktiver World Time AI

## Debug: Tjek hvad WordPress ser

Opret en fil `debug-plugins.php` i WordPress root:

```php
<?php
require_once('wp-load.php');

$plugin_folder = WP_PLUGIN_DIR . '/world-time-ai';
$plugin_file = $plugin_folder . '/world-time-ai.php';

echo "Plugin folder exists: " . (is_dir($plugin_folder) ? 'YES' : 'NO') . "\n";
echo "Plugin file exists: " . (file_exists($plugin_file) ? 'YES' : 'NO') . "\n";
echo "Plugin folder path: $plugin_folder\n";
echo "Plugin file path: $plugin_file\n";

if (file_exists($plugin_file)) {
    $plugin_data = get_plugin_data($plugin_file);
    echo "\nPlugin data:\n";
    print_r($plugin_data);
}

// Check active plugins
$active = get_option('active_plugins');
echo "\nActive plugins:\n";
print_r($active);
?>
```

Kør: `https://yoursite.com/debug-plugins.php` og se output.

## Sidste udvej: Test lokalt først

Hvis intet virker:
1. Installer Local by Flywheel eller XAMPP
2. Installer plugin'et lokalt først
3. Test at det virker
4. Derefter overfør det hele til live-serveren

Dette sikrer at plugin'et faktisk virker før vi fejlfinder serveren.


