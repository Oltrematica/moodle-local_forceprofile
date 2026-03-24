# Force Profile Completion — Moodle Local Plugin

[![Moodle 4.5+](https://img.shields.io/badge/Moodle-4.5%2B-orange)](https://moodle.org)
[![PHP 8.1+](https://img.shields.io/badge/PHP-8.1%2B-blue)](https://php.net)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-green.svg)](https://www.gnu.org/licenses/gpl-3.0)

**`local_forceprofile`** is a Moodle local plugin that ensures users complete their profile before accessing any page on the platform.

It works with **any custom profile field** — simply configure the shortnames of the fields you want to enforce, and the plugin takes care of the rest.

**Documentation:** [oltrematica.github.io/moodle-local_forceprofile](https://oltrematica.github.io/moodle-local_forceprofile)

---

## The Problem

Administrators often create user accounts with minimal data (name, email, password) and expect users to fill in the rest later. But Moodle has no built-in mechanism to **force** users to complete specific profile fields before they can use the platform.

Making fields "required" blocks admins from creating accounts without that data. Removing the requirement means users can ignore those fields indefinitely.

**This plugin solves that gap.**

---

## How It Works

```
Admin creates user          User logs in             Profile complete?
(minimal data) ──────────►  First page load  ──────► YES → Normal navigation
                                     │
                                     ▼
                                    NO
                                     │
                                     ▼
                            Redirect to /user/edit.php
                            with warning notification
                                     │
                                     ▼
                            User fills in fields → ✓
```

The plugin hooks into Moodle's `after_require_login` callback, which fires on **every protected page load**. Users cannot bypass the check by navigating directly to any URL.

### On each page load, the plugin:

1. Checks if the plugin is enabled
2. Skips guests, CLI scripts, and AJAX requests
3. Skips site admins and users with the `local/forceprofile:exempt` capability
4. Skips allowed pages (profile edit, logout, password change)
5. Checks session cache — if profile was already confirmed complete, no DB query is needed
6. Queries the database for the configured custom profile fields
7. Validates field values against optional regex patterns
8. If any field is empty, missing, or invalid → redirects to the profile edit page with a warning
9. Fires a `profile_blocked` event for logging

When the user completes all fields, the plugin:
- Caches the result in the session (no more DB queries)
- Records the completion timestamp in `local_forceprofile_compl`
- Fires a `profile_completed` event

---

## Features

- **Any custom field** — configure which profile fields to enforce via shortnames
- **Regex validation** — optional pattern validation per field (e.g., tax code format)
- **Admin status page** — dashboard showing users with incomplete profiles
- **Event logging** — `profile_blocked` and `profile_completed` events in Moodle logs
- **Completion tracking** — stores when each user completed their profile
- **Session caching** — minimal DB overhead after first check
- **GDPR compliant** — full privacy provider with export/delete support
- **Bilingual** — English and Italian language packs included

---

## Requirements

| Requirement | Minimum version |
|-------------|----------------|
| Moodle      | 4.5+           |
| PHP         | 8.1+           |

---

## Installation

### Option 1 — Git

```bash
cd /path/to/moodle/local
git clone https://github.com/Oltrematica/moodle-local_forceprofile.git forceprofile
```

### Option 2 — Download ZIP

1. Download the latest release from [Releases](https://github.com/Oltrematica/moodle-local_forceprofile/releases)
2. Extract into `local/forceprofile/`

### Finalize installation

1. Log in as site administrator
2. Go to **Site administration → Notifications** — Moodle will detect and install the plugin
3. Configure the plugin at **Site administration → Plugins → Local plugins → Force Profile Completion**

---

## Configuration

Navigate to **Site administration → Plugins → Local plugins → Force Profile Completion**.

| Setting | Description | Default |
|---------|-------------|---------|
| **Enable** | Activate or deactivate the plugin | Disabled |
| **Fields to check** | Custom profile field shortnames, one per line | *(empty)* |
| **Validation patterns** | Optional regex per field: `shortname:/pattern/` one per line | *(empty)* |
| **Message** | Warning message shown when the user is redirected | *"You must complete your profile..."* |
| **Redirect URL** | Local path where users are sent to complete their profile | `/user/edit.php` |

### Validation patterns example

```
CF:/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/i
phone:/^\+?[0-9]{8,15}$/
```

Each line is `shortname:/regex/`. Fields without a pattern only need to be non-empty. Fields with a pattern must also match the regex.

### Recommended profile field settings

For the fields you want to enforce, configure them in **Site administration → Users → User profile fields**:

| Setting | Recommended value | Why |
|---------|-------------------|-----|
| Required | **No** | So admins are not blocked when creating users |
| Locked | **No** | So users can fill them in |
| Unique | **No** | So empty values don't conflict across users |

---

## Admin Status Page

Navigate to **Site administration → Plugins → Local plugins → Profile Completion Status**.

The status page shows:
- **Summary counters** — total users, incomplete, complete
- **User table** — username, full name, email, missing fields (as badges), last access
- **Actions** — link to view profile and edit profile for each user
- **Pagination** — for large user bases

Requires the `local/forceprofile:viewstatus` capability (default: manager role).

---

## Event Logging

The plugin fires two custom events, visible in **Site administration → Reports → Logs**:

| Event | When | Data |
|-------|------|------|
| `\local_forceprofile\event\profile_blocked` | User is redirected | User ID, list of incomplete fields |
| `\local_forceprofile\event\profile_completed` | User completes all fields | User ID, completion record ID |

These events can be used for:
- Monitoring how many users are being blocked
- Triggering external integrations via event observers
- Auditing when users completed their profiles

---

## Completion Tracking

When a user fills in all required fields and passes validation, the plugin stores a record in the `local_forceprofile_compl` table:

| Column | Description |
|--------|-------------|
| `userid` | The user who completed the profile (unique) |
| `timecompleted` | Unix timestamp of completion |

This data can be queried for reports or exported via the Moodle privacy API.

---

## Capabilities

| Capability | Description | Default roles |
|-----------|-------------|---------------|
| `local/forceprofile:exempt` | Exempt from forced profile completion | `manager`, `editingteacher` |
| `local/forceprofile:viewstatus` | Access the status page | `manager` |

**Site administrators are always exempt** (they have all capabilities by default).

---

## Plugin Structure

```
local/forceprofile/
├── version.php                          Plugin metadata
├── settings.php                         Admin settings + external page registration
├── lib.php                              Core logic: callback, validation, completion
├── status.php                           Admin status page
├── db/
│   ├── access.php                       Capability definitions
│   ├── install.xml                      Database schema
│   └── upgrade.php                      Upgrade steps
├── classes/
│   ├── event/
│   │   ├── profile_blocked.php          Event: user blocked
│   │   └── profile_completed.php        Event: user completed profile
│   └── privacy/
│       └── provider.php                 GDPR privacy provider
├── tests/
│   └── lib_test.php                     PHPUnit tests
└── lang/
    ├── en/local_forceprofile.php         English strings
    └── it/local_forceprofile.php         Italian strings
```

---

## Technical Details

### Performance

The profile check is **cached in `$SESSION`**. Once confirmed complete, no further DB queries are made for the session. The cache is invalidated when the user visits the profile edit page.

### Security

- **SQL injection safe** — all queries use Moodle's parameterized API
- **XSS protected** — messages sanitized via `format_string()`
- **Open redirect safe** — redirect URL validated as `PARAM_LOCALURL`
- **Typo resilient** — non-existent shortnames skipped with `DEBUG_DEVELOPER` notice
- **Invalid regex safe** — broken patterns are skipped and logged

### Pages excluded from redirect

| Path | Reason |
|------|--------|
| `/user/edit.php` | Profile edit (where users fill in fields) |
| `/user/editadvanced.php` | Advanced profile edit |
| `/login/logout.php` | Users must always be able to log out |
| `/login/change_password.php` | Password change flow |
| `/lib/ajax/service.php` | AJAX web services |
| `/lib/ajax/service-nologin.php` | AJAX (no login) |

CLI scripts and AJAX requests are also excluded.

---

## Testing

The plugin includes PHPUnit tests covering:

- Complete/incomplete field detection
- Missing data records
- Non-existent shortname handling
- Regex validation (pass/fail)
- Validation pattern parsing
- Invalid regex handling
- Completion timestamp recording
- Event firing (profile_completed on first completion only)

Run tests (from Moodle root):

```bash
php vendor/bin/phpunit --testsuite local_forceprofile_testsuite
```

Or directly:

```bash
php vendor/bin/phpunit local/forceprofile/tests/lib_test.php
```

---

## Contributing

Issues and pull requests are welcome on [GitHub](https://github.com/Oltrematica/moodle-local_forceprofile).

---

## License

This plugin is licensed under the [GNU GPL v3 or later](https://www.gnu.org/copyleft/gpl.html).

Copyright 2026 [Oltrematica](https://www.oltrematica.com)
