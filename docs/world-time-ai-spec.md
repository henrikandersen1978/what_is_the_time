# World Time AI – Kravspecifikation

Version: 1.0  
Formål: Dette dokument beskriver alle funktionelle og tekniske krav til WordPress-pluginet **“World Time AI”**.

---

# 1. Overordnet formål

Pluginet skal:

1. Importere **alle kontinenter, lande og byer** i verden via GitHub-databasen:
   - `dr5hn/countries-states-cities-database`.
2. Oprette hierarkiske WordPress-landingssider:
   - Kontinent → Land → By  
     som custom post type.
3. Vise **aktuel lokal tid** for hver by, baseret på korrekt tidszone (IANA).
4. Hente tidszoner til komplekse lande via **TimeZoneDB** API (gratis version, 1 request/sec).
5. Generere indhold, titles og meta descriptions via **OpenAI**, baseret på redigerbare prompts.
6. Køre import, tidszone-opslag og AI-generering via **cron + batches**, så systemet kan håndtere >150.000 sider uden timeouts.
7. Oprette alt indhold på **baselandets sprog** (fx dansk eller amerikansk).

---

# 2. Teknisk miljø

- WordPress 6.8.3+
- PHP 8.4
- LiteSpeed webserver
- Kompatibel med:
  - Yoast SEO
  - ACF Pro (valgfrit – plugin skal virke uden)

Pluginet skal være:
- Performance-optimeret
- Caching-venligt
- Fejltolerant med genoptagelige processer

---

# 3. Datakilder

## 3.1. GitHub-database
Kilde: `dr5hn/countries-states-cities-database`

Brug følgende JSON-filer:
- `countries.json`
- `states.json`
- `cities.json`

Vigtige felter:
- Country:
  - `id`, `name`, `iso2`, `iso3`, `region`, `subregion`, `latitude`, `longitude`
- City:
  - `id`, `name`, `country_id`, `state_id`, `latitude`, `longitude`

## 3.2. TimeZoneDB API
Bruges til byer i **komplekse lande** (f.eks. USA, Canada, Brasilien, Australien, Rusland).

- Kald: `get-time-zone`
- Input: lat, lng
- Output: IANA timezone
- Rate limit: 1 request / sekund

Plugin-indstillinger:
- `timezonedb_api_key`

---

# 4. Baseland & sprog

Pluginet skal have følgende indstillinger:

- `base_country_name` (fx Danmark / USA)
- `base_timezone` (IANA, fx Europe/Copenhagen)
- `base_language` (fx da-DK / en-US)
- `base_language_description`  
  (fx “Skriv på flydende dansk til danske brugere”)

**Alle oprettede sider skal være på dette sprog:**
- Titel
- Slug
- Indhold
- Yoast SEO title/meta

Navne fra databasen (engelske) skal oversættes via OpenAI.

---

# 5. WordPress-datamodel

## 5.1. Custom Post Type
CPT: `world_time_location`

Egenskaber:
- Hierarchical: true
- Public
- URL’er baseret på hierarki:
  - /europa/  
  - /europa/tyskland/  
  - /europa/tyskland/berlin/

## 5.2. Post meta

- `wta_type` (`continent` | `country` | `city`)
- `wta_continent_code`
- `wta_country_code`
- `wta_country_id`
- `wta_city_id`
- `wta_lat`
- `wta_lng`
- `wta_timezone`
- `wta_timezone_status` (`pending` | `resolved` | `fallback`)
- `wta_ai_status` (`pending` | `done` | `error`)
- `wta_name_original`
- `wta_name_local`

---

# 6. Queue-system

Opret en tabel: `wp_world_time_queue`

Felter:
- `id` (int)
- `type` (`continent` | `country` | `city` | `timezone` | `ai_content`)
- `source_id`
- `payload` (json)
- `status` (`pending` | `processing` | `done` | `error`)
- `last_error`
- `created_at`
- `updated_at`

---

# 7. Cron-arkitektur & batching

## 7.1. Cron events (serveres hver 5. minut)

1. `world_time_import_structure`
   - Importerer kontinenter, lande og byer → CPT
   - Batch: 50 elementer
   - Kun oprettelse, ingen AI

2. `world_time_resolve_timezones`
   - For byer i komplekse lande
   - Batch: 5 elementer
   - 200–300 ms sleep mellem API-kald

3. `world_time_generate_ai_content`
   - Genererer indhold, titles, meta
   - Batch: 10 elementer

Alle cron-jobs skal:

- Undgå overlap via lock (transient)
- Respektere max execution time
- Kun processere pending-elementer
- Kun processere byer med resolved timezone

---

# 8. Import-flow

## Trin 1 – Opret kø
Brug admin-siden “Data & Import”.

- Indlæs JSON-filerne
- Opret queue-elementer for:
  - Kontinenter (region/subregion)
  - Lande
  - Byer
- Status: pending

## Trin 2 – Cron: Struktur → CPT
For hver queue-post:
- Opret CPT-post (`world_time_location`)
- Sæt meta
- Parent bestemmes efter hierarki:
  - Kontinent → top-level
  - Lande → child af kontinent
  - Byer → child af land

## Trin 3 – Cron: TimeZoneDB
- Kun komplekse lande
- For hver by:
  - Lav API-kald
  - Gem IANA timezone i `wta_timezone`
  - Sæt `wta_timezone_status = resolved`

## Trin 4 – Cron: AI-indhold
- Brug AI til:
  - Oversættelse af navn
  - Titel (post_title)
  - Indhold (post_content)
  - Yoast title/meta

---

# 9. OpenAI-integration

Admin skal kunne indstille:

- OpenAI API key
- Modelnavn
- Temperatur
- Max tokens

Prompts skal være konfigurerbare (se prompts-dokumentet).

AI-felter:
- post_title
- post_content
- Yoast SEO title
- Yoast meta description
- Oversættelse af navn

AI-status gemmes i `wta_ai_status`.

Fejl logges i queue-tabel.

---

# 10. Admin-interface

Menu: **World Time AI**

Undersider:

1. **Dashboard**
   - Status for import
   - Queue-stats (pending/done/error)
2. **Data & Import**
   - Upload/URL til JSON-filer
   - Valg af kontinenter
   - Måske: min. population / max byer pr. land
   - Knap: “Forbered kø”
3. **AI-indstillinger**
4. **Prompts**
5. **Tidszone & Sprog**
6. **Log/Værktøjer**
   - Se fejl
   - Genskab AI for fejlramte poster

---

# 11. Frontend-visning

## By-side skal vise:

- H1: “Hvad er klokken i {{city_name}}?”
- Lokal tid (server + JS real-time)
- Tidsforskel til `base_country_name`
- Genereret indhold

## Land-side:

- H1
- Genereret tekst
- Liste over byer

## Kontinent-side:

- H1
- Genereret tekst
- Liste over lande

---

# 12. JavaScript for live klokkeslæt

- Server genererer initial lokal tid i IANA timezone
- JS tæller sekunder frem lokalt
- Caching-safe

HTML eksempel:

```html
<div
  class="wta-clock"
  data-timezone="Europe/London"
  data-base-time="2025-11-19T10:23:00"
>
  10:23:00
</div>
