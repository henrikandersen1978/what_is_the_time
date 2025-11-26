# Deployment Guide - Version 2.1.0

## Problem
Serveren har ikke de opdaterede filer fra version 2.1.0 endnu.

## Løsning: Upload opdaterede filer til serveren

### Metode 1: Git Pull (Anbefalet hvis server har git adgang)

```bash
# SSH til din server
ssh runcloud@yourserver.com

# Naviger til plugin mappen
cd /home/runcloud/webapps/whatisthetime/wp-content/plugins/time-zone-clock

# Pull seneste version
git pull origin main

# Tjek at filerne er der
ls -la includes/

# Du skulle se:
# - class-wta-activator.php
# - class-wta-core.php
# - class-wta-deactivator.php
# - class-wta-loader.php
# - admin/
# - core/
# - helpers/
# - scheduler/
# etc.
```

### Metode 2: FTP/SFTP Upload

1. **Forbind til din server** via FTP/SFTP klient (FileZilla, WinSCP, etc.)

2. **Naviger til plugin mappen:**
   ```
   /home/runcloud/webapps/whatisthetime/wp-content/plugins/time-zone-clock/
   ```

3. **Upload disse mapper/filer** (overskriv eksisterende):
   - `includes/` (hele mappen)
   - `time-zone-clock.php` (hovedfilen)

4. **Sørg for at disse filer eksisterer på serveren:**
   ```
   includes/class-wta-activator.php
   includes/class-wta-core.php
   includes/class-wta-deactivator.php
   includes/class-wta-loader.php
   includes/admin/class-wta-admin.php
   includes/admin/class-wta-settings.php
   includes/admin/views/ (alle filer)
   includes/core/class-wta-github-fetcher.php
   includes/core/class-wta-importer.php
   includes/core/class-wta-post-type.php
   includes/core/class-wta-queue.php
   includes/helpers/class-wta-ai-translator.php (NY FIL!)
   includes/helpers/class-wta-logger.php
   includes/helpers/class-wta-quick-translate.php
   includes/helpers/class-wta-timezone-helper.php
   includes/helpers/class-wta-utils.php
   includes/scheduler/class-wta-ai-processor.php
   includes/scheduler/class-wta-structure-processor.php
   includes/scheduler/class-wta-timezone-processor.php
   ```

### Metode 3: Runcloud Panel (hvis du bruger Runcloud)

1. Log ind på Runcloud panel
2. Gå til din web app
3. Brug File Manager
4. Naviger til `/home/runcloud/webapps/whatisthetime/wp-content/plugins/time-zone-clock/`
5. Upload filerne

### Metode 4: Pak op .zip fil på serveren

```bash
# SSH til serveren
ssh runcloud@yourserver.com

# Gå til plugins mappen
cd /home/runcloud/webapps/whatisthetime/wp-content/plugins/

# Backup eksisterende plugin
mv time-zone-clock time-zone-clock-backup

# Upload world-time-ai-2.1.0.zip til serveren (via FTP eller scp)

# Pak ud
unzip world-time-ai-2.1.0.zip -d time-zone-clock

# Tjek permissions
chown -R runcloud:runcloud time-zone-clock
chmod -R 755 time-zone-clock
```

## Efter upload

1. **Gå til WordPress Admin:**
   ```
   https://whatisthetime.xhct10lger-xd6r122xy49g.p.temp-site.link/wp-admin/
   ```

2. **Deaktiver plugin** (hvis det er aktivt)

3. **Reaktiver plugin** 
   - Dette vil køre aktiverings-rutinen
   - Scheduler nye actions
   - Opdaterer database

4. **Flush rewrite rules:**
   - Gå til Settings → Permalinks
   - Klik "Save Changes"

5. **Tjek at det virker:**
   - Gå til World Time AI → Dashboard
   - Tjek version nummer (skal være 2.1.0)

## Fejlfinding

### Hvis du stadig får fejl:

```bash
# SSH til serveren
ssh runcloud@yourserver.com

# Tjek at filen eksisterer
ls -la /home/runcloud/webapps/whatisthetime/wp-content/plugins/time-zone-clock/includes/class-wta-core.php

# Hvis filen mangler, upload den!

# Tjek permissions
cd /home/runcloud/webapps/whatisthetime/wp-content/plugins/time-zone-clock/
find . -type f -not -perm 644 -exec chmod 644 {} \;
find . -type d -not -perm 755 -exec chmod 755 {} \;
```

### Tjek fil struktur på serveren:

```bash
cd /home/runcloud/webapps/whatisthetime/wp-content/plugins/time-zone-clock/

# Skal vise alle filer
tree -L 3 includes/

# Eller
ls -R includes/
```

## Quick Fix - Kopier build til server

Hvis du har adgang til server filer lokalt:

```bash
# Pak build mappen
cd build/time-zone-clock
zip -r ../../world-time-ai-2.1.0-complete.zip .

# Upload til server og pak ud i plugin mappen
```

## Kontakt

Hvis problemet fortsætter, kontakt mig med output fra:
```bash
ls -la /home/runcloud/webapps/whatisthetime/wp-content/plugins/time-zone-clock/includes/
```



