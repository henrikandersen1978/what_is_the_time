# Changelog

All notable changes to World Time AI will be documented in this file.

## [2.28.5] - 2025-12-05

### Fixed
- **CRITICAL: Disabled WordPress canonical redirects that broke clean URLs**
- WordPress was redirecting `/europa/danmark/` → `/location/europa/danmark/`
- Added `redirect_canonical` filter to prevent WordPress "fixing" our clean URLs
- Added `do_redirect_guess_404_permalink` filter to prevent WordPress guessing wrong URLs
- Clean URLs now work WITHOUT redirects

### Root Cause Discovered
**The Real Problem:**
1. ✅ Our rewrite rules worked correctly
2. ✅ Our permalink filters removed `/wta_location/` correctly
3. ❌ BUT WordPress' **canonical redirect** ran and "corrected" clean URLs
4. ❌ WordPress thought `/europa/danmark/` was "wrong" and redirected to `/location/europa/danmark/`

**Why this happened:**
- WordPress has built-in "helpful" redirect logic
- It tries to fix "incorrect" URLs by redirecting to what it thinks is correct
- Since post type is `wta_location`, WordPress assumed URLs MUST include that prefix
- Our clean URLs triggered WordPress' 404 guess redirect

**The Solution:**
- Disable `redirect_canonical` for location posts
- Disable `do_redirect_guess_404_permalink` for 1-3 level paths without 'location'
- Now WordPress accepts our clean URLs without "helping"

### What Now Works
✅ `/europa/` - No redirect, displays correctly
✅ `/europa/danmark/` - No redirect, displays correctly  
✅ `/europa/danmark/aalborg/` - No redirect, displays correctly
✅ get_permalink() returns clean URLs
✅ Internal links use clean URLs
✅ Schema markup uses clean URLs
✅ Yoast SEO data uses clean URLs
✅ Breadcrumbs use clean URLs

### Testing Instructions
1. Upload plugin v2.28.5
2. Flush permalinks (Settings → Permalinks → Save)
3. Clear ALL caches (object cache, browser, CDN)
4. Visit `/europa/danmark/` directly
5. Check browser URL bar - should stay at `/europa/danmark/` (NO redirect!)
6. View page source - all URLs should be clean
7. Check Yoast canonical and og:url tags

## [2.28.4] - 2025-12-05

### Fixed
- **CRITICAL: Aggressive permalink cache busting to fix internal links**
- Filter priority changed from 10 to 1 (runs BEFORE WordPress caches permalinks)
- Added filters to `post_link` and `page_link` in addition to `post_type_link`
- Automatic wp_cache_flush() on init to force permalink regeneration
- Clear permalink cache when individual posts are saved
- Clear Yoast SEO sitemap cache to force regeneration

### Technical Details
**Root cause:**
- WordPress caches permalinks after generating them
- Our filter ran at priority 10, but permalinks were already cached
- get_permalink() returned cached URLs with `/wta_location/` prefix
- This affected: internal links, breadcrumbs, schema markup, Yoast SEO data

**Solution implemented:**
1. **Early filter priority**: Changed from priority 10 to 1
   - Runs BEFORE WordPress internal permalink caching
2. **Multiple filter hooks**: 
   - `post_type_link` (our posts)
   - `post_link` (general posts)  
   - `page_link` (pages - just in case)
3. **Aggressive cache clearing**:
   - `wp_cache_flush()` on every init (priority 999)
   - `clean_post_cache()` when posts are saved
   - Delete Yoast SEO sitemap cache
4. **Handle both post objects and IDs**: Filter now works with both

### What should now work
✅ get_permalink() returns clean URLs immediately
✅ Internal links use clean URLs
✅ Breadcrumbs use clean URLs
✅ Schema @id fields use clean URLs
✅ Yoast SEO canonical/og:url use clean URLs
✅ Sitemap XML uses clean URLs

### Important
- **FLUSH PERMALINKS REQUIRED** after update
- **CLEAR ALL CACHES** (object cache, browser cache, CDN)
- May need to resave posts to regenerate their permalinks
- Performance impact: wp_cache_flush() on init is aggressive but necessary

## [2.28.3] - 2025-12-05

### Fixed
- **CRITICAL: URL filters now properly registered and working everywhere**
- Permalink filter (`post_type_link`) now removes `/wta_location/` from ALL locations
- Fixed filter registration - now using loader system instead of direct hooks
- Removed post_status restriction - filter now works for all post statuses (draft, publish, etc.)
- Internal links, breadcrumbs, and schema markup now ALL use clean URLs

### Technical Details
**Root cause identified:**
- Filters were added in constructor with direct `add_filter()` calls
- But `$post_type` instance wasn't persisted, so callbacks failed
- Additionally, `'publish' !== $post->post_status` check was too restrictive

**Solution:**
- Moved filter registration to `class-wta-core.php` using loader system
- Filters now properly registered: `$this->loader->add_filter('post_type_link', ...)`
- Removed post_status check - now works for all posts regardless of status
- Instance properly maintained through WordPress hooks system

**What now works:**
✅ `get_permalink()` returns clean URLs everywhere
✅ Breadcrumbs use clean URLs
✅ Internal links in shortcodes use clean URLs
✅ Schema.org @id fields use clean URLs
✅ All navigation uses clean URLs

### Important
- Still requires permalink flush after update (Settings → Permalinks → Save)
- All previous warnings about URL conflicts still apply

## [2.28.2] - 2025-12-05

### Fixed
- **CRITICAL: Completely removed post type prefix from URLs**
- URLs now work correctly: `/europa/danmark/` instead of `/wta_location/europa/danmark/`
- Added custom rewrite rules for hierarchical URLs without post type prefix
- Added permalink filters to remove `wta_location` slug from generated URLs
- Added URL parser to correctly match clean URLs to location posts

### Technical
- `post_type_link` filter removes post type slug from permalinks
- `pre_get_posts` filter parses clean URLs and sets correct query vars
- Custom rewrite rules handle 1, 2, and 3-level hierarchies
- Rules added at 'top' priority to catch before WordPress defaults

### Important Warnings
- **CRITICAL: You MUST flush permalinks after this update!**
  - Go to Settings → Permalinks → Click "Save Changes"
- **POTENTIAL CONFLICTS**: Clean URLs can collide with WordPress pages
  - If you have a page named "europa", "danmark", etc., it will conflict
  - Location URLs take priority due to 'top' rewrite rule priority
  - Consider deleting or renaming conflicting pages before import
- **BREAKING CHANGE**: All existing URLs will change
  - Old: `/wta_location/europa/danmark/`
  - New: `/europa/danmark/`
  - OK on test site, but plan redirects for production

### Testing Required
- Test all location pages after permalink flush
- Verify breadcrumbs still work
- Check internal links
- Test schema markup URLs

## [2.28.1] - 2025-12-05

### Fixed
- **CRITICAL: AI Placeholder Protection** - AI vil ikke længere opfinde sine egne placeholders
- Opdateret ALLE AI system-prompts med sikkerhed mod placeholders som `[by-navn]`, `[navn]`, `[location]`, `[land]`, `[sted]`
- AI instrueres nu eksplicit om at ALTID bruge faktiske stednavne direkte i teksten
- Forhindrer problemer som "hvad er klokken i [by-navn]" i genereret indhold

### Technical
- Tilføjet til alle 16 system prompts: "KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location], [land] etc. Brug ALTID de faktiske stednavne direkte."
- Dækker continent prompts (5 sections), country prompts (6 sections), city prompts (6 sections)
- Eksisterende god prompt-struktur bevaret - kun tilføjet sikkerhedsinstruktion

### Important
- Eksisterende AI-genereret content kan stadig indeholde gamle placeholders
- Ved ny import vil alt content blive genereret med de opdaterede prompts
- Anbefalinger: Reset content og re-importer for at få rent content

## [2.28.0] - 2025-12-05

### Added
- **NEW SHORTCODE: `[wta_continents_overview]`** - Beautiful homepage continent/country navigation
  - Displays all continents in responsive grid layout
  - Shows top N countries per continent (default: 5, configurable via `countries_per_continent` parameter)
  - Includes continent emoji indicators (🇪🇺 Europa, 🌍 Afrika, 🌏 Asien, 🌎 Americas, 🌊 Oceanien)
  - Schema.org ItemList markup for SEO
  - Hover effects and modern card design
  - Usage: `[wta_continents_overview countries_per_continent="5"]`

### Changed
- **CLEANER URL STRUCTURE**: Removed "location" prefix from all location URLs
  - Before: `/location/afrika/sydafrika/benoni/`
  - After: `/afrika/sydafrika/benoni/`
  - Makes URLs shorter, cleaner, and more SEO-friendly
  - All internal links, breadcrumbs, and schema markup automatically updated
  - **IMPORTANT**: Requires permalink flush - Go to Settings → Permalinks and click "Save Changes"

### Technical
- Modified `WTA_Post_Type::register_post_type()` - rewrite slug changed from 'location' to empty string
- Added new `continents_overview_shortcode()` method in `WTA_Shortcodes` class
- Added CSS styling for continent overview cards with responsive grid layout
- All links use `get_permalink()` so URL changes propagate automatically throughout plugin

### Important Notes
- This is a breaking change for URLs if plugin was already deployed to production
- For new installations (like current test site), this is the perfect time to implement
- Better to do this before full production rollout to avoid SEO impact

## [2.27.12] - 2025-12-05

### Fixed
- **CRITICAL: Clear Translation Cache now clears BOTH AI and Wikidata caches**
- Previously "Clear Translation Cache" button only cleared AI translations (`wta_trans_`)
- Now also clears Wikidata translations (`wta_wikidata_`) automatically
- This was the root cause of "Kommune" persisting after v2.27.11 - old cached Wikidata translations
- **Enhanced Wikidata Debugging**: Added raw label logging to track what Wikidata API actually returns

### Technical Details
- `WTA_AI_Translator::clear_cache()` now also calls `WTA_Wikidata_Translator::clear_cache()`
- Added debug logging at the start of Wikidata label processing
- Shows raw label before any suffix removal, making debugging easier

### Important
- **ACTION REQUIRED**: Click "Clear Translation Cache" button in Tools before next import
- This will clear old cached "Kommune" translations from before v2.27.11
- Then the new LAG 2 cleanup code will run on fresh Wikidata API responses

## [2.27.11] - 2025-12-05

### Fixed
- **2-LAYER "KOMMUNE" REMOVAL SYSTEM**: Comprehensive fix to prevent administrative terms in city names
- **Layer 1 (Import Filter)**: Cities with admin terms in source name (e.g., "Oslo kommune") are now filtered OUT at import
  - Prevents duplicates where both "Oslo" and "Oslo kommune" exist in cities.json
  - Filters 17+ admin terms globally (kommune, kommun, municipality, commune, etc.)
  - Runs BEFORE translation, checks raw source data
- **Layer 2 (Translation Cleanup)**: Enhanced Wikidata suffix removal now finds and removes admin terms ANYWHERE in string
  - No longer limited to suffix-only removal
  - Handles "Kommune Oslo", "Oslo Kommune", "Oslo  kommune" (extra spaces), etc.
  - Fully case-insensitive with proper Unicode handling
  - Multi-pass removal (up to 3 iterations for compound terms)
  - Covers 40+ administrative terms in all languages
- **Result**: Future imports will have clean city names without administrative designations
- **Important**: Does NOT modify existing posts - only affects new imports going forward

### Technical Details
- Import filter added in `class-wta-structure-processor.php` after population filter
- Translation cleanup enhanced in `class-wta-wikidata-translator.php` with position-independent term removal
- Uses `mb_strpos()` for finding terms anywhere in string (not just end)
- Sorts terms by length (longest first) to prevent partial match issues
- Preserves original case of city name while removing admin terms

## [2.27.10] - 2025-12-04

### Fixed
- **REVERTED: Population Filter**: Cities with null population are now correctly EXCLUDED when min_population is set
- Only cities with known population >= threshold are imported (as intended)
- Fixed issue where 656 cities with unknown population were included in import
- **Enhanced Debugging**: Added comprehensive logging for Wikidata suffix removal
- Now logs when administrative terms (kommune, kommun, municipality, etc.) remain after cleaning
- Helps identify which cities need manual translation overrides

### Technical Details
- Population filter reverted to original behavior: `null` population = exclude when filtering
- Added multi-term detection in Wikidata translator for 6 common administrative terms
- Enhanced warning logs pinpoint exact cities where suffix removal failed

## [2.27.9] - 2025-12-04

### Fixed
- **CRITICAL: Population Filter Fixed**: Cities with null/missing population are now INCLUDED (not excluded)
- Previously 656 cities were incorrectly filtered out due to missing population data
- Now imports all cities over threshold PLUS cities with unknown population
- **Enhanced Wikidata Suffix Removal**: Expanded to 40+ administrative suffixes across all languages
- Multi-pass suffix removal (removes up to 3 compound suffixes like "City Municipality District")
- Added Nordic languages (Swedish "kommun", Finnish "kunta", Icelandic "kommuna")
- Added Eastern European (Polish "gmina", Russian "oblast", Ukrainian variants)
- Added Asian languages (Japanese "shi", Korean "gun", Arabic "governorate")
- All Wikidata city names now properly cleaned without administrative designations

### Technical Details
- Population filter logic inverted: only exclude if population explicitly set AND below threshold
- Suffix removal now iterates up to 3 times to handle compound administrative names
- Expanded suffix dictionary from 15 to 40+ entries covering global administrative terms

## [2.27.8] - 2025-12-04

### Fixed
- **CRITICAL: JSON Parsing Fixed**: Replaced manual brace-counting with robust `json_decode()` method
- Eliminated 50% JSON parsing error rate (76,955 errors out of 153,915 objects)
- Cities import now processes all cities correctly without parsing failures
- Norwegian cities (and all others) now import properly without being filtered out due to parsing errors

### Changed
- Switched from line-by-line manual JSON parsing to loading entire file with `file_get_contents()` + `json_decode()`
- More reliable and faster processing (native PHP JSON parsing vs. manual string manipulation)
- Removed fragile brace-counting logic that caused every other city to fail parsing

### Technical Details
- 185MB JSON file loads successfully with standard WordPress 256M memory limit
- Peak memory usage remains around 10-15MB due to efficient processing
- All 153,915 cities now parse correctly (0 JSON errors)

## [2.27.7] - 2025-12-04

### Fixed
- **Log Size Reduction**: Drastically reduced debug log file size during city imports (95-99% smaller)
- JSON parsing errors now limited to first 10 entries instead of logging thousands
- Removed excessive per-city debug logging
- Progress logging reduced from every 100 to every 500 cities
- Object tracking reduced from every 10k to every 50k objects

### Technical Details
- Added `$json_errors` counter to track total errors without logging each one
- Removed debug logging for all Norwegian cities, mega cities (>500k), and filter rejections
- Log file now contains only critical information and error summaries
- Import process now generates manageable log files even for 150k+ cities

## [2.12.0] - 2025-01-02

### Fixed
- **Critical Fix**: Corrected Git tag naming to use `v` prefix (e.g., `v2.12.0`) for WordPress update detection
- Plugin updates now properly detected by WordPress auto-updater

### Added
- Same features as 2.11.0 (re-released with correct tag format)

## [2.11.0] - 2025-01-02 (Git tag issue - superseded by 2.12.0)

### Added
- **Wikidata Integration**: Plugin now uses Wikidata API for 100% accurate official translations of location names
- New `WTA_Wikidata_Translator` class for fetching official localized names from Wikidata
- Support for `wikiDataId` field from JSON data sources for precise translation lookups
- Intelligent fallback system: Wikidata → Static translations → AI → Original name
- Cache system for Wikidata translations (1 year for successful lookups, 30 days for missing translations)
- `wta_wikidata_id` meta field stored for all countries and cities

### Changed
- **Translation Priority**: Wikidata now takes priority over AI translations, ensuring more reliable and accurate Danish location names
- `WTA_AI_Translator::translate()` now accepts optional `wikidata_id` parameter
- Country and city imports now include Wikidata ID in payload for improved translation accuracy
- Updated translation flow to try Wikidata first, then static Quick_Translate, then AI, and finally return original name

### Fixed
- Resolved issue where AI would hallucinate incorrect translations for place names
- Small towns now correctly keep their original names when no official Danish translation exists (proper Danish convention)

### Technical Details
- Wikidata API endpoint: `https://www.wikidata.org/wiki/Special:EntityData/{Q-ID}.json`
- Translations cached in WordPress transients with prefix `wta_wikidata_`
- Rate limiting: 100ms delay between API calls to respect Wikidata limits
- Comprehensive logging for translation sources and success/failure tracking

---

## [2.10.0] - 2025-01-02

### Added
- **Country Page Template**: New 6-section AI content structure for country landing pages
  - Section 1: Introduction
  - Section 2: Timezones Overview
  - Section 3: Major Cities
  - Section 4: Weather & Climate
  - Section 5: Culture & Time
  - Section 6: Travel Information
- Admin UI prompts for all 6 country page sections with editable system and user prompts
- H1 title custom field (`_pilanto_page_h1`) support for country pages
- `[wta_major_cities count="12"]` shortcode now adapts to show cities from current country (not continent)
- Automatic content regeneration for parent location when child is added

### Changed
- Country pages now use multi-prompt AI generation system (same approach as continents)
- `generate_country_content()` function mirrors continent structure with 6 prompts instead of 5
- Updated admin prompts interface with separate "Country Page Template" section

### Fixed
- Country AI content generation routing now correctly uses `generate_country_content()`
- Ensured H1 titles are saved for both continent and country pages

---

## [2.9.10] - 2025-01-01

### Fixed
- **Critical Fix**: `wta_population` meta is now correctly saved during city import
  - Added `update_post_meta( $post_id, 'wta_population', intval( $data['population'] ) );` in `process_city()`
  - This fixes the `[wta_major_cities]` shortcode not displaying cities due to NULL population values
- Major cities shortcode now correctly filters and displays cities with population data

---

## [2.9.8] - 2025-01-01

### Added
- Debug logging in `major_cities_shortcode()` to troubleshoot city display issues
- Logging for major cities query, found cities, and parent post type

---

## [2.9.7] - 2025-01-01

### Changed
- **Improved Reliability**: Replaced AI-generated individual `[wta_city_time]` shortcodes with a single dynamic `[wta_major_cities count="12"]` shortcode
- Shortcode is now inserted directly in `generate_continent_content()` instead of relying on AI to generate it
- This ensures the shortcode is always present and correctly formatted

---

## [2.9.6] - 2025-01-01

### Added
- Additional debug logging in `generate_continent_content()` for major cities detection
- Logs number of major cities found and their IDs for troubleshooting

---

## [2.9.5] - 2025-01-01

### Added
- Comprehensive CSS styling for `wta-city-times-grid` (3x4 responsive grid layout)
- Individual `wta-live-city-clock` styling with gradient backgrounds
- Extended `clock.js` to update `wta-live-city-clock` elements with real-time updates including seconds

---

## [2.9.4] - 2025-01-01

### Fixed
- `[wta_major_cities]` shortcode now correctly displays cities by including `post_status => array('publish', 'draft')` in query
- Major cities are now found even when they are still in draft status during continent content generation

---

## [2.9.3] - 2025-01-01

### Fixed
- `[wta_child_locations]` shortcode heading now uses simple `post_title` instead of SEO H1 title
- Heading now shows "Oversigt over lande i Europa" instead of "Oversigt over lande i Hvad er klokken i Europa?..."

---

## [2.9.2] - 2025-01-01

### Added
- `[wta_city_time city="London"]` shortcode to display live time for a specific city
- Styling for inline city time display (`wta-inline-city-time`)

### Fixed
- `[wta_child_locations]` shortcode links now use simple country/city names instead of SEO H1 titles
- Changed from `get_the_title()` to `get_post_field('post_title')` for link text

---

## [2.9.1] - 2025-01-01

### Fixed
- Increased CSS specificity for `.wta-locations-grid` to prevent theme style overrides
- Added `!important` flags for critical grid layout properties

---

## [2.9.0] - 2025-01-01

### Added
- `[wta_child_locations]` shortcode to display grid of child countries/cities with dynamic heading and intro text
- CSS styling for locations grid layout
- Dynamic heading: "Oversigt over lande i [continent]" or "Oversigt over byer i [country]"
- Intro text with count of child locations and timezones

---

## [2.8.4] - 2024-12-30

### Added
- PHP filter (`the_title`) and JavaScript fallback to automatically replace H1 titles for `wta_location` posts
- H1 title now automatically uses `_pilanto_page_h1` custom field without requiring theme modifications

---

## [2.8.3] - 2024-12-30

### Added
- `THEME-INTEGRATION.md` documentation for theme developers
- H1 custom field (`_pilanto_page_h1`) is now saved during continent and country post creation

### Changed
- Theme integration guide explains how to use `_pilanto_page_h1` meta key for custom H1 display

---

## [2.8.2] - 2024-12-30

### Fixed
- `post_title` for continents and countries now uses simple names (e.g., "Europa", "Danmark")
- SEO-friendly H1 title stored in separate custom field for display

---

## [2.8.1] - 2024-12-30

### Added
- `add_paragraph_breaks()` function to format AI content into readable paragraphs
- All AI-generated content now automatically formatted with proper line breaks

### Changed
- H2 headings now use Danish grammatical capitalization (only proper nouns capitalized)
- Continent content generation query now includes `post_status => array('publish', 'draft')` to find child countries

### Fixed
- Fixed missing country list on continent pages by including draft posts in query
- Resolved "klumpet tekst" issue - AI content now displays in well-formatted paragraphs

---

## [2.8.0] - 2024-12-30

### Added
- **Multi-Prompt System for Continent Pages**: Continent pages now use 5 separate AI prompts for different sections:
  - Section 1: Introduction (200-300 words)
  - Section 2: Timezones in [Continent]
  - Section 3: Major Cities in [Continent]
  - Section 4: Geography & Climate
  - Section 5: Interesting Facts
- Editable prompts in admin UI - each section has separate system and user prompts
- Default prompt templates pre-filled with SEO-optimized instructions
- Support for dynamic variables in prompts: `{continent_name_local}`, `{num_countries}`, `{country_list}`

### Changed
- Redesigned "Continent Page Template" section in admin prompts interface
- `generate_continent_content()` now calls OpenAI 5 times (once per section) instead of generating all content in one call
- Each section has its own temperature and token settings for optimal output
- Max tokens removed from PHP (now controlled in prompts)

---

## [2.7.3] - 2024-12-29

### Fixed
- **Critical Fix**: Cities now correctly assigned to parent country instead of other cities
  - Modified `get_posts` query in `process_city()` to filter by `wta_type = 'country'`
  - Prevents incorrect parent assignments where cities became children of other cities

---

## [2.7.2] - 2024-12-29

### Fixed
- Syntax error in streaming parser (removed extra curly brace on line 547)

---

## [2.7.1] - 2024-12-29

### Fixed
- Improved municipality/commune filtering:
  - Now filters by `name` field to exclude entries containing "kommune", "municipality", "commune", etc.
  - Added `type` field filtering to exclude non-city administrative divisions
- Population filter now correctly skips cities with `null` or `0` population when `min_population` is set

---

## [2.7.0] - 2024-12-29

### Changed
- **Major Performance Fix**: Reverted to chunk-based streaming parser for `cities.json` to avoid memory exhaustion
  - Reads and parses one JSON object (city) at a time
  - Drastically reduced memory usage (from 512MB+ to <100MB)
  - Can now handle 185MB `cities.json` file without hitting memory limits

---

## [2.6.1] - 2024-12-29

### Fixed
- "Prepare Import Queue" AJAX button now correctly handles "Quick Test Mode" parameters
- Updated `ajax_prepare_import()` to receive and pass `import_mode` and `selected_countries`

---

## [2.5.0] - 2024-12-28

### Fixed
- **Critical Fix**: City import now correctly filters by `country_code` (ISO2) instead of `country_id`
  - This ensures cities are matched to the correct WordPress post IDs for parent countries
  - Resolves issue where 0 cities were queued despite countries being imported

---

## [2.4.0] - 2024-12-28

### Added
- Comprehensive debug logging for `cities_import` job
- Separate debug log file: `wp-content/uploads/wta-cities-import-debug.log`
- Try-catch blocks with detailed error messages

---

## [2.3.0] - 2024-12-28

### Added
- "Quick Test Mode" in data import interface
  - Option to select specific countries (e.g., Denmark) for fast testing
  - Country selector organized by continent for better UX
- `import_mode` parameter: `continents` or `countries`

---

## [2.2.0] - 2024-12-28

### Changed
- Improved admin grid layout: Changed from `minmax(300px, 1fr)` to `minmax(500px, 1fr)` for better readability

---

## [2.1.0] - 2024-12-27

### Added
- OpenAI API integration for AI-powered content generation
- Action Scheduler for background job processing
- Multi-stage import system: Structure → Timezone Resolution → AI Content Generation
- Custom post type: `wta_location` for continents, countries, and cities
- Hierarchical location structure with parent-child relationships
- AI-powered translation of location names to Danish
- Static translation table (`WTA_Quick_Translate`) for common locations
- Custom queue system with progress tracking
- Admin interface for data import, AI settings, and prompts management

---

## [2.0.0] - 2024-12-25

### Added
- Initial release of World Time AI plugin
- Basic time display functionality
- GitHub data source integration for countries, states, and cities


