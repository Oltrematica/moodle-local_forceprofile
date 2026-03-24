# Force Profile Completion — Moodle Local Plugin

**`local_forceprofile`** is a Moodle local plugin that forces users to complete their profile before accessing any page on the platform.

It is designed for organizations where administrators create user accounts manually without all required information, and users must fill in the missing profile fields at their first login.

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

### Checks performed on each page load:

1. Is the plugin enabled?
2. Is the user logged in and not a guest?
3. Is the user an admin or exempt via capability?
4. Is the user already on an allowed page (profile edit, logout, password change)?
5. Are all configured profile fields filled in?

If any configured field is empty or missing, the user is redirected to the profile edit page with a warning message. The user **cannot navigate anywhere else** until all fields are completed.

---

## Requirements

| Requirement | Version |
|------------|---------|
| Moodle     | 4.5+    |
| PHP        | 8.1+    |

---

## Installation

### Option 1 — Git clone

```bash
cd /path/to/moodle/local
git clone https://github.com/Oltrematica/moodle-local_forceprofile.git forceprofile
```

### Option 2 — Download

1. Download the latest release from [GitHub Releases](https://github.com/Oltrematica/moodle-local_forceprofile/releases)
2. Extract into `local/forceprofile/`

### Then

1. Log in as site administrator
2. Go to **Site administration → Notifications** to trigger the plugin installation
3. Configure the plugin at **Site administration → Plugins → Local plugins → Force Profile Completion**

---

## Configuration

Navigate to **Site administration → Plugins → Local plugins → Force Profile Completion**.

| Setting | Description | Default |
|---------|-------------|---------|
| **Enable** | Activate or deactivate the plugin | Enabled |
| **Fields to check** | Custom profile field shortnames, one per line | `CF`<br>`professione`<br>`disciplina` |
| **Message** | Warning message shown to users | *"Per procedere è necessario completare il profilo..."* |
| **Redirect URL** | Page where users are redirected | `/user/edit.php` |

### Recommended profile field configuration

After installing the plugin, adjust your custom profile fields in **Site administration → Users → User profile fields**:

| Field | Required | Locked | Notes |
|-------|----------|--------|-------|
| Codice Fiscale (CF) | No | No | Remove uniqueness constraint if empty values cause duplicates |
| Professione | No | No | — |
| Disciplina | No | No | — |

Setting fields to **not required** and **not locked** allows admins to skip them during user creation, while still letting users fill them in.

---

## Capability

The plugin defines one capability:

| Capability | Description | Default roles |
|-----------|-------------|---------------|
| `local/forceprofile:exempt` | Exempt from forced profile completion | `manager`, `editingteacher` |

Site administrators are **always exempt** (they have all capabilities by default).

Assign this capability to additional roles via **Site administration → Users → Permissions → Define roles**.

---

## Plugin Structure

```
local/forceprofile/
├── version.php                     Plugin metadata
├── settings.php                    Admin settings page
├── lib.php                         after_require_login callback + helper
├── db/
│   └── access.php                  Capability definition
├── classes/
│   └── privacy/
│       └── provider.php            GDPR privacy provider (null — no data stored)
└── lang/
    ├── en/local_forceprofile.php   English strings
    └── it/local_forceprofile.php   Italian strings
```

---

## Technical Details

### Performance

The database query to check profile fields runs once per page load. To minimize overhead, the result is **cached in `$SESSION`**: once a user's profile is confirmed complete, no further DB queries are made for the remainder of their session. The cache is automatically invalidated when the user visits the profile edit page.

### Security

- SQL queries use Moodle's Data Manipulation API with parameterized queries (`$DB->get_in_or_equal`)
- The notification message is sanitized via `format_string()` before display
- The redirect URL is validated as a local path (`PARAM_LOCALURL`) — external URLs are rejected
- Non-existent field shortnames are silently skipped with a `DEBUG_DEVELOPER` notice (no redirect loops from typos)

### Pages excluded from redirect

The following pages are always accessible, even with an incomplete profile:

- `/user/edit.php` — Profile edit page (where users fill in fields)
- `/user/editadvanced.php` — Advanced profile edit
- `/login/logout.php` — Logout
- `/login/change_password.php` — Password change
- `/lib/ajax/service.php` — AJAX web services
- `/lib/ajax/service-nologin.php` — AJAX (no login)

CLI scripts and AJAX requests are also excluded to prevent breaking background processes.

---

## License

This plugin is licensed under the [GNU GPL v3 or later](https://www.gnu.org/copyleft/gpl.html).

Copyright 2026 [Oltrematica](https://www.oltrematica.com)
