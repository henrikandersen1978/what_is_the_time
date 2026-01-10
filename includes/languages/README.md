# World Time AI - Language Packs

This directory contains JSON-based language packs for the World Time AI plugin.

## Available Languages

- `da.json` - 🇩🇰 Dansk (Danish) - **Default**
- `en.json` - 🇬🇧 English
- `de.json` - 🇩🇪 Deutsch (German)
- `sv.json` - 🇸🇪 Svenska (Swedish)

## Adding a New Language

1. Copy `da.json` to `xx.json` (where xx is language code)
2. Translate all strings (keep placeholders like `{city_name}` intact!)
3. Validate JSON: `Get-Content includes\languages\xx.json | ConvertFrom-Json`
4. Add to whitelist in `class-wta-activator.php` line 403
5. Add dropdown option in `timezone-language.php`
6. Test!

See `docs/features/multilingual-support.md` for full documentation.


