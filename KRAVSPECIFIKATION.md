# Kravspecifikation: World Time AI WordPress Plugin

**Version:** 2.12.0  
**Dato:** 2. januar 2025  
**Projekttype:** WordPress Plugin  
**Målgruppe:** Danske brugere der søger information om lokaltid rundt om i verden

---

## 📋 EXECUTIVE SUMMARY

World Time AI er en WordPress-plugin der viser aktuel lokal tid for 150.000+ byer verden over. Pluginet skaber automatisk hierarkiske sider (Kontinenter → Lande → Byer) med AI-genereret dansk indhold og levende ure. Alle stednavne oversættes korrekt til dansk ved hjælp af Wikidata API og OpenAI, og URL'er er SEO-optimerede på dansk fra starten.

---

## 🎯 FORRETNINGSKRAV

### Primære mål
1. Vise præcis lokal tid for byer verden over med levende ure
2. Generere SEO-optimeret dansk indhold automatisk
3. Skabe hierarkisk navigerbar struktur (Kontinent → Land → By)
4. Sikre alle URL'er er på dansk fra start (ikke engelsk)
5. Håndtere stort datavolumen (150.000+ byer) effektivt

### Brugerscenarier
- **Bruger søger "hvad er klokken i Tokyo"** → Finder side med levende ur og dansk indhold om Tokyo
- **Bruger browser "/europa/danmark/"** → Ser oversigt over danske byer med tidsinfo
- **Administrator importerer nye data** → System behandler automatisk i baggrunden uden at overbelaste serveren

---

## 🏗️ TEKNISK STACK

### Core Requirements
- **WordPress:** 6.8 eller nyere
- **PHP:** 8.4 eller nyere
- **MySQL/MariaDB:** Standard WordPress database

### Eksterne biblioteker (bundlet)
- **Action Scheduler** (WooCommerce) - Baggrundsjob processing
- **Plugin Update Checker** (YahnisElsts) - GitHub-baserede automatiske opdateringer

### API Integrationer
1. **OpenAI API** (GPT-4o Mini)
   - Formål: Generering af dansk indhold og oversættelse
   - Kræver API-nøgle fra https://platform.openai.com/api-keys
   - Omkostning: ~$0.15 pr. 1M tokens (input), ~$0.60 pr. 1M tokens (output)

2. **Wikidata API** (Gratis)
   - Formål: Officielle oversættelser af stednavne til dansk
   - Ingen API-nøgle påkrævet
   - Endpoint: https://www.wikidata.org/wiki/Special:EntityData/{Q-ID}.json
   - Rate limiting: 100ms delay mellem kald

3. **TimeZoneDB API** (Valgfri, men anbefalet)
   - Formål: Præcis tidszonebestemmelse for komplekse lande (USA, Canada, Rusland, Australien)
   - Gratis tier: 1 request/sekund
   - API-nøgle fra https://timezonedb.com/api

### Data kilder
- **Countries/Cities Database** (dr5hn på GitHub)
  - countries.json (~500KB)
  - cities.json (~185MB, 150.000+ entries)
  - Format: JSON med felter som: id, name, country_code, latitude, longitude, population, wikiDataId

---

## 📊 DATAMODEL OG STRUKTUR

### Custom Post Type: `wta_location`

**Konfiguration:**
- Hierarchical: Ja (understøtter parent-child relationer)
- Public: Ja
- Supports: title, editor, author, page-attributes
- Rewrite slug: `/location/` (men overstyret af dansk slug)
- Show in REST: Ja

**Post Meta felter:**

| Meta Key | Type | Beskrivelse | Eksempel |
|----------|------|-------------|----------|
| `wta_type` | string | Type: continent, country, eller city | "city" |
| `wta_timezone` | string | IANA timezone identifier | "Europe/Copenhagen" |
| `wta_latitude` | float | Breddegrad | 55.6761 |
| `wta_longitude` | float | Længdegrad | 12.5683 |
| `wta_country_code` | string | ISO2 landekode | "DK" |
| `wta_country_id` | int | Database ID for land | 58 |
| `wta_state_id` | int | Database ID for stat/region | 1528 |
| `wta_population` | int | Indbyggertal | 1346485 |
| `wta_original_name` | string | Originalt engelsk navn | "Copenhagen" |
| `wta_wikidata_id` | string | Wikidata Q-ID | "Q1748" |
| `wta_ai_generated` | bool | Om indhold er AI-genereret | true |
| `_yoast_wpseo_title` | string | SEO titel (Yoast integration) | "Hvad er klokken i København? ⏰" |
| `_yoast_wpseo_metadesc` | string | SEO meta beskrivelse | "Se den aktuelle tid i København..." |
| `_pilanto_page_h1` | string | Custom H1 overskrift | "Hvad er klokken i København lige nu?" |

### Hierarkisk struktur

```
Kontinent (wta_type = 'continent')
├── Land 1 (wta_type = 'country', post_parent = Kontinent ID)
│   ├── By 1.1 (wta_type = 'city', post_parent = Land 1 ID)
│   ├── By 1.2
│   └── By 1.3
├── Land 2
│   ├── By 2.1
│   └── By 2.2
```

**Eksempel URL-struktur:**
- `/europa/` (kontinent)
- `/europa/danmark/` (land)
- `/europa/danmark/kobenhavn/` (by med levende ur)

---

## 🔄 IMPORT OG PROCESSING WORKFLOW

### Import Pipeline (3 faser)

#### **Fase 1: Structure Processor**
**Ansvar:** Opretter posts for kontinenter, lande og byer

**Process:**
1. Hent data fra `countries.json` og `cities.json`
2. Oversæt stednavne via Wikidata API → Static Quick_Translate → OpenAI API → Original navn
3. Opret WordPress post med:
   - Dansk titel og slug (fra oversættelse)
   - Status: 'draft'
   - Post type: 'wta_location'
   - Parent: korrekt hierarkisk relation
4. Gem alle meta-felter
5. Tilføj til næste køfase (timezone resolution)

**Job identifier:** `wta_process_structure`  
**Recurrence:** Hvert 5. minut  
**Batch size:** 10 items per run

---

#### **Fase 2: Timezone Processor**
**Ansvar:** Bestemmer præcis timezone for hver by

**Process:**
1. **Simpel landeregler:**
   - Lande med kun 1 timezone: Brug landets timezone direkte
   - Eksempler: Danmark → Europe/Copenhagen, Japan → Asia/Tokyo
   
2. **Komplekse lande (USA, Canada, Rusland, Brasilien, Australien, etc.):**
   - Kald TimeZoneDB API med lat/lng
   - Endpoint: `http://api.timezonedb.com/v2.1/get-time-zone?key={API_KEY}&format=json&by=position&lat={LAT}&lng={LNG}`
   - Parse response og gem `zoneName` (f.eks. "America/New_York")
   - Respekter rate limit: 1 request/sekund (sleep 1000ms mellem kald)

3. Gem timezone i `wta_timezone` meta
4. Tilføj til næste køfase (AI content generation)

**Job identifier:** `wta_process_timezone`  
**Recurrence:** Hvert 5. minut  
**Batch size:** 5 items per run (pga. API rate limiting)

---

#### **Fase 3: AI Content Processor**
**Ansvar:** Genererer dansk indhold og SEO metadata

**Indholdstyper:**

**3A. Kontinent-sider (5 separate AI prompts):**
1. Introduktion (200-300 ord)
2. Tidszoner i [kontinent]
3. Større byer i [kontinent]
4. Geografi & klima
5. Interessante fakta

Efter sektion 3 indsættes automatisk shortcode: `[wta_major_cities count="12"]`

**3B. Lande-sider (6 separate AI prompts):**
1. Introduktion
2. Tidszoner oversigt
3. Større byer
4. Vejr & klima
5. Kultur & tid
6. Rejseinformation

**3C. By-sider (1 AI prompt):**
- Genereres som 1 samlet tekst (400-600 ord)
- Indeholder: historie, kultur, klima, seværdigheder, rejsetips
- Levende ur tilføjes automatisk via template

**AI Processing:**
- Model: gpt-4o-mini
- Temperature: 0.7
- Max tokens: Defineret per prompt type
- Variabler i prompts: `{location_name}`, `{location_name_local}`, `{timezone}`, `{country_name}`, `{continent_name}`, `{num_countries}`, `{country_list}`

**SEO metadata (genereres via separat AI prompt):**
- Yoast SEO titel (max 60 tegn)
- Meta beskrivelse (max 155 tegn)
- Custom H1 overskrift

**Når færdig:**
- Opdater post_status til 'publish'
- Gem alle meta felter
- Trigger regeneration af parent-location content hvis nødvendigt

**Job identifier:** `wta_process_ai_content`  
**Recurrence:** Hvert 5. minut  
**Batch size:** 3 items per run (OpenAI har højere rate limit)

---

### Queue System

**Database tabel:** `{wp_prefix}world_time_queue`

**Kolonner:**
```sql
CREATE TABLE {prefix}world_time_queue (
    id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,           -- 'structure', 'timezone', 'ai_content'
    status VARCHAR(20) NOT NULL,         -- 'pending', 'processing', 'completed', 'failed'
    priority INT DEFAULT 0,              -- Højere tal = højere prioritet
    data LONGTEXT,                       -- JSON payload med alle nødvendige data
    error_message TEXT,                  -- Fejlbesked hvis status = 'failed'
    attempts INT DEFAULT 0,              -- Antal forsøg
    created_at DATETIME,
    updated_at DATETIME,
    INDEX idx_status_priority (status, priority),
    INDEX idx_type (type)
);
```

**JSON payload eksempel (structure):**
```json
{
  "location_type": "city",
  "name": "Copenhagen",
  "name_local": "København",
  "country_code": "DK",
  "country_id": 58,
  "state_id": 1528,
  "latitude": 55.6761,
  "longitude": 12.5683,
  "population": 1346485,
  "wikidata_id": "Q1748",
  "parent_post_id": 123
}
```

---

## 🎨 FRONTEND FUNKTIONALITET

### Templates

**Single Location Template:**
- Path: `includes/frontend/templates/single-wta_location.php`
- Override i theme: `{theme}/world-time-ai/single-wta_location.php`

**Template indhold:**
- H1 titel (fra `_pilanto_page_h1` custom field)
- Levende ur (for by-sider)
- AI-genereret indhold (fra post_content)
- Breadcrumb navigation (Kontinent → Land → By)
- Liste over child locations (hvis applicable)

---

### Shortcodes

#### `[wta_clock]`
Viser levende ur for nuværende location (kun bysider).

**Output:**
```html
<div class="wta-clock-container">
  <div class="wta-clock" data-timezone="Europe/Copenhagen">
    <div class="wta-time">14:32:15</div>
    <div class="wta-date">Tirsdag, 2. januar 2025</div>
  </div>
</div>
```

**JavaScript:** Opdaterer hvert sekund via `clock.js`

---

#### `[wta_child_locations]`
Viser grid af child locations (lande under kontinent, byer under land).

**Attributter:** Ingen

**Output:**
```html
<div class="wta-locations-section">
  <h2>Oversigt over lande i Europa</h2>
  <p>Europa indeholder 44 lande med [X] forskellige tidszoner.</p>
  <div class="wta-locations-grid">
    <a href="/europa/danmark/" class="wta-location-card">Danmark</a>
    <a href="/europa/tyskland/" class="wta-location-card">Tyskland</a>
    ...
  </div>
</div>
```

---

#### `[wta_major_cities count="12"]`
Viser levende ure for største byer i nuværende kontinent/land.

**Attributter:**
- `count` (int, default: 12) - Antal byer at vise

**Logic:**
- Finder child cities sorteret efter population
- Viser kun byer med `wta_population` > 0
- Adaptiv: Hvis på kontinent-side → vis byer fra hele kontinentet; hvis på land-side → vis kun byer fra landet

**Output:**
```html
<div class="wta-city-times-grid">
  <div class="wta-live-city-clock" data-timezone="Europe/Copenhagen" data-city-name="København">
    <div class="city-name">København</div>
    <div class="city-time">14:32:15</div>
  </div>
  ...
</div>
```

---

#### `[wta_city_time city="London"]`
Viser inline tid for specifik by.

**Attributter:**
- `city` (string, required) - Bynavn (post_title eller original_name)

**Output:**
```html
<span class="wta-inline-city-time" data-timezone="Europe/London">
  14:32 i London
</span>
```

---

### Frontend Assets

**CSS:** `includes/frontend/assets/frontend.css`
- Clock styling
- Grid layouts
- Responsive breakpoints
- Theme-agnostic design

**JavaScript:** `includes/frontend/assets/clock.js`
- Initialiserer alle `.wta-clock`, `.wta-live-city-clock`, `.wta-inline-city-time` elementer
- Opdaterer tid hvert sekund via Intl.DateTimeFormat API
- Håndterer timezone konvertering i browser

---

## ⚙️ ADMIN FUNKTIONALITET

### Admin Menu Struktur

```
World Time AI
├── Dashboard               (Oversigt: statistik, køstatus, seneste logs)
├── All Locations          (Standard WordPress post liste)
├── Add New Location       (Manuel oprettelse - sjældent brugt)
├── Settings
│   ├── AI Settings        (OpenAI API key, model, temperature)
│   ├── Timezone & Language (TimeZoneDB API key, målsprog)
│   └── Prompts            (9 redigerbare AI prompts)
├── Data & Import          (Import interface)
└── Tools
    ├── Scheduled Actions  (Action Scheduler UI - bundlet)
    ├── Load Recent Logs   (WTA_Logger output)
    └── System Information (PHP version, memory, dependencies check)
```

---

### Admin Pages

#### **Dashboard (`admin/views/dashboard.php`)**

**Widgets:**
1. **Queue Statistics**
   - Pending: [X] items
   - Processing: [X] items
   - Completed: [X] items
   - Failed: [X] items

2. **Content Overview**
   - Kontinenter: [X] published
   - Lande: [X] published
   - Byer: [X] published

3. **Action Scheduler Status**
   - Last run: [timestamp]
   - Next run: [timestamp]
   - Status: Active/Paused

4. **Recent Logs** (seneste 20 linjer)

---

#### **AI Settings (`admin/views/settings-ai.php`)**

**Felter:**
- **OpenAI API Key** (text input, required)
  - Validering: Test connection button
- **AI Model** (dropdown)
  - Valgmuligheder: gpt-4o-mini, gpt-4o, gpt-3.5-turbo
  - Default: gpt-4o-mini
- **Temperature** (range slider 0.0-1.0)
  - Default: 0.7

---

#### **Timezone & Language Settings**

**Felter:**
- **TimeZoneDB API Key** (text input, optional)
- **Target Language** (dropdown)
  - Default: Danish (da)
  - Support for: en, sv, no, de, fr, es (fremtidig expansion)

---

#### **Prompts Management (`admin/views/prompts.php`)**

**Redigerbare prompt-kategorier:**

1. **Translation Prompts**
   - Continent translation
   - Country translation
   - City translation

2. **Continent Page Template** (5 prompts)
   - Section 1: Introduction
   - Section 2: Timezones
   - Section 3: Major Cities
   - Section 4: Geography & Climate
   - Section 5: Interesting Facts

3. **Country Page Template** (6 prompts)
   - Section 1-6 (se AI Processor section)

4. **City Page Template** (1 prompt)
   - Content generation

5. **SEO Metadata Prompts**
   - SEO title generation
   - Meta description generation
   - H1 generation

**Hver prompt har:**
- System prompt (textarea)
- User prompt (textarea)
- Availabile variables (info box)
- Reset to default button

---

#### **Data & Import (`admin/views/import.php`)**

**Import modes:**

**Mode 1: Continent-based import**
- Checkboxes for kontinenter: Afrika, Asien, Europa, Nordamerika, Oceanien, Sydamerika
- Population filter: Dropdown (No filter, >50K, >100K, >500K, >1M, >5M)
- Max cities per country: Number input (optional test-begrænsning)

**Mode 2: Quick Test Mode**
- Single country selector (organized by continent)
- Nyttigt til at teste ét land ad gangen

**Actions:**
- **Prepare Import Queue** button
  - AJAX request til `admin-ajax.php?action=wta_prepare_import`
  - Læser JSON filer
  - Fylder queue med filtered entries
  - Progress bar during processing
  - Success message med antal items queued

---

### AJAX Endpoints

| Action Hook | Function | Beskrivelse |
|-------------|----------|-------------|
| `wp_ajax_wta_prepare_import` | `WTA_Admin::ajax_prepare_import()` | Forbereder import queue |
| `wp_ajax_wta_test_openai_connection` | `WTA_Admin::ajax_test_openai()` | Tester OpenAI API key |
| `wp_ajax_wta_clear_queue` | `WTA_Queue::ajax_clear_queue()` | Rydder hele køen |
| `wp_ajax_wta_regenerate_content` | `WTA_Admin::ajax_regenerate_content()` | Regenererer AI indhold for specifik post |

---

## 🔐 SIKKERHED OG VALIDERING

### API Key Storage
- Gemmes i `wp_options` tabel
- Aldrig eksponeret i frontend
- Admin-only adgang
- Nonces på alle AJAX requests

### Input Sanitization
- Alle bruger-inputs sanitized via `sanitize_text_field()`, `wp_kses_post()`
- API responses valideret før brug

### Error Handling
- Try-catch blocks i alle kritiske funktioner
- Logging af fejl via `WTA_Logger`
- Graceful degradation hvis API fejler

---

## 📈 PERFORMANCE OG SKALERBARHED

### Optimeringer
- **Caching:**
  - Wikidata translations: 1 år (success), 30 dage (not found)
  - Timezone lookups: Permanent (gemt i post meta)
  - AI content: Permanent (gemt i post_content)

- **Batch Processing:**
  - Små batches (3-10 items) for ikke at overbelaste server
  - Action Scheduler håndterer automatisk retry ved fejl

- **Database:**
  - Indexering på queue tabel (status, priority, type)
  - Post meta queries optimeret med meta_query

### Estimeret Processing Tid

| Dataset | Antal byer | Estimeret tid |
|---------|------------|---------------|
| Megabyer (>5M) | ~100 | 15-30 min |
| Større byer (>500K) | ~2,000 | 1-3 timer |
| Alle byer (ingen filter) | ~150,000 | 1-3 dage |

---

## 🧪 TESTSCENARIER

### Installation Test
1. Installer plugin på frisk WordPress installation
2. Verificer Action Scheduler er loaded
3. Verificer custom post type registreret
4. Verificer queue tabel oprettet

### Import Test
1. Konfigurer OpenAI API key
2. Vælg 1 kontinent (f.eks. Oceanien - mindst data)
3. Sæt population filter til >1M
4. Start import
5. Verificer:
   - Queue fyldes korrekt
   - Posts oprettes med draft status
   - Stednavne oversættes til dansk
   - Timezones beregnes korrekt
   - AI content genereres
   - Posts publiceres automatisk

### Frontend Test
1. Besøg kontinent-side → Verificer child locations vises
2. Besøg land-side → Verificer child cities vises
3. Besøg by-side → Verificer levende ur fungerer
4. Test shortcodes i WordPress editor
5. Verificer breadcrumbs navigation
6. Verificer responsive design på mobil

### Performance Test
- Import af 100 byer → Måle tidsforløb
- Server CPU/memory under import → Max 80% belastning
- Frontend page load → <2 sekunder
- JavaScript ur opdatering → Smooth, ingen lag

---

## 🚀 DEPLOYMENT OG RELEASE

### Pre-deployment Checklist
- [ ] Alle tests bestået
- [ ] Version number opdateret i `time-zone-clock.php`
- [ ] CHANGELOG.md opdateret
- [ ] README.md opdateret
- [ ] Commit og push til GitHub

### Release Process
```bash
# 1. Tag version
git tag -a v2.12.0 -m "Release version 2.12.0"

# 2. Push tag
git push origin main
git push origin v2.12.0

# 3. Create GitHub Release
# - Go to GitHub → Releases → New Release
# - Select tag v2.12.0
# - Upload built plugin zip file
# - Write release notes
# - Publish release

# 4. Plugin Update Checker will auto-detect new release
```

### Build Script
Brug inkluderet PowerShell script:
```powershell
.\build-release.ps1
```

Dette script:
1. Kopierer plugin filer til `/build/time-zone-clock/`
2. Ekskluderer unødvendige filer (.git, docs, tests)
3. Zipper pakke til `/releases/time-zone-clock-{version}.zip`

---

## 📞 SUPPORT OG VEDLIGEHOLDELSE

### Logging System
**WTA_Logger** gemmer alle vigtige hændelser:
- Placering: `wp-content/uploads/world-time-ai-logs/wta-{date}.log`
- Format: `[TIMESTAMP] [LEVEL] Message + Context`
- Levels: DEBUG, INFO, WARNING, ERROR
- Rotation: Nye log-fil dagligt
- Retention: 30 dage (automatisk cleanup)

### Debugging Tools
1. **Admin Dashboard** → Kø statistik og fejl-oversigt
2. **Tools → Load Recent Logs** → Seneste log-linjer
3. **Tools → System Information** → Environment check
4. **Tools → Scheduled Actions** → Action Scheduler UI

### Common Issues og Løsninger

| Problem | Årsag | Løsning |
|---------|-------|---------|
| Import kører ikke | Action Scheduler ikke loaded | Verificer `/includes/action-scheduler/` folder eksisterer |
| AI content genereres ikke | Ugyldig OpenAI API key | Test key i AI Settings, check logs |
| Timezone ikke bestemt | TimeZoneDB API fejler | Check API key, fallback til land-default timezone |
| Posts har engelske URLs | Oversættelse fejlet før post creation | Check Wikidata/OpenAI status, re-queue item |
| Levende ur ikke opdaterer | JavaScript fejl | Check browser console, verificer timezone data format |

---

## 🔮 FREMTIDIGE UDVIDELSER

### Phase 2 Features (roadmap)
- Multi-language support (svensk, norsk, engelsk)
- Bulk regenerate content for alle posts
- Manual timezone override i admin
- Country flag icons integration
- Weather data integration (OpenWeatherMap API)
- World clock widget til Elementor/Gutenberg
- REST API endpoint til eksterne apps

### Performance Improvements
- Wikidata batch API requests (multiple entities per call)
- Redis caching layer for high-traffic sites
- CDN integration for static assets
- Lazy-loading af levende ure (kun visible clocks opdateres)

---

## 📚 TEKNISK DOKUMENTATION REFERENCES

### Intern dokumentation
- `build/time-zone-clock/README.md` - Plugin oversigt
- `build/time-zone-clock/SETUP-INSTRUCTIONS.md` - Installation guide
- `CHANGELOG.md` - Version historik
- `WIKIDATA-INTEGRATION.md` - Wikidata API dokumentation
- `THEME-INTEGRATION.md` - Guide til theme-udviklere

### Ekstern dokumentation
- Action Scheduler: https://actionscheduler.org/
- Plugin Update Checker: https://github.com/YahnisElsts/plugin-update-checker
- OpenAI API: https://platform.openai.com/docs
- Wikidata API: https://www.wikidata.org/wiki/Wikidata:Data_access
- TimeZoneDB API: https://timezonedb.com/api

---

## 📄 LICENSIERING

### Plugin License
- **Type:** Proprietary
- **Copyright:** 2025 Henrik Andersen

### Bundled Libraries
- **Action Scheduler:** GPL-3.0 (Automattic/WooCommerce)
- **Plugin Update Checker:** MIT (YahnisElsts)

### Data Sources
- **Countries/Cities Database:** (dr5hn) - Open Data License
- **Wikidata:** CC0 1.0 Universal (Public Domain)

---

## 👥 KONTAKTINFORMATION

**Udvikler:** Henrik Andersen  
**GitHub Repository:** https://github.com/henrikandersen1978/what_is_the_time  
**WordPress Plugin:** World Time AI  
**Support:** Via GitHub Issues

---

## 🎓 ONBOARDING FOR NYE UDVIKLERE

### Anbefalet læserækkefølge
1. Denne kravspecifikation (KRAVSPECIFIKATION.md) - Start her!
2. build/time-zone-clock/README.md - Feature oversigt
3. build/time-zone-clock/SETUP-INSTRUCTIONS.md - Installation
4. CHANGELOG.md - Hvad er nyt i seneste versioner
5. Kildekode: Start i `time-zone-clock.php` og følg require chain

### Kodestruktur quick-start
```
time-zone-clock.php          → Entry point, plugin registration
├── includes/
│   ├── class-wta-core.php           → Core plugin class, dependency loading
│   ├── core/                         → Business logic (post type, queue, importer)
│   ├── scheduler/                    → Action Scheduler processors (3 phases)
│   ├── admin/                        → Admin UI (settings, import interface)
│   ├── frontend/                     → Frontend templates og shortcodes
│   └── helpers/                      → Utility classes (logger, translator, timezone)
```

### Development workflow
1. Setup lokal WordPress installation (LocalWP anbefales)
2. Clone repository til `/wp-content/plugins/`
3. Download Action Scheduler og Plugin Update Checker biblioteker
4. Aktiver plugin
5. Konfigurer test API keys
6. Importer test-datasæt (Quick Test Mode: 1 land)
7. Modifier kode og test

### Debug tips
- Enable WordPress debug mode: `define('WP_DEBUG', true);` i `wp-config.php`
- Check logs: `wp-content/uploads/world-time-ai-logs/`
- Monitor queue: Admin → Dashboard widget
- Action Scheduler status: Tools → Scheduled Actions
- Database inspektion: phpMyAdmin eller TablePlus

---

## ✅ ACCEPTANCE CRITERIA

Pluginet er production-ready når:
- [x] Alle 3 import-faser fungerer fejlfrit
- [x] Wikidata oversættelser fungerer med fallback til AI
- [x] Posts oprettes med dansk titel og slug fra start
- [x] Levende ure opdaterer korrekt hvert sekund
- [x] Admin interface er intuitivt og fejlbeskedgivende
- [x] Performance håndterer 150K+ byer uden server-crash
- [x] Logs giver tilstrækkelig debug-information
- [x] Automatiske opdateringer fungerer fra GitHub
- [x] Dokumentation er komplet og klar

**Current Status:** ✅ Alle kriterier opfyldt i v2.12.0

---

**Dokumentversion:** 1.0  
**Sidst opdateret:** 2. januar 2025  
**Forfatter:** Henrik Andersen / World Time AI Development Team

---

## 🤖 AI / AUTOMATION INTEGRATION NOTES

### For make.com eller lignende værktøjer:

**Data endpoints:**
- GitHub JSON data: https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master/json/cities.json
- Wikidata API: https://www.wikidata.org/wiki/Special:EntityData/{Q-ID}.json
- OpenAI API: https://api.openai.com/v1/chat/completions

**Integration flow:**
1. Fetch city data fra GitHub
2. For hver by: Oversæt navn via Wikidata eller OpenAI
3. Opret WordPress post via WordPress REST API
4. Gem meta-felter (timezone, lat/lng, population, etc.)
5. Trigger AI content generation job

**WordPress REST API endpoints (kræver authentication):**
```
POST /wp-json/wp/v2/wta_location
GET /wp-json/wp/v2/wta_location/{id}
PUT /wp-json/wp/v2/wta_location/{id}
DELETE /wp-json/wp/v2/wta_location/{id}
```

**Custom REST endpoints (kan implementeres):**
```
POST /wp-json/wta/v1/import              → Trigger import job
GET /wp-json/wta/v1/queue/status         → Queue statistics
POST /wp-json/wta/v1/translate           → Translation service
GET /wp-json/wta/v1/locations/search     → Search locations
```

---

**END OF SPECIFICATION**






















































