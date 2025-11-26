# World Time AI - Version 2.1.0

## 🎉 Major Release: AI-Powered Universal Translation

Version 2.1.0 introduces intelligent AI-powered translations for **all locations worldwide**, making the plugin truly language-agnostic and ready for any market.

---

## 🌟 Major Features

### AI-Powered Translation System
- **New**: Comprehensive AI translation engine using OpenAI
- **Coverage**: ALL continents, countries, and cities - not just pre-defined ones
- **Smart Caching**: Translations cached for 1 year to minimize API costs
- **Fallback System**: Static translations for common locations, AI for everything else
- **Language Agnostic**: Works with any language set as "Base Language"

### Performance Improvements
- **Faster Processing**: Scheduled Actions now run every 1 minute (previously 5 minutes)
- **Optimized Queue**: Faster content generation and location creation
- **Better WP-Cron Integration**: Fully utilizes wp-cron.php running every minute

### Clean URL Structure
- **Fixed**: Removed `world_time_location` from URLs
- **Clean URLs**: `/europa/danmark/kobenhavn/` instead of `/world_time_location/europa/danmark/kobenhavn/`
- **Hierarchical**: Proper parent-child URL structure
- **SEO Friendly**: Clean, readable URLs for better search engine visibility

---

## ✨ Improvements

### Content Quality
- **Natural Writing**: AI prompts improved to generate authentic, WordPress-friendly content
- **No ChatGPT Artifacts**: Automatic removal of phrases like "Velkommen til", "Lad os udforske", etc.
- **Direct Style**: Content is now more direct and informative
- **Clean Metadata**: Title tags and meta descriptions automatically cleaned of quotes

### Translation Coverage
#### Static Translations (Fast & Free):
- **Continents**: 7 continents translated
- **Countries**: 200+ countries including:
  - All European countries (Albania, Andorra, Austria, etc.)
  - All American countries (North & South America)
  - All Asian countries (Middle East, Central, South, Southeast, East Asia)
  - All African countries (North, West, Central, East, Southern Africa)
  - All Oceania countries
- **Cities**: 200+ major cities worldwide

#### AI Translations (Comprehensive):
- Any location not in static map
- Context-aware translations (understands geography)
- Respects local naming conventions
- Keeps names that shouldn't be translated (like "London")

### Admin Features
- **New Tool**: "Clear Translation Cache" button in Tools page
- **Better Logging**: All translations logged for debugging
- **Cache Management**: Easy to force fresh translations when changing languages

---

## 🔧 Technical Changes

### New Files
- `includes/helpers/class-wta-ai-translator.php` - AI translation engine with caching

### Modified Files
- `includes/class-wta-activator.php` - 1-minute intervals, improved prompts
- `includes/class-wta-core.php` - AI translator integration
- `includes/core/class-wta-post-type.php` - Custom URL structure
- `includes/core/class-wta-importer.php` - Uses AI translator
- `includes/scheduler/class-wta-structure-processor.php` - AI translation support
- `includes/scheduler/class-wta-ai-processor.php` - Content cleaning and quote removal
- `includes/admin/class-wta-admin.php` - Translation cache AJAX endpoint
- `includes/admin/views/tools.php` - Translation cache UI
- `includes/helpers/class-wta-quick-translate.php` - Expanded static translations
- `time-zone-clock.php` - Version bump to 2.1.0

---

## 📋 Installation & Update

### Fresh Installation
1. Upload the plugin files to `/wp-content/plugins/world-time-ai/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your OpenAI API key in AI Settings
4. Go to Settings → Permalinks and click "Save Changes"
5. Start importing locations!

### Updating from 2.0.0
1. **Backup your site** (always recommended)
2. Deactivate the plugin
3. Delete old plugin files
4. Upload new version
5. Reactivate the plugin
6. **Important**: Go to Settings → Permalinks and click "Save Changes" (flushes rewrite rules for new URL structure)
7. **Optional**: Go to Tools → "Clear Translation Cache" if you want fresh translations

---

## 🎯 How AI Translation Works

### Translation Flow
1. **Check Static Map** (instant, free)
   - If location is in the static translation map → use it
   
2. **Use AI Translation** (intelligent, comprehensive)
   - If not in static map → call OpenAI API
   - Low temperature (0.3) for consistent results
   - Context-aware prompts for geographical names
   
3. **Cache Result** (performance)
   - Store in WordPress transients for 1 year
   - Avoid repeated API calls
   - Minimal cost impact

### Example Translations
- **Albania** → "Albanien" (static, instant)
- **Tórshavn** (Faroe Islands) → "Tórshavn" (AI, keeps local name)
- **Ulaanbaatar** (Mongolia) → "Ulan Bator" (AI, Danish spelling)

---

## ⚙️ Configuration

### OpenAI Settings
The AI translator uses your existing OpenAI configuration:
- **Model**: Uses configured model (default: gpt-4o-mini)
- **Temperature**: 0.3 for translations (consistent results)
- **Max Tokens**: 50 tokens per translation (very cost-effective)
- **Timeout**: 30 seconds per request

### Translation Costs
Extremely low - approximately:
- **Per Translation**: ~$0.0001 (one hundredth of a cent)
- **1000 Translations**: ~$0.10
- **Cached Forever**: Most translations only called once

---

## 🔍 What's Fixed

1. ✅ Scheduled Actions run every minute (Issue #1)
2. ✅ All locations translate to selected language (Issue #2)
3. ✅ URLs no longer contain `world_time_location` (Issue #3)
4. ✅ Landing page content is WordPress-friendly (Issue #4)
5. ✅ Title tags and meta descriptions have no quotes (Issue #5)

---

## 📚 Admin Tools

### New: Translation Cache Management
Located in **World Time AI → Tools**:

```
┌─────────────────────────────────┐
│ Translation Cache               │
├─────────────────────────────────┤
│ Clear cached AI translations.   │
│ Use this when you change the    │
│ base language or want to force  │
│ fresh translations.              │
│                                  │
│ [Clear Translation Cache]       │
└─────────────────────────────────┘
```

**When to use:**
- Changed "Base Language" in settings
- Want to regenerate translations with different prompts
- Debugging translation issues

---

## 🌍 Language Support

The plugin now works with **any language** you set as "Base Language":
- Danish (da-DK) - Default
- English (en-US)
- Swedish (sv-SE)
- Norwegian (no-NO)
- German (de-DE)
- French (fr-FR)
- Spanish (es-ES)
- Italian (it-IT)
- And more...

Simply change "Base Country Name" in Timezone & Language settings, clear the translation cache, and reimport!

---

## 🐛 Bug Reports & Support

Found a bug or have a suggestion?
- **GitHub Issues**: https://github.com/henrikandersen1978/what_is_the_time/issues
- **Email**: henrik@example.com

---

## 📝 Requirements

- **WordPress**: 6.8 or higher
- **PHP**: 8.4 or higher
- **OpenAI API Key**: Required for AI translations and content generation
- **TimeZoneDB API Key**: Required for complex timezone resolution

---

## 🙏 Acknowledgments

- Action Scheduler by Automattic
- Plugin Update Checker by YahnisElsts
- Countries/States/Cities database by dr5hn

---

## 📅 Release Date

November 25, 2025

---

**Full Changelog**: https://github.com/henrikandersen1978/what_is_the_time/compare/v2.0.0...v2.1.0



