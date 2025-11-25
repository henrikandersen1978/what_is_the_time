# World Time AI 2.0

WordPress plugin for displaying current local time worldwide with AI-generated content in Danish.

## Status
🚧 **In Development** - Complete rewrite from v1.0

## Documentation
See [Requirements Specification](docs/world-time-ai-requirements.md) for complete project requirements.

## Key Features (Planned)
- ⏰ Real-time clock display for 150,000+ cities worldwide
- 🌍 Hierarchical location pages (Continents → Countries → Cities)
- 🤖 AI-generated Danish content for all locations
- 📍 Timezone resolution via TimeZoneDB API
- 🔄 Automatic updates from GitHub
- ⚡ Action Scheduler for reliable background processing

## Technology Stack
- WordPress 6.8+
- PHP 8.4+
- Action Scheduler
- OpenAI API (GPT-4)
- TimeZoneDB API
- Yoast SEO Integration

## Project Structure
```
world-time-ai/
├── README.md
└── docs/
    └── world-time-ai-requirements.md
```

## Development
This is a ground-up rewrite incorporating lessons learned from v1.0:
- ✅ Translation before post creation (Danish URLs from start)
- ✅ Persistent data storage in `wp-content/uploads/`
- ✅ Action Scheduler instead of WP Cron
- ✅ Correct population filtering logic
- ✅ Settings persistence across updates

## License
Proprietary

---

**Version**: 2.0.0-dev  
**Author**: Your Name  
**Last Updated**: November 25, 2025

