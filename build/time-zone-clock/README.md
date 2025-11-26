# World Time AI 2.0

WordPress plugin for displaying current local time worldwide with AI-generated Danish content.

## 🎉 Status
✅ **COMPLETE** - Ready for testing! (v2.0.0)

Complete ground-up rewrite incorporating all lessons learned from v1.0.

## 📚 Documentation
- **[Setup Instructions](SETUP-INSTRUCTIONS.md)** - How to install and configure
- **[External Libraries](EXTERNAL-LIBRARIES.md)** - Required dependencies to download
- **[Development Checklist](DEVELOPMENT-CHECKLIST.md)** - Feature completion status
- **[Requirements Specification](docs/world-time-ai-requirements.md)** - Complete technical requirements

## ✨ Key Features
- ⏰ **Real-time clocks** - Live updating clocks for 150,000+ cities worldwide
- 🌍 **Hierarchical structure** - Continents → Countries → Cities with Danish URLs
- 🇩🇰 **Danish from start** - Translation BEFORE post creation (no English URLs!)
- 🤖 **AI-generated content** - GPT-4 powered Danish content and SEO metadata
- 📍 **Smart timezone resolution** - Automatic for simple countries, API for complex ones
- ⚡ **Action Scheduler** - Reliable background processing with automatic retry
- 💾 **Persistent storage** - Data survives plugin updates in `wp-content/uploads/`
- 🎯 **Correct filtering** - Cities with null population included (unlike v1.0!)

## 🚀 Quick Start

### 1. Install External Libraries (Required!)
```bash
cd includes/

# Action Scheduler
git clone https://github.com/woocommerce/action-scheduler.git

# Plugin Update Checker
git clone https://github.com/YahnisElsts/plugin-update-checker.git
```

See [EXTERNAL-LIBRARIES.md](EXTERNAL-LIBRARIES.md) for details.

### 2. Upload JSON Files (Optional, but recommended)
Place these in `wp-content/uploads/world-time-ai-data/`:
- [countries.json](https://github.com/dr5hn/countries-states-cities-database/blob/master/json/countries.json)
- [cities.json](https://github.com/dr5hn/countries-states-cities-database/blob/master/json/cities.json)

Or they'll be fetched from GitHub automatically.

### 3. Install & Configure
1. Upload plugin to `/wp-content/plugins/world-time-ai/`
2. Activate in WordPress
3. Configure API keys:
   - **OpenAI:** https://platform.openai.com/api-keys
   - **TimeZoneDB:** https://timezonedb.com/api (free!)

### 4. Import Data
1. Go to **World Time AI → Data & Import**
2. Select continents (try Europe + Asia for testing)
3. Set population filter (5M = ~100 cities, 500K = ~2000 cities)
4. Click **Prepare Import Queue**
5. Monitor in **Tools → Scheduled Actions**

## 🏗️ Technology Stack
- **WordPress:** 6.8+
- **PHP:** 8.4+
- **Action Scheduler:** Background job processing
- **OpenAI API:** GPT-4o Mini for content generation
- **TimeZoneDB API:** Timezone resolution for complex countries
- **Yoast SEO:** Full meta integration

## 📁 Project Structure
```
world-time-ai/
├── world-time-ai.php         # Main plugin file
├── uninstall.php              # Cleanup on uninstall
├── includes/
│   ├── class-wta-core.php     # Core plugin class
│   ├── class-wta-activator.php # Activation routines
│   ├── class-wta-deactivator.php
│   ├── class-wta-loader.php    # Hook loader
│   ├── core/                   # Core functionality
│   │   ├── class-wta-post-type.php
│   │   ├── class-wta-queue.php
│   │   ├── class-wta-importer.php
│   │   └── class-wta-github-fetcher.php
│   ├── scheduler/              # Action Scheduler processors
│   │   ├── class-wta-structure-processor.php
│   │   ├── class-wta-timezone-processor.php
│   │   └── class-wta-ai-processor.php
│   ├── admin/                  # Admin interface
│   │   ├── class-wta-admin.php
│   │   ├── class-wta-settings.php
│   │   ├── views/              # Admin page templates
│   │   └── assets/             # Admin CSS/JS
│   ├── frontend/               # Frontend display
│   │   ├── class-wta-template-loader.php
│   │   ├── class-wta-shortcodes.php
│   │   ├── templates/          # Page templates
│   │   └── assets/             # Frontend CSS/JS
│   ├── helpers/                # Utility classes
│   │   ├── class-wta-logger.php
│   │   ├── class-wta-quick-translate.php
│   │   ├── class-wta-timezone-helper.php
│   │   └── class-wta-utils.php
│   ├── action-scheduler/       # External: Download separately
│   └── plugin-update-checker/  # External: Download separately
└── docs/                       # Documentation
    └── world-time-ai-requirements.md
```

## 🎯 Critical v1.0 Fixes

| Issue | v1.0 | v2.0 |
|-------|------|------|
| **URL Language** | English slugs, translated later | Danish from start ✅ |
| **Population Filter** | Excluded null values | Includes null values ✅ |
| **Data Storage** | Plugin folder (lost on update) | wp-content/uploads ✅ |
| **Settings** | Overwritten on update | Persistent ✅ |
| **Background Jobs** | WP Cron (unreliable) | Action Scheduler ✅ |

## 🌍 Example URLs
```
/europa/                      (Continent)
/europa/danmark/              (Country)
/europa/danmark/kobenhavn/    (City with live clock)
```

All Danish from creation - no English URLs to fix later!

## 🎨 Customization

### AI Prompts
Edit all 9 prompts in **World Time AI → Prompts**

Available variables:
- `{location_name}` - Original English name
- `{location_name_local}` - Danish name
- `{timezone}` - IANA timezone
- `{country_name}` - Parent country
- `{continent_name}` - Parent continent

### Templates
Override in your theme:
1. Create folder: `your-theme/world-time-ai/`
2. Copy template from: `includes/frontend/templates/`
3. Customize!

### Styles
Enqueue your own CSS to override defaults.

## 📊 Performance

| Dataset | Cities | Processing Time |
|---------|--------|-----------------|
| Mega cities (5M+) | ~100 | 15-30 min |
| Major cities (500K+) | ~2,000 | 1-3 hours |
| All cities (no filter) | ~150,000 | 1-3 days |

Processing happens in background via Action Scheduler.

## 🔄 Updates

Plugin updates automatically from GitHub releases.

**To create a release:**
1. Update version in `world-time-ai.php`
2. Commit and tag: `git tag -a v2.0.1 -m "Version 2.0.1"`
3. Push: `git push origin main && git push origin v2.0.1`
4. Create GitHub release

## 🆘 Troubleshooting

See [SETUP-INSTRUCTIONS.md](SETUP-INSTRUCTIONS.md#-troubleshooting) for common issues.

**Quick checks:**
- Dashboard shows queue statistics
- Tools → Scheduled Actions shows job status
- Tools → Load Logs shows detailed errors
- Tools → System Information checks environment

## 📝 License
Proprietary

## 👨‍💻 Author
Henrik Andersen

## 🙏 Acknowledgments
- **Action Scheduler** by Automattic (WooCommerce)
- **Plugin Update Checker** by YahnisElsts
- **Countries States Cities Database** by dr5hn

---

**Version**: 2.0.0  
**Status**: ✅ Ready for Testing  
**Last Updated**: November 25, 2025

🎉 **Complete ground-up rewrite with all v1.0 issues fixed!**

