# Upload Guide til GitHub Release

## 📦 Fil klar til upload:

**Fil:** `time-zone-clock-2.1.1.zip` (5.91 MB)

**Verificeret struktur:**
```
time-zone-clock/              ← Plugin folder (IKKE versioned!)
├── time-zone-clock.php
└── includes/
    ├── class-wta-*.php
    ├── admin/
    ├── core/
    ├── frontend/
    ├── helpers/
    ├── scheduler/
    ├── action-scheduler/
    └── plugin-update-checker/
```

✅ Korrekt: Bruger Unix forward slashes (/)
✅ Korrekt: Plugin folder hedder `time-zone-clock`
✅ Korrekt: Ingen version i folder navn

---

## 🚀 Upload til GitHub

### 1. Gå til GitHub Releases
https://github.com/henrikandersen1978/what_is_the_time/releases/new

### 2. Udfyld formular

**Choose a tag:**
```
v2.1.1 (vælg fra dropdown eller opret ny)
```

**Release title:**
```
Version 2.1.1 - Critical Hotfix
```

**Description:**
```markdown
## 🔧 Critical Hotfix Release

### What's Fixed
- ✅ Added missing frontend files (templates, shortcodes, assets)
- ✅ Fixed plugin activation errors
- ✅ Proper Unix-compatible zip structure
- ✅ Plugin folder name consistent with server

### For Users
If you experienced "Fatal error: Failed opening required..." - this fixes it.

**How to update:**
1. Go to Dashboard → Updates
2. Click "Check Again"
3. Click "Update Now" next to World Time AI

**Manual update via Git:**
```bash
cd /path/to/wp-content/plugins/time-zone-clock
git pull origin main
```

### Technical Details
- Plugin folder: `time-zone-clock` (matches server installation)
- File structure: Unix-compatible (forward slashes)
- Size: 5.91 MB
- Includes: All required frontend and backend files

**Full Changelog:** https://github.com/henrikandersen1978/what_is_the_time/compare/v2.1.0...v2.1.1
```

### 3. Attach binary
- Træk `releases/time-zone-clock-2.1.1.zip` til "Attach binaries"
- Eller klik og vælg filen

### 4. Publish
- ✅ Set as the latest release
- Klik "Publish release"

---

## ✅ Efter publicering

WordPress Plugin Update Checker vil automatisk:
1. Opdage den nye version
2. Vise den i Dashboard → Updates
3. Tillade en-klik opdatering

**Test det:**
1. Gå til din WordPress site
2. Dashboard → Updates
3. Klik "Check Again"
4. Se "World Time AI 2.1.1" tilgængelig

---

## 📝 Fremtidige releases

```powershell
# 1. Opdater version i time-zone-clock.php
# 2. Commit
git add .
git commit -m "Version X.Y.Z - beskrivelse"
git push

# 3. Tag
git tag vX.Y.Z -m "Beskrivelse"
git push origin vX.Y.Z

# 4. Byg release
.\build-release.ps1 -Version X.Y.Z

# 5. Upload til GitHub
# Upload releases/time-zone-clock-X.Y.Z.zip
```

---

**Note:** .zip filen er **IKKE** i git repository (gitignored), men findes lokalt i `releases/` mappen.


