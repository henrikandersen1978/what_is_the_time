# World Time AI 2.0.0 - Complete Rewrite 🚀

## ✨ Major Release - Complete Rewrite from Ground Up

World Time AI 2.0.0 is a complete rewrite that fixes all v1.0 issues and implements a robust, production-ready architecture.

---

## 🎯 Key Features

### ✅ Fixed from v1.0
- **🇩🇰 Danish URLs from Creation** - No more English slugs! Posts are created with Danish titles and slugs from the start
- **💾 Persistent Data Storage** - JSON data files stored in `wp-content/uploads/world-time-ai-data/` survive plugin updates
- **⚡ Action Scheduler Integration** - Reliable background processing replaces WordPress cron
- **✅ Correct Population Filter** - Cities with `null` population are now included in imports
- **🔧 Settings Persistence** - API keys and settings are never overwritten during updates
- **📊 Accurate Queue Counts** - Clear UI feedback showing actual queue status
- **🏗️ Correct Hierarchical Structure** - Continents → Countries → Cities properly maintained

### 🆕 New Features
- **🕐 Live Clocks** - Real-time JavaScript clocks on every location page
- **📱 Modern Admin UI** - Beautiful, intuitive admin interface with cards and clear sections
- **🔍 Smart Translation** - Built-in Danish translation map for 200+ cities
- **📊 Enhanced Dashboard** - Real-time statistics and progress tracking
- **🎨 Custom Templates** - Beautiful frontend display with responsive design
- **🔐 Secure API Handling** - Encrypted API key storage
- **📝 Comprehensive Logging** - Detailed logs for debugging and monitoring
- **⚙️ Flexible Configuration** - Control population filters, continent selection, and processing speed

---

## 📦 Installation

### Prerequisites
This plugin requires two external libraries (see `EXTERNAL-LIBRARIES.md`):

1. **Action Scheduler** (3.7.0+)
2. **Plugin Update Checker** (5.0+)

### Quick Install

**Option 1: Via Build Script (Recommended)**
```powershell
# Clone repository
git clone https://github.com/henrikandersen1978/what_is_the_time.git
cd what_is_the_time

# Download external libraries
cd includes
git clone https://github.com/woocommerce/action-scheduler.git
git clone https://github.com/YahnisElsts/plugin-update-checker.git
cd ..

# Build plugin ZIP
powershell -ExecutionPolicy Bypass -File build.ps1

# Upload build/world-time-ai.zip to WordPress
```

**Option 2: Direct Download**
1. Download `world-time-ai.zip` from this release
2. Download external libraries (see `EXTERNAL-LIBRARIES.md`)
3. Extract everything to `wp-content/plugins/world-time-ai/`
4. Activate in WordPress

### Configuration
1. Go to **WordPress Admin → World Time AI → Settings**
2. Add your **OpenAI API Key** (required)
3. Add your **TimeZoneDB API Key** (optional but recommended)
4. Configure **data import settings** (continents, population filters)
5. Click **Prepare Import Queue** to start

---

## 🔄 Automatic Updates

Once installed, World Time AI will automatically check for updates from GitHub. When a new version is released, you'll see an update notification in WordPress.

**Note:** You must have the external libraries installed for updates to work properly.

---

## 📖 Documentation

- **[SETUP-INSTRUCTIONS.md](./SETUP-INSTRUCTIONS.md)** - Complete setup guide
- **[EXTERNAL-LIBRARIES.md](./EXTERNAL-LIBRARIES.md)** - External library installation
- **[DEVELOPMENT-CHECKLIST.md](./DEVELOPMENT-CHECKLIST.md)** - Development guide
- **[BUILD-AND-RELEASE.md](./BUILD-AND-RELEASE.md)** - Release process
- **[docs/world-time-ai-requirements.md](./docs/world-time-ai-requirements.md)** - Full requirements specification

---

## ⚠️ Breaking Changes from v1.0

**This is NOT an upgrade - it's a complete rewrite:**

1. **Custom Post Type Changed**: Uses `world_time_location` instead of old CPT
2. **Database Table Changed**: New queue table structure
3. **Settings Structure Changed**: API keys and settings stored differently
4. **Data Storage Changed**: Files moved to `wp-content/uploads/`
5. **No v1.0 Data Migration**: You'll need to re-import all data

**Recommendation:** Deactivate and delete v1.0 before installing v2.0.0.

---

## 🎯 What's Next?

After installation:

1. **Configure API Keys** in Settings
2. **Select Continents** to import
3. **Set Population Filter** (e.g., 500,000 for major cities)
4. **Prepare Import Queue** - this fetches and parses data
5. **Monitor Dashboard** - watch as Action Scheduler processes your queue
6. **View Locations** - check out the beautiful frontend display

---

## 🐛 Known Issues

None! This is a fresh rewrite with all v1.0 issues resolved.

Report any issues at: https://github.com/henrikandersen1978/what_is_the_time/issues

---

## 📊 Statistics

- **Total Files:** 50+
- **Lines of Code:** 5,000+
- **External Dependencies:** 2 (Action Scheduler, Plugin Update Checker)
- **Supported Cities:** 140,000+
- **Supported Countries:** 250+
- **Supported Continents:** All
- **Languages:** Danish (with English fallback)

---

## 💡 Technical Highlights

- **Architecture:** Modular, PSR-4 style structure
- **Processing:** Action Scheduler with rate limiting
- **Data Handling:** Streaming JSON parser for large files
- **Translation:** AI-powered with local translation maps
- **Frontend:** Live JavaScript clocks with real-time updates
- **Admin:** AJAX-powered interface with real-time feedback
- **Security:** Nonce verification, capability checks, input sanitization
- **Performance:** Optimized database queries, caching support

---

## 🙏 Credits

Built with:
- [Action Scheduler](https://github.com/woocommerce/action-scheduler) by Automattic/WooCommerce
- [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker) by Yahnis Elsts
- [OpenAI API](https://platform.openai.com) for content generation
- [TimeZoneDB API](https://timezonedb.com) for timezone resolution
- [GeoNames](https://www.geonames.org) data (via GitHub)

---

## 📝 Changelog

### [2.0.0] - 2025-11-25

#### Complete Rewrite
- Rebuilt entire plugin from scratch
- Fixed all v1.0 architectural issues
- Implemented production-ready architecture

#### Added
- Action Scheduler integration
- Persistent data storage in uploads directory
- Danish translation before post creation
- Live JavaScript clocks
- Modern admin UI with cards
- Comprehensive logging system
- Smart translation maps
- Real-time dashboard statistics
- Custom frontend templates
- Automatic GitHub updates

#### Fixed
- English slugs in URLs (now Danish from creation)
- Data loss during plugin updates
- Population filter excluding null values
- Incorrect hierarchical structure
- Misleading queue counts
- Settings overwritten on update
- WordPress cron reliability issues

#### Changed
- Custom post type to `world_time_location`
- Queue processing to Action Scheduler
- Data storage to uploads directory
- Translation timing to before post creation
- Admin interface completely redesigned

---

## 🚀 Ready to Get Started?

Download `world-time-ai.zip`, follow the setup instructions, and start generating beautiful world time location pages with AI!

**Need help?** Check out the documentation or open an issue on GitHub.

---

**Enjoy World Time AI 2.0.0! 🌍⏰🤖**

