# World Time AI - Requirements Specification

**Version**: 2.0  
**Last Updated**: November 25, 2025  
**Project Type**: WordPress Plugin

---

## 1. Project Overview

### 1.1 Purpose
Create a WordPress plugin that imports and displays current local time for cities worldwide with AI-generated content and hierarchical location pages.

### 1.2 Core Functionality
- Import global location data (continents, countries, cities)
- Create hierarchical WordPress pages with Danish content and URLs
- Display real-time local time for each location
- Generate AI-powered content in Danish language
- Handle 150,000+ pages via efficient batch processing

---

## 2. Technical Requirements

### 2.1 Environment
- **WordPress**: 6.8+
- **PHP**: 8.4+
- **MySQL**: 5.7+ or MariaDB 10.3+
- **Server**: Any (Apache, Nginx, LiteSpeed)

### 2.2 Third-Party Integrations
- **Yoast SEO**: Full integration for meta titles and descriptions
- **Action Scheduler**: For reliable background job processing
- **Plugin Update Checker**: For automatic updates via GitHub

---

## 3. Data Sources

### 3.1 Location Data
**Source**: `dr5hn/countries-states-cities-database` (GitHub)

**Files Required**:
- `countries.json` (~1.5 MB)
- `states.json` (~5 MB)
- `cities.json` (~185 MB)

**Important Fields**:
```json
// Country
{
  "id": 59,
  "name": "Denmark",
  "iso2": "DK",
  "region": "Europe",
  "latitude": "56.26392000",
  "longitude": "9.50178500"
}

// City
{
  "id": 12345,
  "name": "Copenhagen",
  "country_id": 59,
  "latitude": "55.67594000",
  "longitude": "12.56553000",
  "population": 1153615
}
```

### 3.2 Timezone API
**Service**: TimeZoneDB (https://timezonedb.com)
- **Usage**: Resolve IANA timezones for complex countries
- **Rate Limit**: 1 request/second (free tier)
- **Complex Countries**: USA, Canada, Brazil, Russia, Australia, Mexico

### 3.3 AI Service
**Service**: OpenAI API
- **Usage**: Generate Danish content, titles, and SEO metadata
- **Model**: gpt-4o-mini (configurable)
- **Rate Limit**: Handle gracefully with delays

---

## 4. Data Storage

### 4.1 JSON Files Location
**Path**: `wp-content/uploads/world-time-ai-data/`

**Requirements**:
- Auto-create directory on plugin activation
- Protect with `.htaccess`: `Deny from all`
- Persist across plugin updates and reinstalls
- Auto-migrate from legacy locations if found

**Directory Structure**:
```
wp-content/uploads/world-time-ai-data/
├── countries.json
├── states.json
├── cities.json
├── logs/
│   └── [date]-log.txt
└── .htaccess
```

### 4.2 Database Tables

#### Queue Table
```sql
CREATE TABLE {prefix}_world_time_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    source_id VARCHAR(100),
    payload LONGTEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    last_error TEXT,
    attempts INT DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_type_status (type, status),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Queue Types**:
- `continent` - Continent post creation
- `country` - Country post creation
- `cities_import` - Batch job to parse cities.json
- `city` - Individual city post creation
- `timezone` - Timezone resolution via API
- `ai_content` - AI content generation

---

## 5. WordPress Structure

### 5.1 Custom Post Type

**Name**: `world_time_location`

**Configuration**:
```php
[
    'hierarchical' => true,
    'public' => true,
    'has_archive' => true,
    'rewrite' => [
        'slug' => '',
        'with_front' => false,
        'hierarchical' => true,
    ],
    'supports' => ['title', 'editor', 'page-attributes'],
]
```

**URL Structure**:
```
/europa/                    (continent)
/europa/danmark/            (country)
/europa/danmark/kobenhavn/  (city)
```

### 5.2 Post Meta Fields

**All Posts**:
- `wta_type` - Type: `continent`, `country`, or `city`
- `wta_name_original` - Original name from source data
- `wta_name_danish` - Translated Danish name
- `wta_ai_status` - AI status: `pending`, `done`, or `error`

**Continents**:
- `wta_continent_code` - Continent code (e.g., `EU`)

**Countries**:
- `wta_continent_code` - Parent continent code
- `wta_country_code` - ISO2 country code (e.g., `DK`)
- `wta_country_id` - Source data ID
- `wta_timezone` - IANA timezone
- `wta_timezone_status` - Status: `resolved`, `pending`, or `fallback`

**Cities**:
- `wta_continent_code` - Continent code
- `wta_country_code` - Country code
- `wta_country_id` - Parent country ID
- `wta_city_id` - Source data ID
- `wta_lat` - Latitude
- `wta_lng` - Longitude
- `wta_timezone` - IANA timezone
- `wta_timezone_status` - Status: `resolved`, `pending`, or `fallback`

---

## 6. Translation System

### 6.1 Quick Translation
**Requirement**: All posts MUST be created with Danish names and Danish URL slugs.

**Implementation**: Static translation map for common locations.

**Translation Map** (minimum 50 entries):
```php
[
    // Continents
    'Europe' => 'Europa',
    'Asia' => 'Asien',
    'Africa' => 'Afrika',
    'North America' => 'Nordamerika',
    'South America' => 'Sydamerika',
    'Oceania' => 'Oceanien',
    'Antarctica' => 'Antarktis',
    
    // Countries (minimum)
    'Denmark' => 'Danmark',
    'Germany' => 'Tyskland',
    'Sweden' => 'Sverige',
    'Norway' => 'Norge',
    'United Kingdom' => 'Storbritannien',
    // ... +45 more common countries
]
```

**Workflow**:
1. Fetch English name from source data
2. Look up Danish translation in static map
3. Use Danish name for `post_title` and `post_name` (slug)
4. Store both names in post meta
5. AI can refine translation later but URL is already Danish

**Critical**: URLs cannot be changed after post creation in WordPress.

---

## 7. Import Process

### 7.1 Import Configuration

**Admin Options**:
- Select continents to import (checkboxes for each)
- Minimum population filter (number, 0 = no filter)
- Max cities per country (number, 0 = unlimited)
- Clear existing queue (checkbox)

### 7.2 Population Filter Logic

**Rule**: Only filter cities that HAVE population data.

```
IF min_population > 0:
    IF city.population is NOT null:
        IF city.population > 0 AND city.population < min_population:
            SKIP city
        ELSE:
            INCLUDE city
    ELSE:
        INCLUDE city  (cities without population data are always included)
ELSE:
    INCLUDE all cities
```

**Reasoning**: Many cities have `null` population in source data but are still relevant.

### 7.3 Import Workflow

```
1. User configures import settings
2. Click "Prepare Import Queue"
   ↓
3. System fetches JSON files
4. Creates queue items:
   - Continents (direct queue items)
   - Countries (direct queue items)
   - Cities (ONE batch job item)
   ↓
5. Action Scheduler processes queue:
   a. Structure processor creates posts
   b. Cities batch job parses cities.json and queues individual cities
   c. Timezone processor resolves timezones for complex countries
   d. AI processor generates content
   ↓
6. Posts published
```

### 7.4 Batch Job for Cities

**Purpose**: Avoid loading 185MB file into memory at once.

**Implementation**:
- Queue ONE `cities_import` job with filter criteria
- Action Scheduler processes this job
- Job streams cities.json in chunks
- Creates individual `city` queue items
- Updates count: "1,247 cities queued from batch job"

---

## 8. Background Processing (Action Scheduler)

### 8.1 Why Action Scheduler
- Guaranteed execution (not dependent on site traffic)
- Parallel processing capability
- Automatic retry on failures
- Built-in admin UI for monitoring
- Battle-tested (used by WooCommerce)

### 8.2 Scheduled Actions

**Three recurring actions** (every 5 minutes):

1. **`wta_process_structure`**
   - Processes: `continent`, `country`, `city`, `cities_import`
   - Batch size: 50 items
   - Creates WordPress posts

2. **`wta_process_timezone`**
   - Processes: `timezone` queue items
   - Batch size: 5 items (API rate limit)
   - Calls TimeZoneDB API
   - 200ms delay between requests

3. **`wta_process_ai_content`**
   - Processes: `ai_content` queue items
   - Batch size: 10 items
   - Calls OpenAI API
   - Publishes posts when complete

### 8.3 Configuration

```php
// Concurrent batches
add_filter( 'action_scheduler_queue_runner_concurrent_batches', function() {
    return 3; // 3 parallel workers
} );

// Batch size
add_filter( 'action_scheduler_queue_runner_batch_size', function() {
    return 50;
} );

// Log retention
add_filter( 'action_scheduler_retention_period', function() {
    return 7 * DAY_IN_SECONDS; // 7 days
} );
```

### 8.4 Monitoring
Built-in UI available at: `WordPress Admin → Tools → Scheduled Actions`

Shows:
- Pending actions
- Running actions
- Completed actions (with logs)
- Failed actions (with error messages)

---

## 9. AI Content Generation

### 9.1 AI Prompts

**Nine configurable prompts** (each has system + user prompt):

1. **`translate_location_name`** - Translate location name to Danish
2. **`city_page_title`** - Generate city page title
3. **`city_page_content`** - Generate city page content (200-300 words)
4. **`country_page_title`** - Generate country page title
5. **`country_page_content`** - Generate country page content (300-400 words)
6. **`continent_page_title`** - Generate continent page title
7. **`continent_page_content`** - Generate continent page content (400-500 words)
8. **`yoast_seo_title`** - Generate SEO meta title (50-60 chars)
9. **`yoast_meta_description`** - Generate meta description (140-160 chars)

### 9.2 Prompt Variables

All prompts support these variables:
- `{location_name}` - Original English name
- `{location_name_local}` - Danish translated name
- `{location_type}` - Type: city, country, or continent
- `{country_name}` - Country name
- `{continent_name}` - Continent name
- `{timezone}` - IANA timezone
- `{base_language}` - Target language (da-DK)
- `{base_language_description}` - "Skriv på flydende dansk til danske brugere"
- `{base_country_name}` - Base country (Danmark)

### 9.3 AI Settings

**Admin Configuration**:
- OpenAI API Key (text input)
- Model (dropdown: gpt-4o-mini, gpt-4, etc.)
- Temperature (slider: 0.0 - 1.0, default: 0.7)
- Max Tokens (number: default 2000)

### 9.4 Yoast SEO Integration

When Yoast SEO is active:
- Set `_yoast_wpseo_title` meta field
- Set `_yoast_wpseo_metadesc` meta field
- Respect Yoast settings for whether to allow updates

---

## 10. Frontend Display

### 10.1 City Page Template

**Required Elements**:
- H1: "Hvad er klokken i [city]?"
- Live clock showing current time in city's timezone
- Time difference to base country
- AI-generated content
- Date in Danish format

**Live Clock**:
- Updates every second via JavaScript
- Uses Intl.DateTimeFormat API
- Displays in format: HH:MM:SS
- Shows Danish date format

### 10.2 Country Page Template

**Required Elements**:
- H1 with country name
- AI-generated content
- List of child cities (with links)

### 10.3 Continent Page Template

**Required Elements**:
- H1 with continent name
- AI-generated content
- List of child countries (with links)

### 10.4 JavaScript Clock

**Implementation Requirements**:
```javascript
- Use Intl.DateTimeFormat with timezone parameter
- Update every 1000ms (1 second)
- Handle timezone correctly via IANA timezone string
- Danish locale: 'da-DK'
- Format: '14:32:15'
```

---

## 11. Plugin Settings

### 11.1 Settings Persistence

**Critical Requirement**: Settings MUST persist across plugin updates.

**Implementation**:
```php
// CORRECT: Use add_option() - does not overwrite existing values
add_option( 'wta_openai_api_key', '' );
add_option( 'wta_base_language', 'da-DK' );

// WRONG: update_option() always overwrites
// update_option( 'wta_openai_api_key', '' ); // DON'T DO THIS
```

### 11.2 Base Settings

**Required Settings**:
- Base Country Name (text, default: "Danmark")
- Base Timezone (dropdown, default: "Europe/Copenhagen")
- Base Language (text, default: "da-DK")
- Base Language Description (textarea, default: "Skriv på flydende dansk til danske brugere")

### 11.3 Complex Countries List

**Configurable List** of countries requiring API timezone lookup:
```
Default: US, CA, BR, RU, AU, MX, ID, CN, KZ, AR, GL, CD, SA, CL
```

Admin should be able to add/remove country codes.

---

## 12. Admin Interface

### 12.1 Menu Structure

```
World Time AI (top-level menu)
├── Dashboard
├── Data & Import
├── AI Settings
├── Prompts
├── Timezone & Language
└── Tools
```

### 12.2 Dashboard Page

**Display**:
- Location Posts count (published/draft/total)
- Queue Status (pending/processing/done/errors)
- Queue by Type table
- Action Scheduler Status
- System Information (WordPress version, PHP version, memory usage)
- Quick Actions buttons

### 12.3 Data & Import Page

**Sections**:

1. **Data Sources**
   - GitHub URLs for JSON files (optional if local files exist)
   - Display file status and size if found locally

2. **Import Configuration**
   - Continent selection (checkboxes)
   - Minimum population filter (number input)
   - Max cities per country (number input)
   - Clear existing queue (checkbox)
   - [Prepare Import Queue] button

3. **Import Instructions**
   - Step-by-step guide
   - Processing order explanation
   - Link to Scheduled Actions

### 12.4 AI Settings Page

**Fields**:
- OpenAI API Key (password input)
- Model (dropdown)
- Temperature (slider with value display)
- Max Tokens (number input)
- [Test API Connection] button
- Save Settings button

### 12.5 Prompts Page

**For each of 9 prompts**:
- Prompt name and description
- System prompt (textarea)
- User prompt (textarea)
- Available variables (info box)
- [Reset to Default] button per prompt
- Save All Prompts button

### 12.6 Timezone & Language Page

**Fields**:
- Base Country Name
- Base Timezone (dropdown of all IANA timezones)
- Base Language
- Base Language Description
- Complex Countries List (comma-separated country codes)
- [Test TimeZoneDB API] button

### 12.7 Tools Page

**Actions**:
- View Recent Logs (display last 100 log entries)
- Retry Failed Queue Items button
- Reset Stuck Items button (items processing > 5 minutes)
- Clear Cache button
- [View Scheduled Actions] button → links to Action Scheduler UI
- [Reset All Data] button (requires confirmation)

---

## 13. Plugin Auto-Update

### 13.1 Update Mechanism

Use **Plugin Update Checker** library (YahnisElsts).

**Configuration**:
```php
$updateChecker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/[username]/world-time-ai',
    __FILE__,
    'world-time-ai'
);

$updateChecker->getVcsApi()->enableReleaseAssets('world-time-ai.zip');
```

### 13.2 Version Check

**On `plugins_loaded` hook**:
```php
$current_version = get_option( 'wta_plugin_version', '0.0.0' );

if ( version_compare( $current_version, WTA_VERSION, '<' ) ) {
    // Plugin upgraded
    WTA_Activator::activate(); // Ensure tables and default options
    update_option( 'wta_plugin_version', WTA_VERSION );
    
    // Show upgrade notice
    set_transient( 'wta_upgraded_notice', [
        'from' => $current_version,
        'to' => WTA_VERSION
    ], HOUR_IN_SECONDS );
}
```

### 13.3 GitHub Release Process

1. Update version in main plugin file
2. Update CHANGELOG.md
3. Commit and tag: `git tag -a v2.0.0 -m "Version 2.0.0"`
4. Build zip file (excluding json/ directory)
5. Push to GitHub: `git push origin main && git push origin v2.0.0`
6. Create GitHub Release with zip file attached

---

## 14. Error Handling & Logging

### 14.1 Logger Class

**Required Methods**:
```php
WTA_Logger::error( $message, $context = [] );
WTA_Logger::warning( $message, $context = [] );
WTA_Logger::info( $message, $context = [] );
WTA_Logger::debug( $message, $context = [] );
```

**Log Format**:
```
[2025-11-25 14:30:15] ERROR: Failed to fetch cities.json
Context: {"url": "https://...", "error": "Connection timeout"}
```

**Log Location**: `wp-content/uploads/world-time-ai-data/logs/`

### 14.2 Queue Retry Logic

- Failed items: Set status to 'error' with error message
- Increment attempts counter
- Retry failed items up to 3 times
- After 3 failures, mark as permanently failed
- Admin can manually retry from Tools page

### 14.3 Error Display

- Show user-friendly error messages in admin
- Technical errors logged but not shown to end-users
- Queue errors visible in Tools → Logs

---

## 15. Performance Requirements

### 15.1 Large File Handling

**Requirement**: Handle cities.json (185MB) without memory errors.

**Implementation**: Stream-parse JSON in 8KB chunks instead of loading entire file.

### 15.2 Batch Processing

- Max 50 items per batch
- Stop processing 10 seconds before PHP timeout
- Use Action Scheduler for parallel processing
- Transient caching for external API data

### 15.3 Caching

```php
// GitHub data: 1 hour
set_transient( 'wta_github_countries', $data, HOUR_IN_SECONDS );

// Local file data: 1 day
set_transient( 'wta_local_cities', $data, DAY_IN_SECONDS );

// Object cache for queries
wp_cache_set( 'continent_eu', $post_id, 'wta_continents', DAY_IN_SECONDS );
```

---

## 16. File Structure

```
world-time-ai/
├── world-time-ai.php                 Main plugin file
├── uninstall.php                     Cleanup on uninstall
├── README.md
├── CHANGELOG.md
├── .gitignore
│
├── includes/
│   ├── class-wta-core.php
│   ├── class-wta-loader.php
│   ├── class-wta-activator.php
│   ├── class-wta-deactivator.php
│   │
│   ├── core/
│   │   ├── class-wta-post-type.php
│   │   ├── class-wta-queue.php
│   │   ├── class-wta-importer.php
│   │   ├── class-wta-queue-processor.php
│   │   ├── class-wta-github-fetcher.php
│   │   ├── class-wta-timezone-resolver.php
│   │   ├── class-wta-ai-generator.php
│   │   └── class-wta-prompt-manager.php
│   │
│   ├── admin/
│   │   ├── class-wta-admin.php
│   │   ├── class-wta-settings.php
│   │   ├── assets/
│   │   │   ├── css/
│   │   │   └── js/
│   │   └── views/
│   │       ├── dashboard.php
│   │       ├── data-import.php
│   │       ├── ai-settings.php
│   │       ├── prompts.php
│   │       ├── timezone-language.php
│   │       └── tools.php
│   │
│   ├── scheduler/
│   │   ├── class-wta-action-scheduler.php
│   │   ├── class-wta-structure-processor.php
│   │   ├── class-wta-timezone-processor.php
│   │   └── class-wta-ai-processor.php
│   │
│   ├── frontend/
│   │   ├── class-wta-template-loader.php
│   │   ├── class-wta-shortcodes.php
│   │   ├── assets/
│   │   │   ├── css/
│   │   │   └── js/
│   │   └── templates/
│   │       ├── single-world_time_location.php
│   │       └── archive-world_time_location.php
│   │
│   ├── helpers/
│   │   ├── class-wta-utils.php
│   │   ├── class-wta-logger.php
│   │   ├── class-wta-timezone-helper.php
│   │   └── class-wta-quick-translate.php
│   │
│   ├── action-scheduler/            Third-party library
│   └── plugin-update-checker/       Third-party library
│
├── languages/
│   └── world-time-ai.pot
│
└── build/                            Distribution files (for releases)
    └── world-time-ai/
        └── (same structure without json files)
```

---

## 17. Testing Checklist

### 17.1 Installation
- [ ] Fresh install creates all tables
- [ ] Fresh install creates data directory
- [ ] Fresh install sets default options
- [ ] Fresh install schedules recurring actions

### 17.2 Import
- [ ] Import with continent selection works
- [ ] Population filter includes null values
- [ ] Max cities per country limit works
- [ ] Clear queue option works
- [ ] Batch job queues cities correctly
- [ ] Progress messaging is clear

### 17.3 Post Creation
- [ ] Posts created with Danish names
- [ ] URLs are Danish
- [ ] Hierarchical structure correct (parent-child)
- [ ] Meta fields populated correctly

### 17.4 Timezone Resolution
- [ ] Simple countries get default timezone
- [ ] Complex countries queued for API
- [ ] TimeZoneDB API calls work
- [ ] Rate limiting respected (1 req/sec)

### 17.5 AI Content
- [ ] AI generates Danish content
- [ ] All 9 prompts work correctly
- [ ] Yoast SEO fields populated
- [ ] Posts published after AI completion

### 17.6 Frontend
- [ ] Clock displays correctly
- [ ] Clock updates every second
- [ ] Timezone displayed correctly
- [ ] Time difference calculated correctly
- [ ] Content displays properly

### 17.7 Settings
- [ ] Settings persist after plugin update
- [ ] Prompts editable and saveable
- [ ] Reset to defaults works

### 17.8 Admin UI
- [ ] Dashboard shows correct stats
- [ ] Queue status accurate
- [ ] Scheduled Actions UI accessible
- [ ] Tools functions work

### 17.9 Updates
- [ ] Plugin detects new version from GitHub
- [ ] Update preserves settings
- [ ] Update preserves data files
- [ ] Upgrade notice displays

---

## 18. Success Criteria

Plugin is complete when:

✅ Can import 150,000+ locations without errors
✅ All posts have Danish names and Danish URLs from creation
✅ Population filter correctly includes cities with null population
✅ Timezone resolution works for both simple and complex countries
✅ AI generates all required content in Danish
✅ Action Scheduler processes queue reliably
✅ Frontend displays accurate real-time clocks
✅ Settings persist across plugin updates
✅ JSON files persist in uploads directory
✅ Auto-update from GitHub works
✅ Admin UI provides clear visibility and control
✅ Error handling is robust with retry logic
✅ Performance is acceptable for large datasets

---

## 19. Known Constraints

1. **WordPress URLs**: Post slugs cannot be changed after creation - translation MUST happen before post creation
2. **TimeZoneDB API**: Free tier limited to 1 request/second
3. **OpenAI API**: Rate limits depend on account tier
4. **PHP Memory**: Large file parsing requires streaming approach
5. **PHP Execution Time**: Batch processing must respect server limits

---

## 20. Out of Scope (Future Versions)

- Multi-language support (only Danish in v2.0)
- Public API endpoints
- Migration tool from v1.0
- Real-time timezone change detection
- Mobile app integration
- Custom timezone override per city

---

## 21. Dependencies

### 21.1 Required PHP Extensions
- json
- curl
- mbstring
- mysqli

### 21.2 Third-Party Libraries
- Action Scheduler v3.7+ (bundled)
- Plugin Update Checker v5+ (bundled)

### 21.3 WordPress
- CPT support
- Cron support
- REST API (optional, for future features)

---

**END OF REQUIREMENTS SPECIFICATION**

This document provides all requirements for implementing World Time AI 2.0.
For technical architecture details and code examples, refer to `world-time-ai-v2-spec.md`.

