# External Libraries Required

World Time AI 2.0 requires two external libraries that must be downloaded separately.

## ⚠️ REQUIRED: Download These Libraries

### 1. Action Scheduler (Required)

**What it does:** Reliable background job processing

**Download from:** https://github.com/woocommerce/action-scheduler/releases

**Install to:** `includes/action-scheduler/`

**Version:** 3.7.0 or higher

**Steps:**
```bash
cd includes/
git clone https://github.com/woocommerce/action-scheduler.git
cd action-scheduler
git checkout 3.7.0
```

Or download ZIP and extract to `includes/action-scheduler/`

**Expected structure:**
```
includes/action-scheduler/
├── action-scheduler.php
├── classes/
├── functions.php
└── ...
```

### 2. Plugin Update Checker (Required)

**What it does:** Automatic plugin updates from GitHub

**Download from:** https://github.com/YahnisElsts/plugin-update-checker/releases

**Install to:** `includes/plugin-update-checker/`

**Version:** 5.0 or higher

**Steps:**
```bash
cd includes/
git clone https://github.com/YahnisElsts/plugin-update-checker.git
cd plugin-update-checker
git checkout v5.4
```

Or download ZIP and extract to `includes/plugin-update-checker/`

**Expected structure:**
```
includes/plugin-update-checker/
├── plugin-update-checker.php
├── Puc/
├── load-v5p6.php
└── ...
```

## ✅ Verification

After installing libraries, verify the structure:

```
world-time-ai/
├── includes/
│   ├── action-scheduler/
│   │   └── action-scheduler.php
│   ├── plugin-update-checker/
│   │   └── plugin-update-checker.php
│   ├── admin/
│   ├── core/
│   └── ...
├── world-time-ai.php
└── README.md
```

## 🔧 Alternative: Use Composer

If you prefer using Composer:

```bash
cd world-time-ai/
composer require woocommerce/action-scheduler:^3.7
composer require yahnis-elsts/plugin-update-checker:^5.4
```

Then update the require paths in `world-time-ai.php` to:
```php
require WTA_PLUGIN_DIR . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
require WTA_PLUGIN_DIR . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';
```

## ⚠️ Important Notes

1. **These libraries are NOT included in the repository** due to licensing and size
2. **Plugin will NOT work** without these libraries
3. Both libraries are **free and open-source**
4. Action Scheduler is also included in WooCommerce - if WooCommerce is active, that version will be used

## 📦 Why Not Bundled?

- **Size:** Together ~2MB, would make repo too large
- **Licensing:** Easier to comply with their licenses by downloading separately
- **Updates:** Easier to update libraries independently
- **Best Practice:** WordPress plugins should not bundle large third-party libraries

## 🆘 Troubleshooting

### "Call to undefined function as_schedule_recurring_action()"
→ Action Scheduler not installed correctly

### "Class YahnisElsts\PluginUpdateChecker not found"
→ Plugin Update Checker not installed correctly

### Solution
Verify the files exist at the exact paths shown above.

