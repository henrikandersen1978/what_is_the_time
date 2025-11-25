# Build and Release Guide - World Time AI 2.0

## 🎯 Quick Start: Install External Libraries

Før du kan bruge plugin'et, skal du downloade 2 eksterne libraries:

### 1. Download Action Scheduler

```bash
cd includes/
git clone https://github.com/woocommerce/action-scheduler.git
```

### 2. Download Plugin Update Checker

```bash
cd includes/
git clone https://github.com/YahnisElsts/plugin-update-checker.git
```

## 📦 Installation i WordPress

### Metode 1: Direkte Installation (Udviklingsmiljø)

1. Kopiér hele `world-time-ai` mappen til `/wp-content/plugins/`
2. Sørg for eksterne libraries er installeret (se ovenfor)
3. Aktivér plugin i WordPress

### Metode 2: Via ZIP (Produktion)

Se "Lav Release" sektionen nedenfor.

## 🚀 Lav GitHub Release for Auto-Updates

For at automatiske opdateringer virker, skal du lave et GitHub release.

### Trin 1: Opdater Version (hvis nødvendigt)

Hvis du laver en ny version:

1. Åbn `world-time-ai.php`
2. Opdater version:
   ```php
   define( 'WTA_VERSION', '2.0.0' );
   ```
3. Opdater plugin header:
   ```php
   * Version:           2.0.0
   ```

### Trin 2: Byg Plugin ZIP

```bash
# Fra rod af repository
php build-plugin.php
```

Eller manuelt:

```bash
# Opret build directory
mkdir -p build

# Kopiér filer (UDEN eksterne libraries)
cp -r includes/ build/world-time-ai/includes/
cp world-time-ai.php build/world-time-ai/
cp uninstall.php build/world-time-ai/
cp README.md build/world-time-ai/
cp -r languages/ build/world-time-ai/languages/

# Kopiér eksterne libraries
cp -r includes/action-scheduler build/world-time-ai/includes/
cp -r includes/plugin-update-checker build/world-time-ai/includes/

# Lav ZIP
cd build
zip -r world-time-ai.zip world-time-ai/
cd ..
```

### Trin 3: Commit og Tag

```bash
# Commit eventuelle ændringer
git add .
git commit -m "Release v2.0.0"

# Lav tag
git tag -a v2.0.0 -m "Version 2.0.0 - Initial release of complete rewrite"

# Push til GitHub
git push origin main
git push origin v2.0.0
```

### Trin 4: Opret GitHub Release

1. Gå til GitHub repository: https://github.com/henrikandersen1978/what_is_the_time
2. Klik på **Releases** → **Create a new release**
3. Vælg tag: `v2.0.0`
4. Release title: `World Time AI v2.0.0`
5. Description:
   ```markdown
   # World Time AI 2.0.0
   
   Complete ground-up rewrite with all v1.0 issues fixed!
   
   ## ✨ Key Features
   - Danish URLs from start
   - Persistent data storage
   - Action Scheduler integration
   - Live clocks with real-time updates
   - AI-powered Danish content
   
   ## 🔧 Installation
   See [SETUP-INSTRUCTIONS.md](SETUP-INSTRUCTIONS.md)
   
   ## ⚠️ Breaking Changes
   This is a complete rewrite - not compatible with v1.0 data.
   ```
6. **Upload `world-time-ai.zip`** (vigtigt!)
7. Klik **Publish release**

## 🔄 Test Auto-Update Mechanism

### I WordPress:

1. Installer v2.0.0 fra ZIP'en
2. Aktivér plugin
3. Lav en ny version (f.eks. v2.0.1) med små ændringer
4. Lav nyt release på GitHub
5. Tjek WordPress → Dashboard → Updates
   - Du skulle se "World Time AI" klar til opdatering

## 📋 Release Checklist

- [ ] Eksterne libraries downloadet (Action Scheduler + Plugin Update Checker)
- [ ] Version opdateret i `world-time-ai.php`
- [ ] Alle ændringer committed
- [ ] Git tag oprettet (f.eks. v2.0.0)
- [ ] Tag pushed til GitHub
- [ ] GitHub release oprettet
- [ ] ZIP fil uploaded til release
- [ ] Release published (ikke draft)

## 🎯 Automatisk Opdateringer Workflow

```
1. Du laver ændringer i koden
     ↓
2. Opdater version i world-time-ai.php
     ↓
3. Commit og push
     ↓
4. Lav git tag (f.eks. v2.0.1)
     ↓
5. Push tag til GitHub
     ↓
6. Opret GitHub release med ZIP fil
     ↓
7. WordPress tjekker for opdateringer (hver 12 time)
     ↓
8. Brugere ser opdatering i wp-admin
     ↓
9. Klik "Update" - færdig! ✅
```

## 🔍 Verificer Plugin Update Checker

Tjek at Plugin Update Checker er konfigureret korrekt i `world-time-ai.php`:

```php
require WTA_PLUGIN_DIR . 'includes/plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$updateChecker = PucFactory::buildUpdateChecker(
    'https://github.com/henrikandersen1978/what_is_the_time',
    __FILE__,
    'world-time-ai'
);

$updateChecker->getVcsApi()->enableReleaseAssets();
```

## 🐛 Troubleshooting Auto-Updates

### Opdateringer vises ikke

1. **Tjek GitHub release:**
   - Er der et release med den rigtige tag?
   - Er ZIP filen uploaded?
   - Er release published (ikke draft)?

2. **Tjek Plugin Update Checker:**
   - Er library'et installeret korrekt?
   - Tjek WordPress → Plugins → Plugin Editor → Se efter fejl

3. **Force tjek for opdateringer:**
   - WordPress → Dashboard → Updates → Check Again

4. **Debug mode:**
   Tilføj til `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```
   Tjek `wp-content/debug.log` for fejl.

## 📝 Version Numbering

Følg Semantic Versioning (SemVer):

- **Major (2.0.0):** Breaking changes
- **Minor (2.1.0):** Nye features (bagudkompatible)
- **Patch (2.0.1):** Bug fixes

Eksempler:
- `v2.0.0` - Initial release
- `v2.0.1` - Bug fix
- `v2.1.0` - Ny feature (f.eks. ny shortcode)
- `v3.0.0` - Breaking change (f.eks. ændret database struktur)

## 🎉 Success!

Når alt virker:
1. ✅ Plugin installeret i WordPress
2. ✅ Aktiveret uden fejl
3. ✅ Dashboard viser korrekt version
4. ✅ WordPress kan tjekke for opdateringer
5. ✅ Nye versioner vises automatisk

**Du er nu klar til at bruge og opdatere World Time AI 2.0!** 🚀

