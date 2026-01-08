# Multilingual Support (Site Language System)

**Version:** v3.2.0  
**Status:** ⏳ Not Implemented  
**Priority:** 🔴 High  
**Estimated Time:** 6-10 hours  
**Dependencies:** None  
**Architecture:** JSON-based language packs  

---

## 📋 Table of Contents

1. [Problem Statement](#problem-statement)
2. [Solution Overview](#solution-overview)
3. [Architecture](#architecture)
4. [Files to Modify](#files-to-modify)
5. [Detailed Implementation](#detailed-implementation)
6. [Testing Checklist](#testing-checklist)
7. [Future Expansion](#future-expansion)

---

## 🎯 Problem Statement

### Current Situation:
- Plugin is **hardcoded in Danish**
- All AI prompts, FAQ strings, and content are Danish-only
- Cannot easily deploy English, German, or Swedish versions
- Backend admin is Danish (which is fine - we want to keep it!)

### What Users Want:
- Deploy plugin on **multiple language sites** (EN, DE, SV, NO, FI, etc.)
- AI content generated in **correct language** per site
- FAQ displayed in **correct language** per site
- Easy to **switch languages** without manual work

### Requirements:
- ✅ Backend can remain Danish
- ✅ Simple system (NO WordPress i18n/.po/.mo files)
- ✅ Easy to add new languages later (< 2 hours per language)
- ✅ One-click "Load Defaults" (no manual copy-paste of 20+ prompts)

---

## 💡 Solution Overview

Implement a **simple JSON-based multilingual system**:

1. **Add "Site Language" dropdown** in admin settings (DA, EN, DE, SV initially)
2. **Store all language defaults** in separate JSON files (prompts + FAQ strings)
3. **Add "Load Language Defaults" button** to populate all prompts for selected language
4. **Update FAQ generator** to use language-specific templates
5. **Existing prompt system** already works - just needs defaults per language!

### Key Design Decisions:
- ❌ **NOT using WordPress i18n** - too complex, overkill
- ✅ **JSON files per language** - safe, validatable, maintainable
- ✅ **Zero PHP syntax risk** - JSON files can't crash the site
- ✅ **Backend stays Danish** - no translation needed for admin UI
- ✅ **Configurable prompts** - users can customize after loading defaults
- ✅ **Easy to add languages** - just upload a new JSON file!

### Why JSON Instead of PHP Arrays?
- 🛡️ **Safety:** Invalid JSON = fallback to Danish (site stays online)
- ✅ **Validation:** Can validate JSON files before deployment
- 📝 **Easy editing:** Non-programmers can translate
- 🌍 **Community-friendly:** Anyone can contribute new languages
- 🚀 **Future-proof:** Can build admin UI for editing later

---

## 🏗️ Architecture

### Component Diagram:

```
┌─────────────────────────────────────────────────────────┐
│                    Admin Settings                        │
│  ┌──────────────────────────────────────────────────┐  │
│  │ Site Language: [🇩🇰 Dansk ▼]                     │  │
│  │ [🔄 Load Default Prompts for Dansk]              │  │
│  └──────────────────────────────────────────────────┘  │
└────────────────────┬────────────────────────────────────┘
                     │
                     ▼
          ┌──────────────────────┐
          │ WTA_Activator        │
          │ load_language_json() │
          └──────────┬───────────┘
                     │
           ┌─────────┴─────────┐
           │  Language Files   │
           │ ┌───────────────┐ │
           │ │ da.json  🇩🇰  │ │
           │ │ en.json  🇬🇧  │ │
           │ │ de.json  🇩🇪  │ │
           │ │ sv.json  🇸🇪  │ │
           │ └───────────────┘ │
           └─────────┬─────────┘
                     │
         ┌───────────┴───────────┐
         │                       │
         ▼                       ▼
┌────────────────┐      ┌──────────────────┐
│ AI Prompts     │      │ FAQ Generator    │
│ (20+ fields)   │      │ get_faq_text()   │
└────────────────┘      └──────────────────┘
         │                       │
         └───────────┬───────────┘
                     ▼
            ┌────────────────┐
            │ Frontend       │
            │ (Content in    │
            │  correct lang) │
            └────────────────┘
```

### File Structure:

```
includes/
  ├── languages/
  │   ├── README.md          (Instructions for adding languages)
  │   ├── da.json           (Danish - 🇩🇰)
  │   ├── en.json           (English - 🇬🇧)
  │   ├── de.json           (German - 🇩🇪)
  │   └── sv.json           (Swedish - 🇸🇪)
  └── class-wta-activator.php
```

### Data Flow:

```
1. User selects language: "English" in admin
2. User clicks "Load Default Prompts for English"
3. WTA_Activator::load_language_defaults('en') is called
4. Updates ~20 prompt options with English defaults
5. Success message shown to user
6. User starts import
7. AI processor uses English prompts → AI content in English
8. FAQ generator uses get_faq_text('en') → FAQ in English
9. Frontend displays all content in English
```

---

## 📁 Files to Modify/Create

| File | Changes | Lines | Complexity |
|------|---------|-------|------------|
| `includes/languages/da.json` | **NEW** Danish language pack (prompts + FAQ) | +350 | Low |
| `includes/languages/en.json` | **NEW** English language pack | +350 | Low |
| `includes/languages/de.json` | **NEW** German language pack | +350 | Low |
| `includes/languages/sv.json` | **NEW** Swedish language pack | +350 | Low |
| `includes/languages/README.md` | **NEW** Instructions for adding languages | +50 | Trivial |
| `includes/admin/class-wta-settings.php` | Add language dropdown + load button + handler | +150 | Medium |
| `includes/class-wta-activator.php` | Add JSON loader + validation + load method | +200 | Medium |
| `includes/helpers/class-wta-faq-generator.php` | Add get_faq_text() + update 12 FAQ methods | +150 | Medium |
| `time-zone-clock.php` | Bump version to 3.2.0 | +1 | Trivial |
| `CHANGELOG.md` | Add v3.2.0 entry | +10 | Trivial |
| `build-release.ps1` | Add JSON validation step | +20 | Low |

**Total:** ~2,000 lines (but mostly JSON data, not code!)

**Key Improvement:** Much safer than 800+ lines of PHP arrays in Activator class!

---

## 🔧 Detailed Implementation

### STEP 0: Create Language Files Structure ⭐ NEW!

**NEW DIRECTORY:** `includes/languages/`

Create JSON files for each language. **This is the key improvement** - all translations in safe, validatable JSON files instead of risky PHP arrays!

#### **File Structure:**

```
includes/languages/
  ├── README.md     (Instructions for adding languages)
  ├── da.json       (Danish - 🇩🇰)
  ├── en.json       (English - 🇬🇧)
  ├── de.json       (German - 🇩🇪)
  └── sv.json       (Swedish - 🇸🇪)
```

#### **JSON File Example (`da.json` - simplified):**

```json
{
  "meta": {
    "language": "da",
    "language_name": "Dansk",
    "flag": "🇩🇰",
    "version": "3.2.0"
  },
  "base_settings": {
    "base_country_name": "Danmark",
    "base_timezone": "Europe/Copenhagen",
    "base_language": "da-DK",
    "base_language_description": "Skriv på flydende dansk til danske brugere"
  },
  "prompts": {
    "translate_name_system": "Du er en professionel oversætter...",
    "translate_name_user": "Oversæt \"{location_name}\" til dansk...",
    "city_title_system": "Du er en SEO ekspert...",
    "city_title_user": "Skriv en fængende H1 titel...",
    "... (~24 prompt pairs - all existing prompts from current system)"
  },
  "faq": {
    "faq1_question": "Hvad er klokken i {city_name} lige nu?",
    "faq1_answer": "Klokken i {city_name} er <strong id=\"faq-live-time\">{current_time}</strong>...",
    "... (24 FAQ strings: 12 questions + 12 answers)"
  }
}
```

**BENEFITS:**
- ✅ No PHP syntax errors possible!
- ✅ Can validate before deployment
- ✅ Easy to edit (even in Notepad)
- ✅ AI can generate valid JSON easily
- ✅ Non-programmers can translate

**NOTE:** Complete JSON files will be created during implementation. Each file is ~350 lines but much safer than PHP arrays!

**TO ADD NEW LANGUAGE:** Just create new `.json` file (e.g., `no.json` for Norwegian), translate strings, and add to settings dropdown. Takes 60-90 minutes!

---

### STEP 1: Add Site Language Setting

**File:** `includes/admin/class-wta-settings.php`

**Location 1:** In `register_settings()` method (~line 50), add:

```php
// Site language setting (v3.2.0)
add_option( 'wta_site_language', 'da' );
register_setting( 'wta_settings_group', 'wta_site_language', array(
    'type' => 'string',
    'sanitize_callback' => 'sanitize_text_field',
    'default' => 'da',
) );
```

**Location 2:** At the **very top** of `render_settings_page()` method, add handler:

```php
public function render_settings_page() {
    // Handle "Load Language Defaults" button click (v3.2.0)
    if ( isset( $_GET['action'] ) && $_GET['action'] === 'load_language_defaults' ) {
        check_admin_referer( 'wta_load_defaults', '_wpnonce' );
        
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions' );
        }
        
        $language = isset( $_GET['language'] ) ? sanitize_text_field( $_GET['language'] ) : 'da';
        
        // Load activator if needed
        if ( ! class_exists( 'WTA_Activator' ) ) {
            require_once WTA_PLUGIN_DIR . 'includes/class-wta-activator.php';
        }
        
        $loaded = WTA_Activator::load_language_defaults( $language );
        
        $lang_names = array( 'da' => 'Dansk', 'en' => 'English', 'de' => 'Deutsch', 'sv' => 'Svenska' );
        $lang_name = isset( $lang_names[ $language ] ) ? $lang_names[ $language ] : $language;
        
        if ( $loaded ) {
            add_settings_error(
                'wta_messages',
                'wta_language_defaults_loaded',
                sprintf( '✅ Standard prompts for %s er blevet indlæst! Alle AI prompts er nu opdateret.', $lang_name ),
                'success'
            );
        } else {
            add_settings_error(
                'wta_messages',
                'wta_language_defaults_error',
                '❌ Kunne ikke indlæse standard prompts. Sproget er muligvis ikke understøttet.',
                'error'
            );
        }
        
        wp_redirect( admin_url( 'admin.php?page=wta-settings&settings-updated=1' ) );
        exit;
    }
    
    // ... rest of existing code continues here
}
```

**Location 3:** In the settings form, after OpenAI settings section (~line 200), add:

```php
<tr>
    <th scope="row"><label for="wta_site_language">Site Language / Webstedssprog</label></th>
    <td>
        <select name="wta_site_language" id="wta_site_language" class="regular-text">
            <option value="da" <?php selected( get_option( 'wta_site_language', 'da' ), 'da' ); ?>>🇩🇰 Dansk (Danish)</option>
            <option value="en" <?php selected( get_option( 'wta_site_language', 'da' ), 'en' ); ?>>🇬🇧 English</option>
            <option value="de" <?php selected( get_option( 'wta_site_language', 'da' ), 'de' ); ?>>🇩🇪 Deutsch (German)</option>
            <option value="sv" <?php selected( get_option( 'wta_site_language', 'da' ), 'sv' ); ?>>🇸🇪 Svenska (Swedish)</option>
        </select>
        <p class="description">Vælg hvilket sprog frontend-indhold skal genereres på. Backend forbliver dansk.</p>
        
        <?php
        $current_lang = get_option( 'wta_site_language', 'da' );
        $lang_names = array( 'da' => 'Dansk', 'en' => 'English', 'de' => 'Deutsch', 'sv' => 'Svenska' );
        $lang_name = isset( $lang_names[ $current_lang ] ) ? $lang_names[ $current_lang ] : 'Dansk';
        $nonce = wp_create_nonce( 'wta_load_defaults' );
        $url = add_query_arg( array(
            'page' => 'wta-settings',
            'action' => 'load_language_defaults',
            'language' => $current_lang,
            '_wpnonce' => $nonce,
        ), admin_url( 'admin.php' ) );
        ?>
        
        <p style="margin-top: 15px;">
            <a href="<?php echo esc_url( $url ); ?>" 
               class="button button-secondary"
               onclick="return confirm('⚠️ ADVARSEL: Dette vil overskrive ALLE dine AI prompts med standard-skabeloner for <?php echo esc_js( $lang_name ); ?>.\n\nDine tilpassede prompts vil gå tabt.\n\nFortsæt?');">
                🔄 Indlæs Standard Prompts for <?php echo esc_html( $lang_name ); ?>
            </a>
        </p>
        <p class="description" style="color: #d63638;">
            ⚠️ <strong>Advarsel:</strong> Knappen erstatter alle AI prompts (~20 felter) med sprog-defaults.
        </p>
    </td>
</tr>
```

---

### STEP 2: JSON Loader System

**File:** `includes/class-wta-activator.php`

Add these methods at the **end of the class** (before closing `}`). Much simpler and safer than storing huge arrays in PHP!

```php
/**
 * Load language defaults from JSON file - PUBLIC method for settings page.
 *
 * Loads JSON file, validates it, and updates ALL WordPress options with language defaults.
 * This is called when user clicks "Load Language Defaults" button in admin.
 *
 * @since 3.2.0
 * @param string $lang Language code (da, en, de, sv, etc.).
 * @return bool True on success, false if language not supported or JSON invalid.
 */
public static function load_language_defaults( $lang ) {
    // Load and parse JSON file
    $defaults = self::load_language_json( $lang );
    
    if ( empty( $defaults ) ) {
        WTA_Logger::error( 'Language not supported or JSON invalid', array( 'language' => $lang ) );
        return false;
    }
    
    $updated_count = 0;
    
    // Update ALL settings with language defaults
    foreach ( $defaults as $key => $value ) {
        update_option( 'wta_' . $key, $value );
        $updated_count++;
    }
    
    WTA_Logger::info( '🌍 Language defaults loaded from JSON', array(
        'language' => $lang,
        'settings_updated' => $updated_count,
    ) );
    
    return true;
}

/**
 * Load and parse language JSON file.
 *
 * Reads JSON file from includes/languages/{lang}.json, validates it,
 * and returns flattened array ready for WordPress options.
 *
 * TO ADD A NEW LANGUAGE:
 * 1. Create new JSON file: includes/languages/xx.json
 * 2. Copy structure from da.json
 * 3. Translate all strings (keep placeholders and HTML!)
 * 4. Validate JSON (use online validator or PowerShell)
 * 5. Add 'xx' to $allowed_langs whitelist below
 * 6. Add dropdown option in settings page
 * 7. Test!
 *
 * @since 3.2.0
 * @param string $lang Language code (da, en, de, sv, no, fi, etc.).
 * @return array Flattened settings array or empty array on error.
 */
private static function load_language_json( $lang ) {
    // Security: Whitelist allowed languages to prevent directory traversal
    $allowed_langs = array( 'da', 'en', 'de', 'sv', 'no', 'fi', 'nl' );
    if ( ! in_array( $lang, $allowed_langs, true ) ) {
        WTA_Logger::error( 'Invalid language code', array( 'lang' => $lang, 'allowed' => $allowed_langs ) );
        return array();
    }
    
    // Build file path
    $file_path = WTA_PLUGIN_DIR . 'includes/languages/' . $lang . '.json';
    
    // Check file exists
    if ( ! file_exists( $file_path ) ) {
        WTA_Logger::error( 'Language file not found', array( 'file' => $file_path ) );
        return array();
    }
    
    // Read JSON file
    $json_content = file_get_contents( $file_path );
    if ( $json_content === false ) {
        WTA_Logger::error( 'Could not read language file', array( 'file' => $file_path ) );
        return array();
    }
    
    // Parse JSON
    $data = json_decode( $json_content, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        WTA_Logger::error( 'Invalid JSON in language file', array(
            'file' => $file_path,
            'error' => json_last_error_msg(),
            'error_code' => json_last_error()
        ) );
        return array();
    }
    
    // Validate structure
    if ( ! isset( $data['base_settings'] ) || ! isset( $data['prompts'] ) || ! isset( $data['faq'] ) ) {
        WTA_Logger::error( 'Invalid language file structure', array(
            'file' => $file_path,
            'has_base' => isset( $data['base_settings'] ),
            'has_prompts' => isset( $data['prompts'] ),
            'has_faq' => isset( $data['faq'] )
        ) );
        return array();
    }
    
    // Flatten JSON structure to WordPress options format
    $defaults = array();
    
    // 1. Add base settings (base_country_name, base_timezone, etc.)
    if ( isset( $data['base_settings'] ) && is_array( $data['base_settings'] ) ) {
        foreach ( $data['base_settings'] as $key => $value ) {
            $defaults[ $key ] = $value;
        }
    }
    
    // 2. Add prompts with 'prompt_' prefix
    if ( isset( $data['prompts'] ) && is_array( $data['prompts'] ) ) {
        foreach ( $data['prompts'] as $key => $value ) {
            $defaults[ 'prompt_' . $key ] = $value;
        }
    }
    
    // 3. Store FAQ strings for FAQ generator
    if ( isset( $data['faq'] ) && is_array( $data['faq'] ) ) {
        $defaults['faq_strings'] = $data['faq'];
    }
    
    WTA_Logger::debug( 'Language JSON loaded successfully', array(
        'language' => $lang,
        'settings_count' => count( $defaults ),
        'has_meta' => isset( $data['meta'] )
    ) );
    
    return $defaults;
}

/**
 * Get fallback defaults (Danish) if JSON loading fails.
 *
 * Provides minimal working defaults so the plugin never completely breaks.
 * Only used as last resort if all JSON loading fails.
 *
 * @since 3.2.0
 * @return array Minimal Danish defaults.
 */
private static function get_fallback_defaults() {
    return array(
        'base_country_name' => 'Danmark',
            'base_timezone' => 'Europe/Copenhagen',
        'base_language' => 'da-DK',
        'base_language_description' => 'Skriv på flydende dansk til danske brugere',
        'prompt_city_title_system' => 'Du er en SEO ekspert der skriver fængende sider på dansk.',
        'prompt_city_title_user' => 'Skriv en fængende H1 titel for en side om hvad klokken er i {location_name_local}.',
        // ... (only minimal prompts needed for emergency fallback)
        'faq_strings' => array(
            'faq1_question' => 'Hvad er klokken i {city_name} lige nu?',
            'faq1_answer' => 'Klokken i {city_name} er {current_time}.'
        )
    );
}
```

**What Changed:**
- ❌ **REMOVED:** 600+ lines of hardcoded PHP arrays (risky!)
- ✅ **ADDED:** `load_language_json()` - loads and validates JSON files  
- ✅ **ADDED:** `get_fallback_defaults()` - emergency Danish defaults
- ✅ **SAFER:** JSON files can't crash PHP
- ✅ **FASTER:** Easier to add new languages (just upload JSON)

**Key Features:**
1. **Security whitelist:** Only allowed language codes accepted
2. **File validation:** Checks if file exists before reading
3. **JSON validation:** Proper error handling with detailed logging
4. **Structure validation:** Ensures all required sections exist
5. **Flattening:** Converts JSON structure to WordPress options format
6. **Fallback:** If JSON fails, uses minimal Danish defaults

---

### STEP 3: FAQ Multilingual System (Simplified)

**File:** `includes/helpers/class-wta-faq-generator.php`

With JSON-based language system, FAQ multilingual support is MUCH simpler!

**Add this helper method** at top of class (after line 15):

```php
/**
 * Get FAQ text string for current language.
 *
 * Retrieves FAQ strings from loaded language (stored in wta_faq_strings option).
 * Falls back to Danish if language not loaded.
 *
 * @since 3.2.0
 * @param string $key FAQ string key (e.g., 'faq1_question', 'faq1_answer').
 * @param array $vars Variables to replace in string (e.g., ['city_name' => 'Copenhagen']).
 * @return string FAQ text with variables replaced.
 */
private static function get_faq_text( $key, $vars = array() ) {
    // Get FAQ strings from loaded language
    $faq_strings = get_option( 'wta_faq_strings', array() );
    
    // Fallback to Danish if not found
    if ( empty( $faq_strings ) || ! isset( $faq_strings[ $key ] ) ) {
        $faq_strings = array(
            'faq1_question' => 'Hvad er klokken i {city_name} lige nu?',
            'faq1_answer' => 'Klokken i {city_name} er <strong id="faq-live-time">{current_time}</strong>. Byen bruger tidszonen {timezone}{utc_offset}.',
            // ... (only essential fallbacks)
        );
    }
    
    $text = isset( $faq_strings[ $key ] ) ? $faq_strings[ $key ] : '';
    
    // Replace placeholders with actual values
    foreach ( $vars as $var_key => $var_value ) {
        $text = str_replace( '{' . $var_key . '}', $var_value, $text );
    }
    
    return $text;
}
```

**Then update each FAQ generation method** to use `get_faq_text()`. Example:

```php
// BEFORE (hardcoded Danish):
private static function generate_current_time_faq( $city_name, $timezone ) {
    return array(
        'question' => "Hvad er klokken i {$city_name} lige nu?",
        'answer' => "Klokken i {$city_name} er...",
        'icon' => '⏰',
    );
}

// AFTER (uses JSON language):
private static function generate_current_time_faq( $city_name, $timezone ) {
    $current_time = WTA_Timezone_Helper::get_current_time_in_timezone( $timezone, 'H:i:s' );
    
    try {
        $dt = new DateTime( 'now', new DateTimeZone( $timezone ) );
        $utc_offset = ' (UTC' . $dt->format( 'P' ) . ')';
    } catch ( Exception $e ) {
        $utc_offset = '';
    }
    
    return array(
        'question' => self::get_faq_text( 'faq1_question', array( 'city_name' => $city_name ) ),
        'answer' => self::get_faq_text( 'faq1_answer', array(
            'city_name' => $city_name,
            'timezone' => $timezone,
            'current_time' => $current_time,
            'utc_offset' => $utc_offset,
        ) ),
        'icon' => '⏰',
    );
}
```

**Repeat this pattern for all 12 FAQ methods.** Much simpler than the old approach!

---

### STEP 4: Add JSON Validation to Build Script

**File:** `build-release.ps1`

Add JSON validation step BEFORE creating ZIP:

```powershell
# Validate all language JSON files before building
Write-Host "Validating language files..." -ForegroundColor Yellow

$langFiles = Get-ChildItem -Path "includes\languages\*.json"
$validationFailed = $false

foreach ($file in $langFiles) {
    $json = Get-Content $file.FullName -Raw
    try {
        $null = ConvertFrom-Json $json
        Write-Host "  ✓ $($file.Name) - Valid" -ForegroundColor Green
    } catch {
        Write-Host "  ✗ $($file.Name) - INVALID: $($_.Exception.Message)" -ForegroundColor Red
        $validationFailed = $true
    }
}

if ($validationFailed) {
    Write-Host "`nBuild ABORTED: Fix JSON errors first!" -ForegroundColor Red
    exit 1
}

Write-Host "All language files valid!" -ForegroundColor Green
Write-Host ""
```

**This prevents building a broken ZIP!** Invalid JSON is caught before deployment.

---

### STEP 5: Version Bump and Changelog

**File:** `time-zone-clock.php`

```php
// Line 14:
 * Version: 3.2.0

// Line 32:
define( 'WTA_VERSION', '3.2.0' );
```

**File:** `CHANGELOG.md`

```markdown
## [3.2.0] - 2026-XX-XX

### Added
- 🌍 **JSON-based Multilingual System**: Danish, English, German, Swedish support
- 🔄 **Load Language Defaults** button: One-click populate all prompts
- 📝 **Multilingual FAQ**: Automatic language-specific FAQ generation
- 🛡️ **JSON Validation**: Build script validates all JSON files before deployment
- 🌐 **Easy Language Addition**: Just upload a new JSON file (60-90 min per language!)

### Changed
- **SAFER Architecture**: Replaced 600+ lines of risky PHP arrays with JSON files
- **Backend stays Danish**: No translation overhead for admin interface  
- **FAQ System**: Uses `get_faq_text()` for dynamic language selection
- **Build Process**: Includes automatic JSON validation step

### Technical
- New directory: `includes/languages/` with JSON files per language
- New setting: `wta_site_language` (options: da, en, de, sv)
- New method: `WTA_Activator::load_language_json()` - loads and validates JSON
- New method: `WTA_Activator::get_fallback_defaults()` - emergency defaults
- New method: `WTA_FAQ_Generator::get_faq_text()` - retrieves FAQ strings
- JSON structure: meta, base_settings, prompts, faq sections
- ~2,000 lines added (mostly JSON data, not risky PHP code)
- **Zero PHP syntax risk** - invalid JSON can't crash the site!

### Documentation
- Feature spec: `docs/features/multilingual-support.md`
- Language README: `includes/languages/README.md`
- Instructions for adding new languages included

### Performance
- ✅ Faster to add languages (just create JSON file)
- ✅ Easier to maintain (edit JSON, not PHP)
- ✅ Safer deployments (auto-validation)
```

---

## ✅ Testing Checklist

### Test 1: JSON Validation
- [ ] Validate all JSON files (da.json, en.json, de.json, sv.json)
- [ ] Use PowerShell: `Get-Content includes\languages\da.json | ConvertFrom-Json`
- [ ] All files must parse without errors

### Test 2: Fresh Installation (Danish Default)
- [ ] Install plugin on fresh WordPress site
- [ ] Verify Site Language = "Dansk" by default
- [ ] Verify "Load Default Prompts" button exists
- [ ] Click button → verify success message
- [ ] Check all ~20 AI prompts are populated in Danish

### Test 3: Switch to English
- [ ] Change dropdown to "English"
- [ ] Click "Load Default Prompts for English"
- [ ] Confirm warning dialog
- [ ] Verify success message
- [ ] Scroll through ALL prompt fields → verify English text
- [ ] Import 10 test cities
- [ ] Check generated content → should be English
- [ ] Check FAQ → should be English

### Test 4: JSON File Missing/Invalid
- [ ] Temporarily rename `en.json` to `en.json.bak`
- [ ] Try to load English defaults
- [ ] Verify error message shown
- [ ] Check logs → should show "Language file not found"
- [ ] Restore file
- [ ] Break JSON syntax in `de.json` (remove a comma)
- [ ] Try to load German defaults
- [ ] Verify error message → "Invalid JSON"
- [ ] Fix JSON and retry → should work

### Test 5: Build Script Validation
- [ ] Break JSON in a language file
- [ ] Run `powershell -ExecutionPolicy Bypass -File build-release.ps1`
- [ ] Verify build STOPS with error message
- [ ] Verify error shows which file is invalid
- [ ] Fix JSON
- [ ] Run build again → should succeed

### Test 6: Adding New Language (Norwegian)
- [ ] Copy `da.json` to `no.json`
- [ ] Update meta section (language: "no", name: "Norsk", flag: "🇳🇴")
- [ ] Translate 3-4 prompts to Norwegian (for testing)
- [ ] Validate JSON with PowerShell
- [ ] Add "no" to whitelist in `class-wta-activator.php`
- [ ] Add dropdown option in settings
- [ ] Select Norwegian in settings
- [ ] Click "Load Defaults" → should work!
- [ ] Verify Norwegian prompts loaded

### Test 7: FAQ Multilingual
- [ ] Load Danish defaults
- [ ] Import 5 cities
- [ ] Check frontend FAQ → Danish
- [ ] Load English defaults
- [ ] Import 5 cities
- [ ] Check frontend FAQ → English
- [ ] Verify live time JavaScript still works

### Test 8: Fallback System
- [ ] Rename ALL JSON files temporarily
- [ ] Try to load any language
- [ ] Plugin should NOT crash
- [ ] Should show error but continue working
- [ ] Restore JSON files

---

## 🚀 Future Expansion

### Phase 1: More Languages (EASY NOW!)

**Norwegian (Norsk):**
- Time: **60-90 minutes** (was 8-16 hours before!)
- Process:
  1. Copy `da.json` to `no.json`
  2. Use ChatGPT/DeepL to translate all strings
  3. Add `'no'` to whitelist (1 line)
  4. Add dropdown option (1 line)
  5. Test!

**Finnish, Dutch, Polish, etc.:**
- Same process as Norwegian
- Each language: **60-90 minutes**
- No code changes needed (just JSON + 2 lines)

### Phase 2: Community Contributions

Create GitHub repository for language packs:

```
world-time-ai-languages/
  ├── README.md
  ├── da.json (official)
  ├── en.json (official)
  ├── community/
  │   ├── no.json (submitted by user)
  │   ├── fi.json (submitted by user)
  │   └── ...
```

Users can:
- Download community language packs
- Submit their own translations
- Vote/review translations

### Phase 3: Translation UI (v4.0)

Admin interface for editing translations without touching files:

```
WP Admin → World Time AI → Languages → Manage Translations

[Language] [Dansk ▼]  [+ Add New Language]

Base Settings:
- Country: [Danmark]
- Timezone: [Europe/Copenhagen]
- Language Code: [da-DK]

Prompts:
- City Title (System): [Du er en SEO ekspert...]
- City Title (User): [Skriv en fængende H1...]

FAQ Strings:
- FAQ 1 Question: [Hvad er klokken i {city_name}...]
- FAQ 1 Answer: [Klokken i {city_name} er...]

[Save Changes]  [Export JSON]  [Import JSON]
```

### Phase 4: Auto-Detection

Automatically detect language from domain:

```php
function wta_auto_detect_language() {
    $domain = parse_url( home_url(), PHP_URL_HOST );
    
    if ( strpos( $domain, '.dk' ) !== false ) return 'da';
    if ( strpos( $domain, '.de' ) !== false ) return 'de';
    if ( strpos( $domain, '.se' ) !== false ) return 'sv';
    if ( strpos( $domain, '.no' ) !== false ) return 'no';
    
    return 'en'; // Default
}
```

---

## 📝 Notes for AI Implementation

### When Implementing:

1. **Create JSON files FIRST** - use existing Danish prompts as template
2. **Use AI to translate** - ChatGPT/Claude can translate entire JSON files
3. **Test JSON validity** - always validate before committing
4. **Implement PHP code LAST** - JSON loading is simple
5. **Test each language** - import 10 cities per language

### Translation Strategy:

**Use AI for initial translation:**
```
Prompt to ChatGPT:
"Translate this JSON file from Danish to English. 
Keep all placeholders like {city_name}, {timezone} intact.
Keep all HTML tags intact.
Return valid JSON."

[Paste da.json content]
```

Then manually review:
- Placeholders preserved? ✓
- HTML tags correct? ✓
- Natural language? ✓
- No ChatGPT meta-comments? ✓

### Common Pitfalls:

1. **Forgetting commas in JSON** - use JSON validator!
2. **Escaping quotes** - use `\"` inside strings
3. **Breaking HTML** in FAQ answers
4. **Translating placeholders** - NEVER translate `{city_name}` etc.
5. **Not testing all strings** - import cities to verify

---

## 💡 Why This JSON Approach is Better

| Feature | Old PHP Arrays | New JSON Files |
|---------|----------------|----------------|
| **Safety** | ❌ Syntax error = site crash | ✅ Invalid JSON = fallback |
| **Validation** | ❌ Can't validate | ✅ Auto-validate before deploy |
| **Adding Language** | ❌ 8-16 hours | ✅ 60-90 minutes |
| **Editing** | ❌ Need PHP knowledge | ✅ Can edit in any text editor |
| **Translation** | ❌ Must understand PHP | ✅ Just translate strings |
| **Community** | ❌ Can't contribute easily | ✅ Submit JSON files |
| **Maintenance** | ❌ 800+ lines in one file | ✅ Separate 350-line files |
| **AI Translation** | ❌ Complicated | ✅ Direct paste |
| **Debugging** | ❌ Hard to find syntax errors | ✅ Clear JSON error messages |
| **Performance** | ✅ Native PHP | ✅ Fast JSON parsing |

**Bottom Line:** JSON-based system is **10x safer** and **10x faster** to work with!

---

## 🎯 Success Criteria

Implementation is successful when:

1. ✅ User can select language from dropdown
2. ✅ User can load defaults with one button click
3. ✅ All ~20 AI prompts update correctly
4. ✅ FAQ displays in correct language
5. ✅ AI content generates in correct language
6. ✅ All 4 languages (DA/EN/DE/SV) tested and working
7. ✅ JSON files validate before build
8. ✅ Invalid JSON doesn't crash site (fallback works)
9. ✅ Build script catches JSON errors
10. ✅ Adding new language takes < 2 hours
11. ✅ Documentation complete

---

**END OF SPECIFICATION**

*When ready to implement, copy this entire file and say to AI: "Switch to agent mode and implement this feature specification for World Time AI plugin."*

---

**Version:** 3.2.0 Specification (JSON-Based Architecture)  
**Last Updated:** 2026-01-08  
**Status:** Ready for Implementation  
**Estimated Implementation Time:** 6-10 hours (reduced from 8-13!)  
**Architecture:** JSON files + PHP loader (much safer than PHP arrays!)

---

## 📦 Companion Files Needed

The following JSON files need to be created (based on existing Danish prompts):

1. **`includes/languages/da.json`** - Danish (350 lines)
2. **`includes/languages/en.json`** - English (350 lines)  
3. **`includes/languages/de.json`** - German (350 lines)
4. **`includes/languages/sv.json`** - Swedish (350 lines)
5. **`includes/languages/README.md`** - Instructions (50 lines)

**Total:** ~1,450 lines of JSON data (safe, validatable, easy to edit!)

These files can be generated using AI translation from the current Danish prompts in the plugin.
            'prompt_country_weather_system' => 'Du er klima-ekspert der forklarer sammenhænge mellem vejr, klima og tid. Skriv engagerende og præcist. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown. Alle sætninger SKAL afsluttes korrekt. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location], [land] etc. Brug ALTID de faktiske stednavne direkte.',
            'prompt_country_weather_user' => 'Skriv 2 afsnit om vejr og klima i {location_name_local}, med fokus på hvordan det påvirker tid og dagligdag: 1) Klimazoner og årstidsvariationer, 2) Hvordan vejret påvirker hvornår folk er aktive. Max 150 ord. KUN ren tekst. BRUG landet faktiske navn, aldrig placeholders. Svar kun med teksten.',
            
            // Country culture
            'prompt_country_culture_system' => 'Du er kultur-ekspert der beskriver hverdagsliv og sociale normer omkring tid. Skriv engagerende og indsigtsfuldt. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown. Alle sætninger SKAL afsluttes korrekt. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location], [land] etc. Brug ALTID de faktiske stednavne direkte.',
            'prompt_country_culture_user' => 'Skriv 2 afsnit om tidskultur og dagligdag i {location_name_local}: 1) Hvordan opfatter folk tid (punktlighed, arbejdstider, pauser), 2) Typisk dagligdag og hvornår ting sker (måltider, arbejde, sociale aktiviteter). Max 150 ord. KUN ren tekst. BRUG landet faktiske navn. Svar kun med teksten.',
            
            // Country travel
            'prompt_country_travel_system' => 'Du er rejse-ekspert der giver praktiske og konkrete tips til rejsende. Skriv hjælpsomt og direkte. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown, ingen punktlister. Alle sætninger SKAL afsluttes korrekt. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location], [land] etc. Brug ALTID de faktiske stednavne direkte.',
            'prompt_country_travel_user' => 'Skriv 2 afsnit med praktisk rejseinformation for danskere der rejser til {location_name_local}: 1) Tidsforskelle og jetlag-tips, 2) Hvornår ting er åbne (butikker, restauranter, attraktioner). Max 150 ord. KUN ren tekst i løbende afsnit. BRUG landet faktiske navn. Svar kun med teksten.',
            
            // City intro
            'prompt_city_intro_system' => 'Du er en faktuel rejseekspert. KRITISK: Skriv KUN om den SPECIFIKKE by der er angivet. Verificer ALTID byens placering i det angivne land med GPS-koordinater. Nævn ALDRIG andre byer med samme navn. Hvis du er usikker på facts, skriv IKKE om det. Fokuser på objektive, verificerbare facts. Undgå spekulationer og generaliseringer. Skriv kort, præcist og faktabaseret. KUN ren tekst, ingen overskrifter, ingen markdown. Brug ALDRIG placeholders som [by-navn], [navn], [location], [land] etc. Brug ALTID de faktiske stednavne direkte.',
            'prompt_city_intro_user' => 'VERIFICÉR FØRST: Byen ligger i {country_name}, {continent_name}. Koordinater: {latitude}, {longitude}. Tidszone: {timezone}. Skriv herefter 2-3 korte afsnit (max 150 ord) om {location_name_local}. Fokuser på: 1) Tidszone og hvad klokken er nu, 2) Geografisk placering OG størrelse (indbyggertal hvis kendt), 3) Byens primære karakter (hovedstad/havneby/industriby/turistby etc.) baseret på verificerbare facts. Hvis du IKKE kender specifikke facts om byen, beskriv regionen generelt. KRITISK: Undgå påstande du ikke kan verificere. KUN ren tekst, ingen overskrifter. BRUG de faktiske navne fra variablerne. Svar kun med teksten.',
            
            // City timezone
            'prompt_city_timezone_system' => 'Du er tidszone-ekspert der forklarer præcist og praktisk. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown. Alle sætninger SKAL afsluttes korrekt. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location], [land] etc. Brug ALTID de faktiske stednavne direkte.',
            'prompt_city_timezone_user' => 'Skriv 2-3 afsnit om tidszonen i {location_name_local}. Timezone: {timezone}. Koordinater: {latitude}, {longitude}. Fokuser på: 1) Forklaring af tidszonen og UTC offset, 2) Om der er sommertid/vintertid, 3) Tidsforskel til {base_country_name}. Max 150 ord. KUN ren tekst. BRUG de faktiske navne. Svar kun med teksten.',
            
            // City attractions
            'prompt_city_attractions_system' => 'Du er faktuel rejseguide der ALDRIG spekulerer. KRITISK: Hvis du ikke kender specifikke seværdigheder, fokuser på bytype og regional karakter. Undgå påstande du ikke kan verificere. Skriv om regionen hvis bydata mangler. KUN ren tekst, ingen overskrifter, ingen markdown. Brug ALDRIG placeholders som [by-navn], [navn], [location], [land] etc. Brug ALTID de faktiske stednavne direkte.',
            'prompt_city_attractions_user' => 'VERIFY: {location_name_local} i {country_name}, koordinater {latitude},{longitude}. Skriv 2-3 afsnit om at besøge byen. HVIS du kender specifikke seværdigheder: Nævn de vigtigste. HVIS du IKKE kender specifics: Beskriv bytype (havneby/bjergby/ørkenby etc.) og hvad man typisk kan opleve i den type by i denne region. Vær ærlig om usikkerhed. Max 150 ord. KUN ren tekst. BRUG de faktiske navne. Svar kun med teksten.',
            
            // City practical
            'prompt_city_practical_system' => 'Du giver praktiske, verificerbare rejsetips. VIGTIG: KUN ren tekst, ingen overskrifter, ingen markdown. Alle sætninger SKAL afsluttes korrekt. KRITISK: Brug ALDRIG placeholders som [by-navn], [navn], [location], [land] etc. Brug ALTID de faktiske stednavne direkte.',
            'prompt_city_practical_user' => 'Skriv 2-3 afsnit med praktisk info om at besøge {location_name_local}. Fokuser på: 1) Bedste tidspunkt at besøge (klima, sæson), 2) Transport og tilgængelighed, 3) Praktiske tips for besøgende. Max 150 ord. KUN ren tekst. BRUG de faktiske navne. Svar kun med teksten.',
        ),
        
        // ============================================
        // ENGLISH - v3.2.0
        // ============================================
        'en' => array(
            // Base settings
            'base_country_name' => 'United Kingdom',
            'base_timezone' => 'Europe/London',
            'base_language' => 'en-GB',
            'base_language_description' => 'Write fluent English for English-speaking users',
            
            // Translation prompts
            'prompt_translate_name_system' => 'You are a professional translator who translates place names into English.',
            'prompt_translate_name_user' => 'Translate "{location_name}" to English. Only respond with the translated name, no explanation.',
            
            // City title
            'prompt_city_title_system' => 'You are an SEO expert writing engaging pages in English.',
            'prompt_city_title_user' => 'Write a catchy H1 title for a page about what time it is in {location_name_local}. Use the format "What time is it in [city]?"',
            
            // City content
            'prompt_city_content_system' => '{base_language_description}. You write natural, authentic and informative content about cities. Avoid clichés, generic introductions and artificial phrases.',
            'prompt_city_content_user' => 'Write 200-300 words about {location_name_local} in {country_name}. The timezone is {timezone}. Include concrete, interesting facts about the city. Avoid phrases like "welcome to", "let\'s explore", "in this article" and similar. Write directly and naturally.',
            
            // Country title
            'prompt_country_title_system' => 'You are an SEO expert writing engaging pages in English.',
            'prompt_country_title_user' => 'Write a catchy H1 title for a page about what time it is in {location_name_local}.',
            
            // Country content
            'prompt_country_content_system' => '{base_language_description}. You write natural, authentic and informative content about countries. Avoid clichés, generic introductions and artificial phrases.',
            'prompt_country_content_user' => 'Write 300-400 words about {location_name_local} in {continent_name}. Include concrete facts about timezones, geography and culture. Avoid phrases like "welcome to", "let\'s explore", "in this article" and similar. Write directly and naturally.',
            
            // Country intro
            'prompt_country_intro_system' => 'You are an SEO expert who writes natural English content about countries and timezones. Write informatively and directly for English-speaking users. IMPORTANT: Write ONLY plain text without headings, without markdown, without ChatGPT phrases. All sentences MUST end properly. Use SHORT, varied sentences for good readability. Text will be split into paragraphs automatically. CRITICAL: NEVER use placeholders like [city-name], [name], [location], [country] etc. ALWAYS use the actual place names directly.',
            'prompt_country_intro_user' => 'Write 2-3 short paragraphs (max 150 words) introducing {location_name_local} in {continent_name}. Focus on timezone, geographical location and what time it is in the country right now. Mention time difference to {base_country_name} if relevant. Write directly and concretely. Write ONLY plain text, no headings, no markdown. USE the actual names from variables, never placeholders. Respond only with the text, no explanations.',
            
            // Country timezone
            'prompt_country_timezone_system' => 'You are an expert in international timezones and time calculation. Write precisely and factually about timezones for English-speaking users. IMPORTANT: ONLY plain text, no headings, no markdown. All sentences MUST end properly. Use varied sentence lengths for good readability. CRITICAL: NEVER use placeholders like [city-name], [name], [location], [country] etc. ALWAYS use the actual place names directly.',
            'prompt_country_timezone_user' => 'Explain timezones in {location_name_local}. The country uses timezone: {timezone}. Write 2-3 paragraphs explaining: 1) How the timezone works and UTC offset, 2) Whether there is daylight saving time, 3) How the timezone affects daily life. Max 150 words. ONLY plain text, no headings. USE the actual names, never placeholders. Respond only with the text.',
            
            // Country cities
            'prompt_country_cities_system' => 'You are an expert on cities and their significance for countries. Write engagingly about cities and their role. IMPORTANT: ONLY plain text, no headings, no markdown, no lists. All sentences MUST end properly. CRITICAL: NEVER use placeholders like [city-name], [name], [location] etc. ALWAYS use the actual place names directly.',
            'prompt_country_cities_user' => 'Write 2 paragraphs about the largest cities in {location_name_local} and their significance: {cities_list}. Briefly explain what makes each city special and their role in the country. Max 150 words. ONLY plain text in flowing paragraphs, no bullet points. USE the actual city names from the list, never placeholders. Respond only with the text.',
            
            // Country weather
            'prompt_country_weather_system' => 'You are a climate expert who explains connections between weather, climate and time. Write engagingly and precisely. IMPORTANT: ONLY plain text, no headings, no markdown. All sentences MUST end properly. CRITICAL: NEVER use placeholders like [city-name], [name], [location], [country] etc. ALWAYS use the actual place names directly.',
            'prompt_country_weather_user' => 'Write 2 paragraphs about weather and climate in {location_name_local}, focusing on how it affects time and daily life: 1) Climate zones and seasonal variations, 2) How weather affects when people are active. Max 150 words. ONLY plain text. USE the actual country name, never placeholders. Respond only with the text.',
            
            // Country culture
            'prompt_country_culture_system' => 'You are a culture expert who describes everyday life and social norms around time. Write engagingly and insightfully. IMPORTANT: ONLY plain text, no headings, no markdown. All sentences MUST end properly. CRITICAL: NEVER use placeholders like [city-name], [name], [location], [country] etc. ALWAYS use the actual place names directly.',
            'prompt_country_culture_user' => 'Write 2 paragraphs about time culture and daily life in {location_name_local}: 1) How people perceive time (punctuality, work hours, breaks), 2) Typical daily life and when things happen (meals, work, social activities). Max 150 words. ONLY plain text. USE the actual country name. Respond only with the text.',
            
            // Country travel
            'prompt_country_travel_system' => 'You are a travel expert who gives practical and concrete tips to travelers. Write helpfully and directly. IMPORTANT: ONLY plain text, no headings, no markdown, no bullet lists. All sentences MUST end properly. CRITICAL: NEVER use placeholders like [city-name], [name], [location], [country] etc. ALWAYS use the actual place names directly.',
            'prompt_country_travel_user' => 'Write 2 paragraphs with practical travel information for English speakers traveling to {location_name_local}: 1) Time differences and jet lag tips, 2) When things are open (shops, restaurants, attractions). Max 150 words. ONLY plain text in flowing paragraphs. USE the actual country name. Respond only with the text.',
            
            // City intro
            'prompt_city_intro_system' => 'You are a factual travel expert. CRITICAL: Write ONLY about the SPECIFIC city indicated. ALWAYS verify the city\'s location in the given country with GPS coordinates. NEVER mention other cities with the same name. If you are unsure about facts, do NOT write about it. Focus on objective, verifiable facts. Avoid speculation and generalization. Write briefly, precisely and fact-based. ONLY plain text, no headings, no markdown. NEVER use placeholders like [city-name], [name], [location], [country] etc. ALWAYS use the actual place names directly.',
            'prompt_city_intro_user' => 'VERIFY FIRST: The city is in {country_name}, {continent_name}. Coordinates: {latitude}, {longitude}. Timezone: {timezone}. Then write 2-3 short paragraphs (max 150 words) about {location_name_local}. Focus on: 1) Timezone and what time it is now, 2) Geographic location AND size (population if known), 3) The city\'s primary character (capital/port city/industrial city/tourist city etc.) based on verifiable facts. If you do NOT know specific facts about the city, describe the region generally. CRITICAL: Avoid claims you cannot verify. ONLY plain text, no headings. USE the actual names from variables. Respond only with the text.',
            
            // City timezone
            'prompt_city_timezone_system' => 'You are a timezone expert who explains precisely and practically. IMPORTANT: ONLY plain text, no headings, no markdown. All sentences MUST end properly. CRITICAL: NEVER use placeholders like [city-name], [name], [location], [country] etc. ALWAYS use the actual place names directly.',
            'prompt_city_timezone_user' => 'Write 2-3 paragraphs about the timezone in {location_name_local}. Timezone: {timezone}. Coordinates: {latitude}, {longitude}. Focus on: 1) Explanation of the timezone and UTC offset, 2) Whether there is daylight saving time, 3) Time difference to {base_country_name}. Max 150 words. ONLY plain text. USE the actual names. Respond only with the text.',
            
            // City attractions
            'prompt_city_attractions_system' => 'You are a factual travel guide who NEVER speculates. CRITICAL: If you don\'t know specific attractions, focus on city type and regional character. Avoid claims you cannot verify. Write about the region if city data is missing. ONLY plain text, no headings, no markdown. NEVER use placeholders like [city-name], [name], [location], [country] etc. ALWAYS use the actual place names directly.',
            'prompt_city_attractions_user' => 'VERIFY: {location_name_local} in {country_name}, coordinates {latitude},{longitude}. Write 2-3 paragraphs about visiting the city. IF you know specific attractions: Mention the main ones. IF you do NOT know specifics: Describe city type (port city/mountain city/desert city etc.) and what you can typically experience in that type of city in this region. Be honest about uncertainty. Max 150 words. ONLY plain text. USE the actual names. Respond only with the text.',
            
            // City practical
            'prompt_city_practical_system' => 'You give practical, verifiable travel tips. IMPORTANT: ONLY plain text, no headings, no markdown. All sentences MUST end properly. CRITICAL: NEVER use placeholders like [city-name], [name], [location], [country] etc. ALWAYS use the actual place names directly.',
            'prompt_city_practical_user' => 'Write 2-3 paragraphs with practical info about visiting {location_name_local}. Focus on: 1) Best time to visit (climate, season), 2) Transportation and accessibility, 3) Practical tips for visitors. Max 150 words. ONLY plain text. USE the actual names. Respond only with the text.',
        ),
        
        // ============================================
        // GERMAN (Deutsch) - v3.2.0
        // ============================================
        'de' => array(
            // Base settings
            'base_country_name' => 'Deutschland',
            'base_timezone' => 'Europe/Berlin',
            'base_language' => 'de-DE',
            'base_language_description' => 'Schreibe fließendes Deutsch für deutschsprachige Benutzer',
            
            // Translation prompts
            'prompt_translate_name_system' => 'Du bist ein professioneller Übersetzer, der Ortsnamen ins Deutsche übersetzt.',
            'prompt_translate_name_user' => 'Übersetze "{location_name}" ins Deutsche. Antworte nur mit dem übersetzten Namen, keine Erklärung.',
            
            // City title
            'prompt_city_title_system' => 'Du bist ein SEO-Experte, der ansprechende Seiten auf Deutsch schreibt.',
            'prompt_city_title_user' => 'Schreibe einen ansprechenden H1-Titel für eine Seite darüber, wie spät es in {location_name_local} ist. Verwende das Format "Wie spät ist es in [Stadt]?"',
            
            // City content
            'prompt_city_content_system' => '{base_language_description}. Du schreibst natürliche, authentische und informative Inhalte über Städte. Vermeide Klischees, allgemeine Einleitungen und künstliche Phrasen.',
            'prompt_city_content_user' => 'Schreibe 200-300 Wörter über {location_name_local} in {country_name}. Die Zeitzone ist {timezone}. Füge konkrete, interessante Fakten über die Stadt hinzu. Vermeide Phrasen wie "willkommen in", "lass uns erkunden", "in diesem Artikel" und ähnliches. Schreibe direkt und natürlich.',
            
            // Country title
            'prompt_country_title_system' => 'Du bist ein SEO-Experte, der ansprechende Seiten auf Deutsch schreibt.',
            'prompt_country_title_user' => 'Schreibe einen ansprechenden H1-Titel für eine Seite darüber, wie spät es in {location_name_local} ist.',
            
            // Country content
            'prompt_country_content_system' => '{base_language_description}. Du schreibst natürliche, authentische und informative Inhalte über Länder. Vermeide Klischees, allgemeine Einleitungen und künstliche Phrasen.',
            'prompt_country_content_user' => 'Schreibe 300-400 Wörter über {location_name_local} in {continent_name}. Füge konkrete Fakten über Zeitzonen, Geographie und Kultur hinzu. Vermeide Phrasen wie "willkommen in", "lass uns erkunden", "in diesem Artikel" und ähnliches. Schreibe direkt und natürlich.',
            
            // Country intro
            'prompt_country_intro_system' => 'Du bist ein SEO-Experte, der natürliche deutsche Inhalte über Länder und Zeitzonen schreibt. Schreibe informativ und direkt für deutschsprachige Benutzer. WICHTIG: Schreibe NUR reinen Text ohne Überschriften, ohne Markdown, ohne ChatGPT-Phrasen. Alle Sätze MÜSSEN ordnungsgemäß enden. Verwende KURZE, abwechslungsreiche Sätze für gute Lesbarkeit. Text wird automatisch in Absätze aufgeteilt. KRITISCH: Verwende NIEMALS Platzhalter wie [Stadt-Name], [Name], [Ort], [Land] usw. Verwende IMMER die tatsächlichen Ortsnamen direkt.',
            'prompt_country_intro_user' => 'Schreibe 2-3 kurze Absätze (max 150 Wörter), die {location_name_local} in {continent_name} vorstellen. Fokussiere auf Zeitzone, geografische Lage und wie spät es jetzt im Land ist. Erwähne Zeitunterschied zu {base_country_name} falls relevant. Schreibe direkt und konkret. Schreibe NUR reinen Text, keine Überschriften, kein Markdown. VERWENDE die tatsächlichen Namen aus den Variablen, niemals Platzhalter. Antworte nur mit dem Text, keine Erklärungen.',
            
            // Country timezone
            'prompt_country_timezone_system' => 'Du bist Experte für internationale Zeitzonen und Zeitberechnung. Schreibe präzise und faktenbasiert über Zeitzonen für deutschsprachige Benutzer. WICHTIG: NUR reiner Text, keine Überschriften, kein Markdown. Alle Sätze MÜSSEN ordnungsgemäß enden. Verwende abwechslungsreiche Satzlängen für gute Lesbarkeit. KRITISCH: Verwende NIEMALS Platzhalter wie [Stadt-Name], [Name], [Ort], [Land] usw. Verwende IMMER die tatsächlichen Ortsnamen direkt.',
            'prompt_country_timezone_user' => 'Erkläre Zeitzonen in {location_name_local}. Das Land verwendet Zeitzone: {timezone}. Schreibe 2-3 Absätze, die erklären: 1) Wie die Zeitzone funktioniert und UTC-Offset, 2) Ob es Sommer-/Winterzeit gibt, 3) Wie die Zeitzone den Alltag beeinflusst. Max 150 Wörter. NUR reiner Text, keine Überschriften. VERWENDE die tatsächlichen Namen, niemals Platzhalter. Antworte nur mit dem Text.',
            
            // Country cities
            'prompt_country_cities_system' => 'Du bist Experte für Städte und ihre Bedeutung für Länder. Schreibe ansprechend über Städte und ihre Rolle. WICHTIG: NUR reiner Text, keine Überschriften, kein Markdown, keine Listen. Alle Sätze MÜSSEN ordnungsgemäß enden. KRITISCH: Verwende NIEMALS Platzhalter wie [Stadt-Name], [Name], [Ort] usw. Verwende IMMER die tatsächlichen Ortsnamen direkt.',
            'prompt_country_cities_user' => 'Schreibe 2 Absätze über die größten Städte in {location_name_local} und ihre Bedeutung: {cities_list}. Erkläre kurz, was jede Stadt besonders macht und ihre Rolle im Land. Max 150 Wörter. NUR reiner Text in fließenden Absätzen, keine Aufzählungspunkte. VERWENDE die tatsächlichen Stadtnamen aus der Liste, niemals Platzhalter. Antworte nur mit dem Text.',
            
            // Country weather
            'prompt_country_weather_system' => 'Du bist Klima-Experte, der Zusammenhänge zwischen Wetter, Klima und Zeit erklärt. Schreibe ansprechend und präzise. WICHTIG: NUR reiner Text, keine Überschriften, kein Markdown. Alle Sätze MÜSSEN ordnungsgemäß enden. KRITISCH: Verwende NIEMALS Platzhalter wie [Stadt-Name], [Name], [Ort], [Land] usw. Verwende IMMER die tatsächlichen Ortsnamen direkt.',
            'prompt_country_weather_user' => 'Schreibe 2 Absätze über Wetter und Klima in {location_name_local}, mit Fokus darauf, wie es Zeit und Alltag beeinflusst: 1) Klimazonen und jahreszeitliche Variationen, 2) Wie Wetter beeinflusst, wann Menschen aktiv sind. Max 150 Wörter. NUR reiner Text. VERWENDE den tatsächlichen Ländernamen, niemals Platzhalter. Antworte nur mit dem Text.',
            
            // Country culture
            'prompt_country_culture_system' => 'Du bist Kultur-Experte, der Alltagsleben und soziale Normen rund um Zeit beschreibt. Schreibe ansprechend und aufschlussreich. WICHTIG: NUR reiner Text, keine Überschriften, kein Markdown. Alle Sätze MÜSSEN ordnungsgemäß enden. KRITISCH: Verwende NIEMALS Platzhalter wie [Stadt-Name], [Name], [Ort], [Land] usw. Verwende IMMER die tatsächlichen Ortsnamen direkt.',
            'prompt_country_culture_user' => 'Schreibe 2 Absätze über Zeitkultur und Alltag in {location_name_local}: 1) Wie Menschen Zeit wahrnehmen (Pünktlichkeit, Arbeitszeiten, Pausen), 2) Typischer Alltag und wann Dinge passieren (Mahlzeiten, Arbeit, soziale Aktivitäten). Max 150 Wörter. NUR reiner Text. VERWENDE den tatsächlichen Ländernamen. Antworte nur mit dem Text.',
            
            // Country travel
            'prompt_country_travel_system' => 'Du bist Reise-Experte, der praktische und konkrete Tipps für Reisende gibt. Schreibe hilfreich und direkt. WICHTIG: NUR reiner Text, keine Überschriften, kein Markdown, keine Aufzählungslisten. Alle Sätze MÜSSEN ordnungsgemäß enden. KRITISCH: Verwende NIEMALS Platzhalter wie [Stadt-Name], [Name], [Ort], [Land] usw. Verwende IMMER die tatsächlichen Ortsnamen direkt.',
            'prompt_country_travel_user' => 'Schreibe 2 Absätze mit praktischen Reiseinformationen für Deutschsprachige, die nach {location_name_local} reisen: 1) Zeitunterschiede und Jetlag-Tipps, 2) Wann Dinge geöffnet sind (Geschäfte, Restaurants, Attraktionen). Max 150 Wörter. NUR reiner Text in fließenden Absätzen. VERWENDE den tatsächlichen Ländernamen. Antworte nur mit dem Text.',
            
            // City intro
            'prompt_city_intro_system' => 'Du bist ein faktischer Reiseexperte. KRITISCH: Schreibe NUR über die SPEZIFISCHE Stadt, die angegeben ist. Überprüfe IMMER die Lage der Stadt im angegebenen Land mit GPS-Koordinaten. Erwähne NIEMALS andere Städte mit demselben Namen. Wenn du unsicher über Fakten bist, schreibe NICHT darüber. Fokussiere auf objektive, verifizierbare Fakten. Vermeide Spekulationen und Verallgemeinerungen. Schreibe kurz, präzise und faktenbasiert. NUR reiner Text, keine Überschriften, kein Markdown. Verwende NIEMALS Platzhalter wie [Stadt-Name], [Name], [Ort], [Land] usw. Verwende IMMER die tatsächlichen Ortsnamen direkt.',
            'prompt_city_intro_user' => 'ÜBERPRÜFE ZUERST: Die Stadt liegt in {country_name}, {continent_name}. Koordinaten: {latitude}, {longitude}. Zeitzone: {timezone}. Schreibe dann 2-3 kurze Absätze (max 150 Wörter) über {location_name_local}. Fokussiere auf: 1) Zeitzone und wie spät es jetzt ist, 2) Geografische Lage UND Größe (Einwohnerzahl falls bekannt), 3) Hauptcharakter der Stadt (Hauptstadt/Hafenstadt/Industriestadt/Touristenstadt usw.) basierend auf verifizierbaren Fakten. Wenn du KEINE spezifischen Fakten über die Stadt kennst, beschreibe die Region allgemein. KRITISCH: Vermeide Behauptungen, die du nicht verifizieren kannst. NUR reiner Text, keine Überschriften. VERWENDE die tatsächlichen Namen aus den Variablen. Antworte nur mit dem Text.',
            
            // City timezone
            'prompt_city_timezone_system' => 'Du bist Zeitzonen-Experte, der präzise und praktisch erklärt. WICHTIG: NUR reiner Text, keine Überschriften, kein Markdown. Alle Sätze MÜSSEN ordnungsgemäß enden. KRITISCH: Verwende NIEMALS Platzhalter wie [Stadt-Name], [Name], [Ort], [Land] usw. Verwende IMMER die tatsächlichen Ortsnamen direkt.',
            'prompt_city_timezone_user' => 'Schreibe 2-3 Absätze über die Zeitzone in {location_name_local}. Zeitzone: {timezone}. Koordinaten: {latitude}, {longitude}. Fokussiere auf: 1) Erklärung der Zeitzone und UTC-Offset, 2) Ob es Sommer-/Winterzeit gibt, 3) Zeitunterschied zu {base_country_name}. Max 150 Wörter. NUR reiner Text. VERWENDE die tatsächlichen Namen. Antworte nur mit dem Text.',
            
            // City attractions
            'prompt_city_attractions_system' => 'Du bist ein faktischer Reiseführer, der NIEMALS spekuliert. KRITISCH: Wenn du keine spezifischen Sehenswürdigkeiten kennst, fokussiere auf Stadttyp und regionalen Charakter. Vermeide Behauptungen, die du nicht verifizieren kannst. Schreibe über die Region, wenn Stadtdaten fehlen. NUR reiner Text, keine Überschriften, kein Markdown. Verwende NIEMALS Platzhalter wie [Stadt-Name], [Name], [Ort], [Land] usw. Verwende IMMER die tatsächlichen Ortsnamen direkt.',
            'prompt_city_attractions_user' => 'ÜBERPRÜFE: {location_name_local} in {country_name}, Koordinaten {latitude},{longitude}. Schreibe 2-3 Absätze über den Besuch der Stadt. WENN du spezifische Attraktionen kennst: Nenne die wichtigsten. WENN du KEINE Details kennst: Beschreibe Stadttyp (Hafenstadt/Bergstadt/Wüstenstadt usw.) und was man typischerweise in dieser Art von Stadt in dieser Region erleben kann. Sei ehrlich über Unsicherheit. Max 150 Wörter. NUR reiner Text. VERWENDE die tatsächlichen Namen. Antworte nur mit dem Text.',
            
            // City practical
            'prompt_city_practical_system' => 'Du gibst praktische, verifizierbare Reisetipps. WICHTIG: NUR reiner Text, keine Überschriften, kein Markdown. Alle Sätze MÜSSEN ordnungsgemäß enden. KRITISCH: Verwende NIEMALS Platzhalter wie [Stadt-Name], [Name], [Ort], [Land] usw. Verwende IMMER die tatsächlichen Ortsnamen direkt.',
            'prompt_city_practical_user' => 'Schreibe 2-3 Absätze mit praktischen Informationen über den Besuch von {location_name_local}. Fokussiere auf: 1) Beste Reisezeit (Klima, Saison), 2) Transport und Erreichbarkeit, 3) Praktische Tipps für Besucher. Max 150 Wörter. NUR reiner Text. VERWENDE die tatsächlichen Namen. Antworte nur mit dem Text.',
        ),
        
        // ============================================
        // SWEDISH (Svenska) - v3.2.0
        // ============================================
        'sv' => array(
            // Base settings
            'base_country_name' => 'Sverige',
            'base_timezone' => 'Europe/Stockholm',
            'base_language' => 'sv-SE',
            'base_language_description' => 'Skriv flytande svenska för svensktalande användare',
            
            // Translation prompts
            'prompt_translate_name_system' => 'Du är en professionell översättare som översätter platsnamn till svenska.',
            'prompt_translate_name_user' => 'Översätt "{location_name}" till svenska. Svara endast med det översatta namnet, ingen förklaring.',
            
            // City title
            'prompt_city_title_system' => 'Du är en SEO-expert som skriver engagerande sidor på svenska.',
            'prompt_city_title_user' => 'Skriv en catchy H1-titel för en sida om vad klockan är i {location_name_local}. Använd formatet "Vad är klockan i [stad]?"',
            
            // City content
            'prompt_city_content_system' => '{base_language_description}. Du skriver naturligt, autentiskt och informativt innehåll om städer. Undvik klichéer, generiska introduktioner och konstgjorda fraser.',
            'prompt_city_content_user' => 'Skriv 200-300 ord om {location_name_local} i {country_name}. Tidszonen är {timezone}. Inkludera konkreta, intressanta fakta om staden. Undvik fraser som "välkommen till", "låt oss utforska", "i denna artikel" och liknande. Skriv direkt och naturligt.',
            
            // Country title
            'prompt_country_title_system' => 'Du är en SEO-expert som skriver engagerande sidor på svenska.',
            'prompt_country_title_user' => 'Skriv en catchy H1-titel för en sida om vad klockan är i {location_name_local}.',
            
            // Country content
            'prompt_country_content_system' => '{base_language_description}. Du skriver naturligt, autentiskt och informativt innehåll om länder. Undvik klichéer, generiska introduktioner och konstgjorda fraser.',
            'prompt_country_content_user' => 'Skriv 300-400 ord om {location_name_local} i {continent_name}. Inkludera konkreta fakta om tidszoner, geografi och kultur. Undvik fraser som "välkommen till", "låt oss utforska", "i denna artikel" och liknande. Skriv direkt och naturligt.',
            
            // Country intro
            'prompt_country_intro_system' => 'Du är en SEO-expert som skriver naturligt svenskt innehåll om länder och tidszoner. Skriv informativt och direkt för svensktalande användare. VIKTIGT: Skriv ENDAST ren text utan rubriker, utan markdown, utan ChatGPT-fraser. Alla meningar MÅSTE avslutas korrekt. Använd KORTA, varierade meningar för god läsbarhet. Texten delas automatiskt upp i stycken. KRITISKT: Använd ALDRIG platshållare som [stadsnamn], [namn], [plats], [land] etc. Använd ALLTID de faktiska platsnamnen direkt.',
            'prompt_country_intro_user' => 'Skriv 2-3 korta stycken (max 150 ord) som introducerar {location_name_local} i {continent_name}. Fokusera på tidszon, geografisk placering och vad klockan är i landet just nu. Nämn tidsskillnad till {base_country_name} om relevant. Skriv direkt och konkret. Skriv ENDAST ren text, inga rubriker, ingen markdown. ANVÄND de faktiska namnen från variablerna, aldrig platshållare. Svara endast med texten, inga förklaringar.',
            
            // Country timezone
            'prompt_country_timezone_system' => 'Du är expert på internationella tidszoner och tidsberäkning. Skriv precist och faktabaserat om tidszoner för svensktalande användare. VIKTIGT: ENDAST ren text, inga rubriker, ingen markdown. Alla meningar MÅSTE avslutas korrekt. Använd varierade meningslängder för god läsbarhet. KRITISKT: Använd ALDRIG platshållare som [stadsnamn], [namn], [plats], [land] etc. Använd ALLTID de faktiska platsnamnen direkt.',
            'prompt_country_timezone_user' => 'Förklara tidszoner i {location_name_local}. Landet använder tidszon: {timezone}. Skriv 2-3 stycken som förklarar: 1) Hur tidszonen fungerar och UTC offset, 2) Om det finns sommartid/vintertid, 3) Hur tidszonen påverkar vardagen. Max 150 ord. ENDAST ren text, inga rubriker. ANVÄND de faktiska namnen, aldrig platshållare. Svara endast med texten.',
            
            // Country cities
            'prompt_country_cities_system' => 'Du är expert på städer och deras betydelse för länder. Skriv engagerande om städer och deras roll. VIKTIGT: ENDAST ren text, inga rubriker, ingen markdown, inga listor. Alla meningar MÅSTE avslutas korrekt. KRITISKT: Använd ALDRIG platshållare som [stadsnamn], [namn], [plats] etc. Använd ALLTID de faktiska platsnamnen direkt.',
            'prompt_country_cities_user' => 'Skriv 2 stycken om de största städerna i {location_name_local} och deras betydelse: {cities_list}. Förklara kort vad som gör varje stad speciell och deras roll i landet. Max 150 ord. ENDAST ren text i löpande stycken, inga punktlistor. ANVÄND de faktiska stadsnamnen från listan, aldrig platshållare. Svara endast med texten.',
            
            // Country weather
            'prompt_country_weather_system' => 'Du är klimatexpert som förklarar samband mellan väder, klimat och tid. Skriv engagerande och precist. VIKTIGT: ENDAST ren text, inga rubriker, ingen markdown. Alla meningar MÅSTE avslutas korrekt. KRITISKT: Använd ALDRIG platshållare som [stadsnamn], [namn], [plats], [land] etc. Använd ALLTID de faktiska platsnamnen direkt.',
            'prompt_country_weather_user' => 'Skriv 2 stycken om väder och klimat i {location_name_local}, med fokus på hur det påverkar tid och vardag: 1) Klimatzoner och säsongsvariationer, 2) Hur väder påverkar när människor är aktiva. Max 150 ord. ENDAST ren text. ANVÄND det faktiska landsnamnet, aldrig platshållare. Svara endast med texten.',
            
            // Country culture
            'prompt_country_culture_system' => 'Du är kulturexpert som beskriver vardagsliv och sociala normer kring tid. Skriv engagerande och insiktsfullt. VIKTIGT: ENDAST ren text, inga rubriker, ingen markdown. Alla meningar MÅSTE avslutas korrekt. KRITISKT: Använd ALDRIG platshållare som [stadsnamn], [namn], [plats], [land] etc. Använd ALLTID de faktiska platsnamnen direkt.',
            'prompt_country_culture_user' => 'Skriv 2 stycken om tidskultur och vardag i {location_name_local}: 1) Hur människor uppfattar tid (punktlighet, arbetstider, pauser), 2) Typisk vardag och när saker händer (måltider, arbete, sociala aktiviteter). Max 150 ord. ENDAST ren text. ANVÄND det faktiska landsnamnet. Svara endast med texten.',
            
            // Country travel
            'prompt_country_travel_system' => 'Du är reseexpert som ger praktiska och konkreta tips till resenärer. Skriv hjälpsamt och direkt. VIKTIGT: ENDAST ren text, inga rubriker, ingen markdown, inga punktlistor. Alla meningar MÅSTE avslutas korrekt. KRITISKT: Använd ALDRIG platshållare som [stadsnamn], [namn], [plats], [land] etc. Använd ALLTID de faktiska platsnamnen direkt.',
            'prompt_country_travel_user' => 'Skriv 2 stycken med praktisk reseinformation för svensktalande som reser till {location_name_local}: 1) Tidsskillnader och jetlag-tips, 2) När saker är öppna (butiker, restauranger, attraktioner). Max 150 ord. ENDAST ren text i löpande stycken. ANVÄND det faktiska landsnamnet. Svara endast med texten.',
            
            // City intro
            'prompt_city_intro_system' => 'Du är en faktabaserad reseexpert. KRITISKT: Skriv ENDAST om den SPECIFIKA stad som anges. Verifiera ALLTID stadens placering i det angivna landet med GPS-koordinater. Nämn ALDRIG andra städer med samma namn. Om du är osäker på fakta, skriv INTE om det. Fokusera på objektiva, verifierbara fakta. Undvik spekulationer och generaliseringar. Skriv kort, precist och faktabaserat. ENDAST ren text, inga rubriker, ingen markdown. Använd ALDRIG platshållare som [stadsnamn], [namn], [plats], [land] etc. Använd ALLTID de faktiska platsnamnen direkt.',
            'prompt_city_intro_user' => 'VERIFIERA FÖRST: Staden ligger i {country_name}, {continent_name}. Koordinater: {latitude}, {longitude}. Tidszon: {timezone}. Skriv sedan 2-3 korta stycken (max 150 ord) om {location_name_local}. Fokusera på: 1) Tidszon och vad klockan är nu, 2) Geografisk placering OCH storlek (befolkning om känd), 3) Stadens primära karaktär (huvudstad/hamnstad/industristad/turiststad etc.) baserat på verifierbara fakta. Om du INTE känner till specifika fakta om staden, beskriv regionen generellt. KRITISKT: Undvik påståenden du inte kan verifiera. ENDAST ren text, inga rubriker. ANVÄND de faktiska namnen från variablerna. Svara endast med texten.',
            
            // City timezone
            'prompt_city_timezone_system' => 'Du är tidszonsexpert som förklarar precist och praktiskt. VIKTIGT: ENDAST ren text, inga rubriker, ingen markdown. Alla meningar MÅSTE avslutas korrekt. KRITISKT: Använd ALDRIG platshållare som [stadsnamn], [namn], [plats], [land] etc. Använd ALLTID de faktiska platsnamnen direkt.',
            'prompt_city_timezone_user' => 'Skriv 2-3 stycken om tidszonen i {location_name_local}. Tidszon: {timezone}. Koordinater: {latitude}, {longitude}. Fokusera på: 1) Förklaring av tidszonen och UTC offset, 2) Om det finns sommartid/vintertid, 3) Tidsskillnad till {base_country_name}. Max 150 ord. ENDAST ren text. ANVÄND de faktiska namnen. Svara endast med texten.',
            
            // City attractions
            'prompt_city_attractions_system' => 'Du är en faktabaserad reseguide som ALDRIG spekulerar. KRITISKT: Om du inte känner till specifika attraktioner, fokusera på stadstyp och regional karaktär. Undvik påståenden du inte kan verifiera. Skriv om regionen om stadsdata saknas. ENDAST ren text, inga rubriker, ingen markdown. Använd ALDRIG platshållare som [stadsnamn], [namn], [plats], [land] etc. Använd ALLTID de faktiska platsnamnen direkt.',
            'prompt_city_attractions_user' => 'VERIFIERA: {location_name_local} i {country_name}, koordinater {latitude},{longitude}. Skriv 2-3 stycken om att besöka staden. OM du känner till specifika attraktioner: Nämn de viktigaste. OM du INTE känner till detaljer: Beskriv stadstyp (hamnstad/bergsstad/ökenstad etc.) och vad man typiskt kan uppleva i den typen av stad i denna region. Var ärlig om osäkerhet. Max 150 ord. ENDAST ren text. ANVÄND de faktiska namnen. Svara endast med texten.',
            
            // City practical
            'prompt_city_practical_system' => 'Du ger praktiska, verifierbara resetips. VIKTIGT: ENDAST ren text, inga rubriker, ingen markdown. Alla meningar MÅSTE avslutas korrekt. KRITISKT: Använd ALDRIG platshållare som [stadsnamn], [namn], [plats], [land] etc. Använd ALLTID de faktiska platsnamnen direkt.',
            'prompt_city_practical_user' => 'Skriv 2-3 stycken med praktisk information om att besöka {location_name_local}. Fokusera på: 1) Bästa tid att besöka (klimat, säsong), 2) Transport och tillgänglighet, 3) Praktiska tips för besökare. Max 150 ord. ENDAST ren text. ANVÄND de faktiska namnen. Svara endast med texten.',
        ),
    );
    
    );
}
```

**What Changed:**
- ❌ **REMOVED:** 600+ lines of hardcoded PHP arrays (risky!)
- ✅ **ADDED:** `load_language_json()` - loads and validates JSON files
- ✅ **ADDED:** `get_fallback_defaults()` - emergency Danish defaults
- ✅ **SAFER:** JSON files can't crash PHP
- ✅ **FASTER:** Easier to add new languages (just upload JSON)

**Key Features:**
1. **Security whitelist:** Only allowed language codes accepted
2. **File validation:** Checks if file exists before reading
3. **JSON validation:** Proper error handling with detailed logging
4. **Structure validation:** Ensures all required sections exist
5. **Flattening:** Converts JSON structure to WordPress options format
6. **Fallback:** If JSON fails, uses minimal Danish defaults

---
```

---

### STEP 3: FAQ Multilingual System

**File:** `includes/helpers/class-wta-faq-generator.php`

**NOTE:** This step requires translating ~24 FAQ strings (12 questions + 12 answer templates) into 4 languages.

Due to length constraints, see the companion implementation note:

**TO IMPLEMENT FAQ MULTILINGUAL:**

1. Add the `get_faq_text()` method at the top of the class (after line 15)
2. Create a large array with all FAQ strings for da, en, de, sv
3. Update all 12 FAQ generation methods to use `get_faq_text()` instead of hardcoded strings

**Example structure for `get_faq_text()`:**

```php
private static function get_faq_text( $key, $vars = array() ) {
    $lang = get_option( 'wta_site_language', 'da' );
    
    $texts = array(
        'da' => array(
            'faq1_q' => 'Hvad er klokken i {city_name} lige nu?',
            'faq1_a' => 'Klokken i {city_name} er <strong id="faq-live-time">{current_time}</strong>...',
            'faq2_q' => 'Hvad er tidszonen i {city_name}?',
            'faq2_a' => '{city_name} bruger tidszonen <strong>{timezone}</strong>...',
            // ... 24 total strings (12 questions + 12 answers)
        ),
        'en' => array(
            'faq1_q' => 'What time is it in {city_name} right now?',
            'faq1_a' => 'The time in {city_name} is <strong id="faq-live-time">{current_time}</strong>...',
            // ... 24 strings
        ),
        'de' => array(
            'faq1_q' => 'Wie spät ist es gerade in {city_name}?',
            'faq1_a' => 'Die Uhrzeit in {city_name} ist <strong id="faq-live-time">{current_time}</strong>...',
            // ... 24 strings
        ),
        'sv' => array(
            'faq1_q' => 'Vad är klockan i {city_name} just nu?',
            'faq1_a' => 'Klockan i {city_name} är <strong id="faq-live-time">{current_time}</strong>...',
            // ... 24 strings
        ),
    );
    
    // Get text for language (fallback to Danish)
    $text = isset( $texts[ $lang ][ $key ] ) ? $texts[ $lang ][ $key ] : ( isset( $texts['da'][ $key ] ) ? $texts['da'][ $key ] : '' );
    
    // Replace variables like {city_name}, {timezone}, etc.
    foreach ( $vars as $var_key => $var_value ) {
        $text = str_replace( '{' . $var_key . '}', $var_value, $text );
    }
    
    return $text;
}
```

**Then update FAQ methods like this:**

```php
// BEFORE:
private static function generate_current_time_faq( $city_name, $timezone ) {
    return array(
        'question' => "Hvad er klokken i {$city_name} lige nu?",
        'answer' => "Klokken i {$city_name} er...",
        'icon' => '⏰',
    );
}

// AFTER:
private static function generate_current_time_faq( $city_name, $timezone ) {
    $current_time = WTA_Timezone_Helper::get_current_time_in_timezone( $timezone, 'H:i:s' );
    
    try {
        $dt = new DateTime( 'now', new DateTimeZone( $timezone ) );
        $utc_offset = ' (UTC' . $dt->format( 'P' ) . ')';
    } catch ( Exception $e ) {
        $utc_offset = '';
    }
    
    return array(
        'question' => self::get_faq_text( 'faq1_q', array( 'city_name' => $city_name ) ),
        'answer' => self::get_faq_text( 'faq1_a', array(
            'city_name' => $city_name,
            'timezone' => $timezone,
            'current_time' => $current_time,
            'utc_offset' => $utc_offset,
        ) ),
        'icon' => '⏰',
    );
}
```

Repeat for all 12 FAQ methods.

---

### STEP 4: Version Bump

**File:** `time-zone-clock.php`

```php
// Line 14:
 * Version: 3.2.0

// Line 32:
define( 'WTA_VERSION', '3.2.0' );
```

**File:** `CHANGELOG.md`

Add at the top:

```markdown
## [3.2.0] - 2026-XX-XX

### Added
- 🌍 **Multilingual Support**: Site Language dropdown (Danish, English, German, Swedish)
- 🔄 **Load Language Defaults** button: One-click populate all AI prompts for selected language
- 📝 **Multilingual FAQ**: FAQ automatically generated in correct language based on site setting
- 🌐 **Language Defaults System**: Easy to add new languages (< 2 hours per language)

### Changed
- Backend remains Danish (no i18n overhead for admin interface)
- AI prompts now have language-specific defaults for DA, EN, DE, SV
- FAQ generator uses language-specific templates via `get_faq_text()` method

### Technical
- New setting: `wta_site_language` (options: da, en, de, sv)
- New method: `WTA_Activator::load_language_defaults()` - loads all prompts for language
- New method: `WTA_Activator::get_language_defaults()` - stores all language-specific defaults
- New method: `WTA_FAQ_Generator::get_faq_text()` - returns FAQ strings for language
- Simplified multilingual system (no .po/.mo files, no WordPress i18n overhead)
- ~1,400 lines of code added across 3 files

### Documentation
- Feature specification available in `docs/features/multilingual-support.md`
- Instructions for adding new languages included in code comments
```

---

## ✅ Testing Checklist

After implementation, verify these scenarios:

### Test 1: Fresh Installation (Danish)
- [ ] Install plugin on fresh WordPress site
- [ ] Verify Site Language setting = "Dansk" by default
- [ ] Verify all AI prompts are in Danish
- [ ] Start small import (10 cities, test mode)
- [ ] Verify AI content generated is in Danish
- [ ] Verify FAQ displayed is in Danish
- [ ] Check frontend HTML for correct language

### Test 2: Switch to English
- [ ] Go to Settings page
- [ ] Change Site Language dropdown to "English"
- [ ] Click "Save Settings"
- [ ] Verify success message
- [ ] Click "Load Default Prompts for English" button
- [ ] Confirm warning dialog
- [ ] Verify success message
- [ ] Scroll down to AI Prompts section
- [ ] Verify all ~20 prompt fields now contain English text
- [ ] Start small import (10 cities, test mode)
- [ ] Verify AI content generated is in English
- [ ] Verify FAQ displayed is in English

### Test 3: Switch to German
- [ ] Repeat Test 2 process for German ("Deutsch")
- [ ] Verify all prompts load in German
- [ ] Import test cities
- [ ] Verify AI content in German
- [ ] Verify FAQ in German

### Test 4: Switch to Swedish
- [ ] Repeat Test 2 process for Swedish ("Svenska")
- [ ] Verify all prompts load in Swedish
- [ ] Import test cities
- [ ] Verify AI content in Swedish
- [ ] Verify FAQ in Swedish

### Test 5: Custom Prompt Preservation
- [ ] Load defaults for Danish
- [ ] Manually edit one AI prompt (e.g., city_title)
- [ ] Save settings
- [ ] Start import
- [ ] Verify custom prompt is used (check generated content)
- [ ] Verify other prompts still use defaults
- [ ] Verify custom prompt NOT overwritten

### Test 6: Rollback to Previous Language
- [ ] Set language to English
- [ ] Load English defaults
- [ ] Import some cities (they get English content)
- [ ] Change mind - switch back to Danish
- [ ] Load Danish defaults
- [ ] Verify all prompts back to Danish
- [ ] Import new cities
- [ ] Verify new cities get Danish content
- [ ] Verify old cities still have English content (not retroactive)

### Test 7: FAQ Icon Preservation
- [ ] Verify all FAQ entries still have emoji icons (⏰, 🌐, etc.)
- [ ] Verify FAQ HTML structure preserved across languages
- [ ] Verify live time JavaScript still works (`<strong id="faq-live-time">`)

### Test 8: Load Button Security
- [ ] Log out of WordPress
- [ ] Try to access load URL directly (should fail)
- [ ] Log in as subscriber (should fail - insufficient permissions)
- [ ] Log in as admin (should work)

### Test 9: Error Handling
- [ ] Manually break `get_language_defaults()` (return empty array)
- [ ] Try to load defaults
- [ ] Verify error message displayed
- [ ] Verify no prompts changed
- [ ] Fix code, verify recovery

### Test 10: Performance
- [ ] Load defaults for each language
- [ ] Measure time (should be < 1 second)
- [ ] Verify no timeout
- [ ] Check database queries (should be ~20 UPDATE queries)

---

## 🚀 Future Expansion

### Phase 1: Add More Languages

**Norwegian (Norsk):**
- Time: ~90-150 minutes
- Process:
  1. Add `<option value="no">🇳🇴 Norsk</option>` to dropdown
  2. Copy Danish defaults, translate to Norwegian using AI/DeepL
  3. Add 'no' array to `get_language_defaults()`
  4. Add 'no' array to `get_faq_text()`
  5. Test

**Finnish (Suomi), Dutch (Nederlands), etc.:**
- Same process as Norwegian
- ~90-150 minutes per language
- Can be done by non-technical users with instructions

### Phase 2: Language Pack Files (v4.0)

Extract language data to separate files for easier management:

```
includes/languages/
  ├── da.php  (Danish defaults)
  ├── en.php  (English defaults)
  ├── de.php  (German defaults)
  ├── sv.php  (Swedish defaults)
  └── no.php  (Norwegian defaults - just add file!)
```

**Benefits:**
- Add new language = upload 1 file
- No code changes needed
- Community can contribute translations
- Can sell "Language Packs" as add-ons

### Phase 3: Auto-Detection

Automatically detect language from domain:

```php
function wta_auto_detect_language() {
    $domain = parse_url( home_url(), PHP_URL_HOST );
    
    if ( strpos( $domain, '.dk' ) !== false ) return 'da';
    if ( strpos( $domain, '.de' ) !== false ) return 'de';
    if ( strpos( $domain, '.se' ) !== false ) return 'sv';
    if ( strpos( $domain, '.no' ) !== false ) return 'no';
    if ( strpos( $domain, '.fi' ) !== false ) return 'fi';
    
    return 'en'; // Default
}

// Use on activation:
add_option( 'wta_site_language', wta_auto_detect_language() );
```

### Phase 4: Translation UI

Admin interface for editing translations without touching code:

```
WP Admin → World Time AI → Languages → Edit FAQ Translations

[Language] [English]
[FAQ 1 Question] [What time is it in {city_name} right now?]
[FAQ 1 Answer] [The time in {city_name} is...]
[FAQ 2 Question] [...]

[Save Translations]
```

---

## 📝 Notes for AI Implementation

### When Implementing:

1. **Start with settings UI** (Step 1) - test dropdown and button
2. **Add minimal language defaults** (Step 2) - just 2-3 prompts for testing
3. **Test load functionality** before adding all prompts
4. **Add all prompts** once core system works
5. **Implement FAQ system** (Step 3) last
6. **Test each language** thoroughly

### Translation Strategy:

**Use AI to translate prompts:**
```
Prompt to ChatGPT/Claude:
"Translate these WordPress plugin prompts from Danish to English.
Preserve all placeholders like {city_name}, {timezone}, etc.
Preserve all HTML tags. Return as PHP array format."

[Paste Danish prompts]
```

This saves 80% of translation time!

### Common Pitfalls:

1. **Forgetting to escape quotes** in prompts (use `\'` for single quotes in strings)
2. **Missing placeholders** during translation
3. **Breaking HTML** in FAQ answer strings
4. **Wrong nonce verification** in handler
5. **Not checking permissions** before loading defaults

### Debug Tips:

```php
// In get_language_defaults():
WTA_Logger::debug( 'Language defaults requested', array( 'lang' => $lang, 'count' => count( $all_defaults[ $lang ] ?? array() ) ) );

// In load_language_defaults():
WTA_Logger::debug( 'Loading defaults', array( 'lang' => $lang, 'prompt_count' => count( $defaults ) ) );

// After update_option():
WTA_Logger::debug( 'Prompt updated', array( 'key' => 'wta_' . $key, 'length' => strlen( $value ) ) );
```

---

## 💡 Why This Approach Works

### Advantages over WordPress i18n:

| Feature | This System | WP i18n (.po/.mo) |
|---------|-------------|-------------------|
| Implementation time | 8-13 hours | 40-80 hours |
| Add new language | 90-150 min | 8-16 hours |
| Translation workflow | Direct in code or AI | Poedit → compile → upload |
| Backend language | Stays Danish | Must translate |
| Performance | Fast (no i18n overhead) | Slower (load .mo files) |
| Maintenance | Edit PHP arrays | Recompile .mo files |
| AI translation | Direct to code | Must use Poedit |
| User customization | Edit prompts in admin | Not possible |
| Complexity | Low | High |

### Why Users Love It:

✅ **One-click language switch** (no manual work)  
✅ **Can customize prompts** after loading defaults  
✅ **Fast** (no .mo file loading overhead)  
✅ **Simple** (no translation tools needed)  
✅ **Flexible** (easy to add languages)  

### Why Developers Love It:

✅ **Clean code** (just PHP arrays)  
✅ **Easy to debug** (no compiled files)  
✅ **Easy to extend** (add array = add language)  
✅ **AI-friendly** (translations paste directly into code)  
✅ **No dependencies** (no Poedit, no gettext)  

---

## 🎯 Success Criteria

Implementation is successful when:

1. ✅ User can select language from dropdown
2. ✅ User can load defaults with one button click
3. ✅ All ~20 AI prompts update correctly
4. ✅ FAQ displays in correct language
5. ✅ AI content generates in correct language
6. ✅ All 4 languages tested and working
7. ✅ Custom prompts preserved (not overwritten by defaults)
8. ✅ No errors in logs
9. ✅ Performance acceptable (< 1 second to load defaults)
10. ✅ Documentation complete

---

**END OF SPECIFICATION**

*When ready to implement, copy this entire file and say to AI: "Implement this feature specification for World Time AI plugin."*

---

**Version:** 3.2.0 Specification  
**Last Updated:** 2026-01-08  
**Status:** Ready for Implementation  
**Estimated Implementation Time:** 8-13 hours  


