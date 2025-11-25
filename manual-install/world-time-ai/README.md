# World Time AI - WordPress Plugin

Version: 1.0.0  
Requires WordPress: 6.8+  
Requires PHP: 8.4+

## Overview

World Time AI is a comprehensive WordPress plugin that imports and displays current local time for cities worldwide with AI-generated content. It creates hierarchical location pages (Continent → Country → City) with real-time timezone support.

## Features

### Core Features
- **Hierarchical Location Structure**: Creates organized pages for continents, countries, and cities
- **Real-Time Clock Display**: Live JavaScript-powered clock showing current time in any timezone
- **AI-Generated Content**: Automatic content generation using OpenAI for all location pages
- **Timezone Management**: Automatic timezone resolution using TimeZoneDB API for complex countries
- **SEO Optimization**: Full Yoast SEO integration with AI-generated meta titles and descriptions
- **Multilingual Support**: All content generated in your chosen language

### Data Import
- Import from dr5hn/countries-states-cities-database GitHub repository
- Configurable filters: continent selection, population minimums, max cities per country
- Background processing with WordPress cron jobs
- Batched processing to avoid timeouts (handles 150,000+ locations)
- Resumable import process with error handling

### Admin Interface
- **Dashboard**: Overview of import progress, queue statistics, and system status
- **Data & Import**: Configure and manage data imports
- **AI Settings**: Configure OpenAI API and Yoast integration
- **Prompts Editor**: Customize all AI prompts with variable support
- **Timezone & Language**: Configure base country, timezone, language, and complex countries
- **Tools & Logs**: Error logs, data management, and troubleshooting tools

## Installation

1. Upload the `world-time-ai` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **World Time AI** → **AI Settings** to configure your OpenAI API key
4. Go to **World Time AI** → **Timezone & Language** to configure TimeZoneDB API key
5. Go to **World Time AI** → **Data & Import** to start importing locations

## Updates

### Automatic Updates via GitHub
The plugin includes **Plugin Update Checker** for automatic updates from GitHub:

1. When a new version is released, you'll see an update notification in WordPress
2. Click **Update Now** to install the latest version
3. ✅ **All your settings are automatically preserved** (API keys, configurations, prompts)
4. After update, you'll see a confirmation notice in the admin

### Settings Preservation
**Your settings are safe during updates!** The plugin uses WordPress best practices:
- API keys (OpenAI, TimeZoneDB) are preserved
- All custom configurations are maintained
- Custom prompts remain unchanged
- Import filters and preferences are kept
- Only database structure and missing options are updated

### Manual Update
If you need to manually update:
1. Deactivate (but don't delete) the plugin
2. Replace plugin files with new version
3. Reactivate the plugin
4. All settings will be restored automatically

## Configuration

### Required API Keys

#### OpenAI API
Get your API key from: https://platform.openai.com/api-keys

Configure in: **World Time AI** → **AI Settings**

#### TimeZoneDB API
Get a free API key from: https://timezonedb.com/api

Configure in: **World Time AI** → **Timezone & Language**

### Base Settings

Configure your base country and language in **Timezone & Language**:
- **Base Country**: The country to compare time differences against
- **Base Timezone**: IANA timezone (e.g., Europe/Copenhagen)
- **Base Language**: Language code (e.g., da-DK, en-US)
- **Language Style**: Instructions for AI writing style

## Import Process

### Step 1: Prepare Import
1. Go to **Data & Import**
2. Select continents to import (or leave all unchecked for all)
3. Optionally set population filter and max cities per country
4. Click "Prepare Import Queue"

### Step 2: Automatic Processing
The plugin will automatically process the queue in the background using WordPress cron:

1. **Structure Import** (every 5 minutes, batch: 50 items)
   - Creates continent, country, and city posts
   - Establishes hierarchical relationships

2. **Timezone Resolution** (every 5 minutes, batch: 5 items)
   - Resolves timezones for cities in complex countries
   - Rate-limited to 1 request/second for TimeZoneDB

3. **AI Content Generation** (every 5 minutes, batch: 10 items)
   - Translates location names
   - Generates page titles and content
   - Creates SEO meta data

### Step 3: Monitor Progress
Check the **Dashboard** for real-time statistics on:
- Queue status (pending, processing, done, errors)
- Created location posts
- Cron job status

## Custom Post Type

All locations are stored in the `world_time_location` custom post type with hierarchical URLs:

- `/europa/` (continent)
- `/europa/tyskland/` (country)
- `/europa/tyskland/berlin/` (city)

### Post Meta Fields

Each location has the following meta fields:
- `wta_type`: continent | country | city
- `wta_continent_code`: Continent code
- `wta_country_code`: ISO2 country code
- `wta_country_id`: Database country ID
- `wta_city_id`: Database city ID
- `wta_lat`: Latitude
- `wta_lng`: Longitude
- `wta_timezone`: IANA timezone identifier
- `wta_timezone_status`: pending | resolved | fallback
- `wta_ai_status`: pending | done | error
- `wta_name_original`: Original English name
- `wta_name_local`: Translated name

## Shortcodes

### Clock Display
```
[wta_clock timezone="Europe/Copenhagen"]
[wta_clock city_id="123"]
```

### Time Difference
```
[wta_time_difference timezone="Europe/Copenhagen"]
[wta_time_difference city_id="123" format="long"]
```

## Template Customization

### Override Default Template
Copy `includes/frontend/templates/single-world_time_location.php` to your theme:
```
your-theme/single-world_time_location.php
```

### Available Template Functions
- `WTA_Utils::get_time_in_timezone($timezone, $format)`
- `WTA_Utils::get_iso_time_in_timezone($timezone)`
- `WTA_Timezone_Helper::get_formatted_difference($tz1, $tz2, $format)`
- `WTA_Timezone_Helper::get_current_time($timezone, $format)`

## AI Prompts

All AI prompts are fully customizable in **World Time AI** → **Prompts**.

### Available Prompts
1. Location Name Translation
2. City Page Title
3. City Page Content
4. Country Page Title
5. Country Page Content
6. Continent Page Title
7. Continent Page Content
8. Yoast SEO Title
9. Yoast Meta Description

### Available Variables
- `{location_name}` - Original name from database
- `{location_name_local}` - Translated name
- `{location_type}` - continent | country | city
- `{country_name}` - Country name
- `{continent_name}` - Continent name
- `{timezone}` - IANA timezone
- `{base_language}` - Target language
- `{base_language_description}` - Language style instructions
- `{base_country_name}` - Base country name

## Complex Countries

Countries requiring individual city timezone lookups (configured in **Timezone & Language**):

Default list:
- US: United States
- CA: Canada
- BR: Brazil
- AU: Australia
- RU: Russia
- MX: Mexico
- ID: Indonesia
- CN: China

Other countries use a default country-level timezone.

## Database Tables

### wp_world_time_queue
Stores import and processing queue items:
- `id`: Queue item ID
- `type`: continent | country | city | timezone | ai_content
- `source_id`: Reference ID
- `payload`: JSON data
- `status`: pending | processing | done | error
- `last_error`: Error message (if any)
- `created_at`: Creation timestamp
- `updated_at`: Update timestamp

## Cron Jobs

### Registered Schedules
- `wta_five_minutes`: Every 5 minutes

### Registered Events
1. `world_time_import_structure`: Process structure queue
2. `world_time_resolve_timezones`: Resolve timezones via API
3. `world_time_generate_ai_content`: Generate AI content

All cron jobs use transient locks to prevent overlapping execution.

## Performance Considerations

- **Batched Processing**: All operations are batched to prevent timeouts
- **Caching**: GitHub data is cached for 1 hour
- **Rate Limiting**: TimeZoneDB calls are rate-limited to 1 req/sec
- **Timeout Protection**: Operations check for approaching max_execution_time
- **Memory Management**: Uses efficient database queries with limits

## Troubleshooting

### Import Not Processing
1. Check **Dashboard** for cron job status
2. Ensure WordPress cron is running (`wp-cron.php`)
3. Check **Tools & Logs** for error messages
4. Use "Reset Stuck Items" in **Tools** if items are stuck in processing

### API Errors
1. Verify API keys in settings
2. Use "Test API Connection" buttons to verify
3. Check error logs for specific error messages
4. Ensure server can make outbound HTTP requests

### Missing Timezones
1. Verify TimeZoneDB API key is configured
2. Check if country is in complex countries list
3. Check queue for pending timezone items
4. Review logs for API errors

### Reset and Start Over
Use **Tools & Logs** → **Full Data Reset** to:
- Delete all imported location posts
- Clear the entire queue
- Start fresh import

## Support

For issues, feature requests, or questions, please contact support.

## Credits

- Data source: [dr5hn/countries-states-cities-database](https://github.com/dr5hn/countries-states-cities-database)
- Timezone API: [TimeZoneDB](https://timezonedb.com/)
- AI Generation: [OpenAI](https://openai.com/)

## License

GPL v2 or later

## Changelog

### 1.0.0
- Initial release
- Complete import system
- AI content generation
- Timezone resolution
- Admin interface
- Frontend templates
- Shortcodes
- Yoast SEO integration






