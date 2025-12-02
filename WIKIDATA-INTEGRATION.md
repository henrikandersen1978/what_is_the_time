# Wikidata Integration - Dokumentation

## 🎯 Formål

World Time AI bruger nu Wikidata API til at få **100% korrekte** oversættelser af lande- og bynavne til dansk. Dette løser problemet med AI "hallucinationer" og sikrer, at alle oversættelser er officielle navne fra Wikipedia/Wikidata-fællesskabet.

## 🔄 Oversættelses-prioritet

Når pluginet skal oversætte et stednavn, følger det denne intelligente fallback-kæde:

```
1. Wikidata API     → Officiel oversættelse fra Wikipedia-data
   ↓ (hvis ikke fundet)
2. Quick_Translate  → Manuelt kurerede oversættelser (kontinenter, populære lande)
   ↓ (hvis ikke fundet)
3. OpenAI API       → AI-genereret oversættelse (backup)
   ↓ (hvis ikke fundet)
4. Original navn    → Beholder det engelske navn (korrekt for små byer)
```

## ✅ Korrekt dansk praksis

For **små udenlandske byer** uden officiel dansk oversættelse er det **korrekt** at bruge det originale navn:

- ✅ **Aenon Town** (Jamaica) → Forbliver "Aenon Town" (ingen dansk oversættelse findes)
- ✅ **København** → Oversættes korrekt fra "Copenhagen"
- ✅ **Tyskland** → Oversættes korrekt fra "Germany"
- ✅ **Elfenbenskysten** → Oversættes korrekt fra "Ivory Coast"

Dette er 100% korrekt dansk sprogbrug! Man siger ikke "Ænon By" på dansk - man beholder det originale navn.

## 🔧 Teknisk implementering

### Ny klasse: `WTA_Wikidata_Translator`

Placeret i: `includes/helpers/class-wta-wikidata-translator.php`

**Hovedfunktion:**
```php
WTA_Wikidata_Translator::get_label( $wikidata_id, $target_lang = 'da' )
```

**Eksempel:**
```php
// København
$label = WTA_Wikidata_Translator::get_label( 'Q1748', 'da' );
// Returnerer: "København"

// Aenon Town (lille by uden dansk navn)
$label = WTA_Wikidata_Translator::get_label( 'Q18436859', 'da' );
// Returnerer: false (korrekt - ingen dansk oversættelse findes)
```

### Opdateret: `WTA_AI_Translator`

**Ny signatur:**
```php
WTA_AI_Translator::translate( $name, $type, $target_lang = null, $wikidata_id = null )
```

**Ny parameter:** `$wikidata_id` - Wikidata Q-ID (f.eks. "Q1748")

Funktionen prøver nu automatisk Wikidata først, hvis `wikidata_id` er angivet.

### Data-flow

1. **Import fra JSON** (`cities.json` / `countries.json`)
   - JSON indeholder `wikiDataId` felt (f.eks. `"wikiDataId": "Q1748"`)
   
2. **Queue payload**
   - `wikidata_id` inkluderes i payload når land/by tilføjes til køen
   
3. **Translation**
   - `WTA_AI_Translator::translate()` modtager `wikidata_id`
   - Kalder `WTA_Wikidata_Translator::get_label()` først
   
4. **Meta data**
   - `wta_wikidata_id` gemmes som post meta
   - Kan bruges til fremtidig reference/debug

## 💾 Caching

Alle Wikidata-opslag caches i WordPress transients:

- **Succesfulde oversættelser:** 1 år cache
- **Manglende oversættelser:** 30 dage cache (længere fordi de sjældent tilføjes)
- **API-fejl:** 1 dag cache (kortere for at retry)

**Cache-nøgle format:**
```
wta_wikidata_{Q-ID}_{sprog}
```

**Eksempel:**
```
wta_wikidata_Q1748_da → "København"
wta_wikidata_Q18436859_da → "__NOTFOUND__"
```

### Cache-administration

**Ryd specifik oversættelse:**
```php
WTA_Wikidata_Translator::clear_cache( 'Q1748', 'da' );
```

**Ryd hele cache:**
```php
WTA_Wikidata_Translator::clear_cache();
```

**Statistik:**
```php
$stats = WTA_Wikidata_Translator::get_cache_stats();
// Array med: total, found, not_found
```

## 🌐 Wikidata API

**Endpoint:**
```
https://www.wikidata.org/wiki/Special:EntityData/{Q-ID}.json
```

**Eksempel-request:**
```
GET https://www.wikidata.org/wiki/Special:EntityData/Q1748.json
```

**Response-struktur:**
```json
{
  "entities": {
    "Q1748": {
      "labels": {
        "da": {
          "language": "da",
          "value": "København"
        },
        "en": {
          "language": "en",
          "value": "Copenhagen"
        }
      }
    }
  }
}
```

**Rate limiting:**
- Plugin venter 100ms mellem hvert API-kald
- Respekterer Wikidata's fair use policy

## 📊 Logging

Alle Wikidata-opslag logges via `WTA_Logger`:

**Succesfuld oversættelse:**
```
[INFO] Wikidata translation successful
  wikidata_id: Q1748
  label: København
  lang: da
```

**Ingen oversættelse fundet:**
```
[DEBUG] Wikidata: No label in target language
  wikidata_id: Q18436859
  target_lang: da
```

**API-fejl:**
```
[WARNING] Wikidata API request failed
  wikidata_id: Q1748
  error: Connection timeout
```

## 🚀 Fordele

1. **100% præcise oversættelser** - Ingen AI-gætteri
2. **Vedligeholdt af Wikipedia-fællesskabet** - Altid opdateret
3. **Understøtter alle sprog** - Ikke kun dansk
4. **Gratis og open source** - Ingen API-omkostninger
5. **Intelligent fallback** - Fungerer også uden Wikidata-ID

## 🔮 Fremtidige forbedringer

- Batch API-requests for hurtigere import (Wikidata understøtter multiple entities i én request)
- Admin UI til at se/redigere oversættelser
- Automatisk sync af nye Wikidata-opdateringer
- Support for alternative navne (aliases) når hovednavnet ikke findes

## 📝 Version

- **Implementeret i:** v2.11.0
- **Dato:** 2. januar 2025
- **Status:** ✅ Production-ready

---

**Note til udviklere:** Wikidata-integrationen kræver ingen konfiguration. Den virker automatisk når JSON-data indeholder `wikiDataId` felter, og falder elegant tilbage til AI/original navn hvis Wikidata ikke har en oversættelse.


