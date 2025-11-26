# World Time AI 2.0 - Setup Instructions

## 🚀 Installation Steps

### 1. Install Dependencies

#### Action Scheduler
Download Action Scheduler from: https://github.com/woocommerce/action-scheduler/releases

Extract to: `includes/action-scheduler/`

#### Plugin Update Checker
Download Plugin Update Checker from: https://github.com/YahnisElsts/plugin-update-checker/releases

Extract to: `includes/plugin-update-checker/`

### 2. Upload JSON Data Files (Optional)

For faster import, upload these files to: `wp-content/uploads/world-time-ai-data/`

- `countries.json` - https://github.com/dr5hn/countries-states-cities-database/blob/master/json/countries.json
- `states.json` - https://github.com/dr5hn/countries-states-cities-database/blob/master/json/states.json
- `cities.json` - https://github.com/dr5hn/countries-states-cities-database/blob/master/json/cities.json

**Note:** If you don't upload these files, they will be fetched automatically from GitHub on first import.

### 3. Install Plugin

1. Upload the `world-time-ai` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **World Time AI → AI Settings** and configure your OpenAI API key
4. Go to **World Time AI → Timezone & Language** and configure your TimeZoneDB API key (optional, but recommended for complex countries)

### 4. Import Data

1. Go to **World Time AI → Data & Import**
2. Configure import settings:
   - Select continents to import
   - Set minimum population filter (e.g., 500,000 for major cities only)
   - Set max cities per country (optional, useful for testing)
3. Click **Prepare Import Queue**
4. Monitor progress in **Tools → Scheduled Actions**

## 📋 Post-Installation Checklist

- [ ] Action Scheduler library installed
- [ ] Plugin Update Checker library installed
- [ ] OpenAI API key configured
- [ ] TimeZoneDB API key configured (optional)
- [ ] JSON data files uploaded OR GitHub URLs configured
- [ ] Import queue prepared
- [ ] Monitor Scheduled Actions for processing progress

## 🔑 API Keys

### OpenAI
Get your API key from: https://platform.openai.com/api-keys

**Recommended model:** gpt-4o-mini (best balance of quality and cost)

### TimeZoneDB
Get your free API key from: https://timezonedb.com/api

**Free tier:** 1 request/second, perfect for this plugin!

## 🎯 Import Process

### Processing Order

1. **Structure Processor** (every 5 min)
   - Creates continent, country, and city posts
   - Posts created with **Danish names and Danish URLs** from the start!
   - Hierarchical structure: Europe → Danmark → København

2. **Timezone Processor** (every 5 min)
   - Resolves timezones for cities in complex countries (US, CA, BR, RU, AU, etc.)
   - Respects API rate limit (1 req/sec)

3. **AI Content Processor** (every 5 min)
   - Generates Danish content, titles, and SEO metadata
   - Publishes posts when complete

### Monitoring

- **Dashboard:** World Time AI → Dashboard
- **Scheduled Actions:** Tools → Scheduled Actions
- **Logs:** World Time AI → Tools → Load Recent Logs

## 🛠️ Troubleshooting

### Import queue not processing
- Check that Action Scheduler is installed correctly
- Go to Tools → Scheduled Actions and check for errors
- Verify JSON files exist or GitHub URLs are configured

### Timezone resolution failing
- Verify TimeZoneDB API key in settings
- Check Tools → Logs for API errors
- Free tier allows 1 request/second - processor respects this limit

### AI content not generating
- Verify OpenAI API key in settings
- Test connection in AI Settings page
- Check Tools → Logs for API errors

### Memory issues
- Reduce batch size in complex scenarios
- Upload JSON files locally instead of fetching from GitHub
- Increase PHP memory limit in wp-config.php

## 📊 Expected Results

With **no population filter**:
- ~150,000+ cities imported
- Processing time: Several hours to days (depending on server and API limits)

With **500,000 population filter**:
- ~1,000-2,000 major cities
- Processing time: 1-3 hours

With **5,000,000 population filter**:
- ~100-200 mega cities
- Processing time: 15-30 minutes

## 🎨 Customization

### AI Prompts
Edit in: World Time AI → Prompts

Available variables:
- `{location_name}` - Original English name
- `{location_name_local}` - Danish name
- `{timezone}` - IANA timezone
- `{country_name}` - Parent country
- `{continent_name}` - Parent continent

### Frontend Templates
Override in your theme:
- Create folder: `your-theme/world-time-ai/`
- Copy template from: `includes/frontend/templates/single-world_time_location.php`
- Customize as needed

### Styles
Enqueue your own CSS to override default styles.

## 🔄 Updates

Plugin updates automatically via GitHub releases.

To create a new release:
1. Update version in `world-time-ai.php`
2. Commit and tag: `git tag -a v2.0.1 -m "Version 2.0.1"`
3. Push: `git push origin main && git push origin v2.0.1`
4. Create GitHub release with zip file

## 📝 Important Notes

### Danish URLs
Posts are created with Danish titles and slugs **from the start**. This ensures:
- SEO-friendly Danish URLs: `/europa/danmark/kobenhavn/`
- No URL changes after creation (WordPress limitation)
- Better UX for Danish users

### Data Persistence
JSON files in `wp-content/uploads/world-time-ai-data/` persist across:
- Plugin updates
- Plugin reinstalls
- Theme changes

Only deleted on plugin uninstall.

### Settings Persistence
All settings use `add_option()` instead of `update_option()`, ensuring:
- Settings preserved across plugin updates
- No accidental overwrites of API keys
- Safe upgrades

## 🆘 Support

For issues, check:
1. Dashboard for queue statistics
2. Tools → Scheduled Actions for job status
3. Tools → Load Recent Logs for detailed errors
4. Tools → System Information for environment check

## 🎉 Success!

When import completes, you should see:
- Published posts in All Locations
- Live clocks on city pages
- Danish content and URLs
- Hierarchical structure in WordPress admin

Enjoy World Time AI 2.0! 🕐


