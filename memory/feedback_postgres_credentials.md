---
name: PostgreSQL Docker credentials
description: Local PostgreSQL Docker container credentials for Nudge project
type: project
---

Local PostgreSQL runs in Docker with:
- User: root
- Password: Bright_737
- Port: 5432
- App DB: nudge_local
- Test DB: nudge_test (to be created)

**Why:** User spun up Docker container with these settings.
**How to apply:** Use these in .env and phpunit.xml for DB connections.
