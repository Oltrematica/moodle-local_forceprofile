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

The plugin hooks into Moodle's `after_require_login` callback, which fires on **every protected page load**. This ensures users cannot bypass the check by navigating directly to any URL.

### On each page load, the plugin:

1. Checks if the plugin is enabled
2. Skips guests, CLI scripts, and AJAX requests
3. Skips site admins and users with the `local/forceprofile:exempt` capability
4. Skips allowed pages (profile edit, logout, password change)
5. Checks session cache — if profile was already confirmed complete, no DB query is needed
6. Queries the database for the configured custom profile fields
7. If any field is empty or missing → redirects to the profile edit page with a warning

The user **cannot navigate anywhere else** until all configured fields are filled in.

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
| **Message** | Warning message shown when the user is redirected | *"You must complete your profile before proceeding..."* |
| **Redirect URL** | Local path where users are sent to complete their profile | `/user/edit.php` |

### Setup steps

1. **Enable** the plugin
2. **Add your field shortnames** — enter the `shortname` of each custom profile field you want to enforce, one per line
3. Optionally customize the **message** and **redirect URL**

### Recommended profile field settings

For best results, configure the custom profile fields you're enforcing as follows in **Site administration → Users → User profile fields**:

| Setting | Recommended value | Why |
|---------|-------------------|-----|
| Required | **No** | So admins are not blocked when creating users |
| Locked | **No** | So users can fill them in |
| Unique | **No** | So empty values don't conflict across users |

The plugin handles enforcement — you don't need Moodle's native "required" mechanism for these fields.

---

## Capability

| Capability | Description | Default roles |
|-----------|-------------|---------------|
| `local/forceprofile:exempt` | Exempt from forced profile completion | `manager`, `editingteacher` |

**Site administrators are always exempt** (they have all capabilities by default).

Assign this capability to additional roles via **Site administration → Users → Permissions → Define roles**.

---

## Example Use Case

A healthcare training platform needs every user to have a **tax code**, **profession**, and **discipline** on file. Admins batch-create accounts with just name and email. At first login, each user sees:

> You must complete your profile before proceeding. Please fill in all required fields.

They fill in the three fields, save, and proceed normally. Simple.

Configuration:
```
tax_code
profession
discipline
```

---

## Plugin Structure

```
local/forceprofile/
├── version.php                     Plugin metadata
├── settings.php                    Admin settings page
├── lib.php                         Core logic (after_require_login callback)
├── db/
│   └── access.php                  Capability definition
├── classes/
│   └── privacy/
│       └── provider.php            GDPR privacy provider (no data stored)
└── lang/
    ├── en/local_forceprofile.php   English strings
    └── it/local_forceprofile.php   Italian strings
```

---

## Technical Details

### Performance

The profile completeness check is **cached in `$SESSION`**. Once a user's profile is confirmed complete, no further database queries are made for the rest of their session. The cache is invalidated when the user visits the profile edit page, so changes take effect immediately.

### Security

- **SQL injection protection** — all queries use Moodle's Data Manipulation API with parameterized queries (`$DB->get_in_or_equal`)
- **XSS protection** — the notification message is sanitized via `format_string()`
- **Open redirect protection** — the redirect URL is validated as a local path (`PARAM_LOCALURL`)
- **Misconfiguration safety** — non-existent field shortnames are silently skipped with a `DEBUG_DEVELOPER` notice (no redirect loops from typos)

### Pages excluded from redirect

These pages are always accessible, even with an incomplete profile:

| Path | Reason |
|------|--------|
| `/user/edit.php` | Profile edit (where users fill in fields) |
| `/user/editadvanced.php` | Advanced profile edit |
| `/login/logout.php` | Users must always be able to log out |
| `/login/change_password.php` | Password change flow |
| `/lib/ajax/service.php` | AJAX web services |
| `/lib/ajax/service-nologin.php` | AJAX (no login) |

CLI scripts and AJAX requests are also excluded to prevent breaking background processes and JavaScript functionality.

### Privacy

This plugin **does not store any personal data**. It only reads existing custom profile field values to determine completeness. A GDPR-compliant `null_provider` is included.

---

## Contributing

Issues and pull requests are welcome on [GitHub](https://github.com/Oltrematica/moodle-local_forceprofile).

---

## License

This plugin is licensed under the [GNU GPL v3 or later](https://www.gnu.org/copyleft/gpl.html).

Copyright 2026 [Oltrematica](https://www.oltrematica.com)
