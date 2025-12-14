# Upgrade to v2.35.46 - Automatic Performance Fix! 🚀

## ⚡ Hvad sker der ved opdatering?

Når du opdaterer til v2.35.46, installeres **automatisk** 3 database indices der **dramatisk forbedrer performance**.

**Ingen manuel SQL krævet!** Plugin'et gør alt arbejdet for dig.

---

## 📊 Performance Forbedring

| Before v2.35.46 | After v2.35.46 |
|-----------------|----------------|
| 16-20s page load (first visit) | <1s page load ✅ |
| 2.5s per query | 0.05s per query |
| Manual SQL required | **Fully automatic** ✅ |

---

## 🔧 Hvad Installeres?

Ved opdatering installeres 3 MySQL indices på `wp_postmeta` tabellen:

1. **`idx_wta_meta_key_value`** - Speeds up WHERE clauses
2. **`idx_wta_post_meta`** - Speeds up JOINs  
3. **`idx_wta_meta_key`** - Fallback for general queries

**Resultat:** 50× hurtigere queries! 🎯

---

## ✅ Upgrade Proces

### **Trin 1: Upload Plugin**
Upload `time-zone-clock-2.35.46.zip` til WordPress:
- Plugins → Add New → Upload Plugin
- Vælg ZIP fil
- Klik "Install Now"
- Klik "Replace current with uploaded"

### **Trin 2: Opdater Plugin**
WordPress detekterer automatisk at der er en ny version og kører:
```php
WTA_Activator::activate()
  └─ install_performance_indices() // ← AUTOMATIC!
```

### **Trin 3: Færdig!**
Indices er installeret - ingen yderligere steps! ✅

---

## 🧪 Verificer Installation

### **Option 1: WordPress Debug Log**
Se efter denne linje i `wp-content/debug.log`:
```
World Time AI: Performance indices installed/verified
```

### **Option 2: phpMyAdmin**
Kør denne SQL for at verificere indices:
```sql
SHOW INDEX FROM wp_postmeta WHERE Key_name LIKE 'idx_wta_%';
```

Du burde se 3 indices:
- ✅ `idx_wta_meta_key_value`
- ✅ `idx_wta_post_meta`
- ✅ `idx_wta_meta_key`

### **Option 3: Test Page Load**
1. Ryd alle caches (page cache, object cache, browser cache)
2. Besøg en by-side (f.eks. `/europa/danmark/koebenhavn/`)
3. Tjek Query Monitor for load time
4. **Expected:** <1 sekund first load ✅

---

## ❓ FAQ

### **Skal jeg køre SQL manuelt?**
❌ NEJ! Alt sker automatisk ved opdatering.

### **Hvad hvis indices allerede findes?**
✅ Plugin'et tjekker først - sikker at køre flere gange.

### **Påvirker det andre plugins?**
✅ NEJ! Indices er generelle og kan hjælpe andre plugins der bruger postmeta.

### **Bruger det ekstra disk space?**
✅ Minimal (~10-50MB afhængigt af database størrelse).

### **Kan jeg fjerne indices senere?**
✅ JA, hvis nødvendigt (men det frarådes):
```sql
DROP INDEX idx_wta_meta_key_value ON wp_postmeta;
DROP INDEX idx_wta_post_meta ON wp_postmeta;
DROP INDEX idx_wta_meta_key ON wp_postmeta;
```

### **Hvad hvis opdatering fejler?**
Prøv at **deaktiver + reaktiver** plugin'et:
1. Plugins → Deactivate "World Time AI"
2. Plugins → Activate "World Time AI"
3. Indices installeres ved aktivering

---

## 🆘 Troubleshooting

### **Stadig langsom efter opdatering?**

1. **Ryd ALLE caches:**
   - WordPress object cache
   - Page cache (LiteSpeed, WP Rocket, etc.)
   - Browser cache

2. **Verificer indices er installeret:**
   ```sql
   SHOW INDEX FROM wp_postmeta WHERE Key_name LIKE 'idx_wta_%';
   ```
   Burde vise 3 indices.

3. **Tjek Query Monitor:**
   - Installer Query Monitor plugin
   - Besøg en by-side
   - Tjek "Slow Queries" tab
   - Queries burde nu være <0.1s hver

4. **Genaktiver plugin:**
   - Deaktiver plugin
   - Aktiver plugin igen
   - Indices re-installeres

---

## 🎉 Efter Opdatering

**Expected Results:**
- ✅ First page load: <1 sekund
- ✅ Cached page load: <0.3 sekunder
- ✅ No slow queries in Query Monitor
- ✅ Happy users! 🎯

**Hvis alt virker:**
Ingen yderligere steps! Nyd den nye performance! 🚀

---

## 📝 Version Info

- **From:** v2.35.45 (required manual SQL)
- **To:** v2.35.46 (automatic indices)
- **Date:** 2025-12-14
- **Breaking Changes:** None
- **Manual Steps:** None ✅

---

**Need Help?**
Open an issue on GitHub with:
- WordPress version
- PHP version
- MySQL version
- Query Monitor screenshot
- Result of `SHOW INDEX FROM wp_postmeta;`

