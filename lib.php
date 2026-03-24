<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Library functions for local_forceprofile.
 *
 * @package    local_forceprofile
 * @copyright  2026 Oltrematica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Callback invoked after require_login() on every page load.
 *
 * Forces users with incomplete profile fields to the profile edit page.
 */
function local_forceprofile_after_require_login() {
    global $DB, $USER, $PAGE, $SESSION;

    // Skip CLI scripts and AJAX requests.
    if (CLI_SCRIPT || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)) {
        return;
    }

    // Plugin enabled?
    if (!get_config('local_forceprofile', 'enabled')) {
        return;
    }

    // Guest or not logged in — skip.
    if (!isloggedin() || isguestuser()) {
        return;
    }

    // Admin or exempt users — skip.
    if (is_siteadmin() || has_capability('local/forceprofile:exempt', \context_system::instance())) {
        return;
    }

    // Use session cache to avoid DB query on every page load.
    // Cache is invalidated when the user visits the profile edit page.
    if (!empty($SESSION->local_forceprofile_complete)) {
        return;
    }

    // Determine current URL safely ($PAGE->url may not be set yet).
    try {
        $currenturl = $PAGE->url->get_path();
    } catch (\Throwable $e) {
        $currenturl = me();
    }

    // Avoid redirect loops: allow profile edit, logout, password change, and AJAX endpoints.
    $allowedpaths = [
        '/user/edit.php',
        '/user/editadvanced.php',
        '/login/logout.php',
        '/login/change_password.php',
        '/lib/ajax/service.php',
        '/lib/ajax/service-nologin.php',
    ];
    foreach ($allowedpaths as $path) {
        if ($currenturl === $path) {
            // Invalidate cache when user visits profile edit page (they may have just saved).
            if ($path === '/user/edit.php' || $path === '/user/editadvanced.php') {
                unset($SESSION->local_forceprofile_complete);
            }
            return;
        }
    }

    // Get configured field shortnames.
    $fieldssetting = get_config('local_forceprofile', 'fields');
    if (empty($fieldssetting)) {
        return;
    }
    $shortnames = array_filter(array_map('trim', explode("\n", $fieldssetting)));
    if (empty($shortnames)) {
        return;
    }

    // Load validation patterns.
    $patterns = local_forceprofile_get_validation_patterns();

    // Check if any required field is empty or invalid.
    $incompletefields = local_forceprofile_get_incomplete_fields($USER->id, $shortnames, $patterns);
    if (empty($incompletefields)) {
        // Profile is complete — cache result and record completion.
        $SESSION->local_forceprofile_complete = true;
        local_forceprofile_record_completion($USER->id);
        return;
    }

    // Fire the profile_blocked event.
    $event = \local_forceprofile\event\profile_blocked::create([
        'userid' => $USER->id,
        'other' => ['fields' => implode(', ', $incompletefields)],
    ]);
    $event->trigger();

    // Redirect to profile edit page with warning.
    $message = get_config('local_forceprofile', 'message');
    if (empty($message)) {
        $message = get_string('notification_message', 'local_forceprofile');
    }
    \core\notification::warning(format_string($message));

    $redirecturl = get_config('local_forceprofile', 'redirecturl');
    if (empty($redirecturl) || !str_starts_with($redirecturl, '/')) {
        $redirecturl = '/user/edit.php';
    }
    $url = new \moodle_url($redirecturl, ['id' => $USER->id]);
    redirect($url);
}

/**
 * Get the list of incomplete or invalid fields for a user.
 *
 * @param int $userid The user ID to check.
 * @param array $shortnames Array of field shortnames to verify.
 * @param array $patterns Associative array of shortname => regex pattern for validation.
 * @return array List of shortnames that are empty or fail validation.
 */
function local_forceprofile_get_incomplete_fields(int $userid, array $shortnames, array $patterns = []): array {
    global $DB;

    if (empty($shortnames)) {
        return [];
    }

    // Build IN clause for shortnames.
    list($insql, $params) = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);
    $params['userid'] = $userid;

    $sql = "SELECT uif.shortname, uid.data
              FROM {user_info_field} uif
         LEFT JOIN {user_info_data} uid ON uid.fieldid = uif.id AND uid.userid = :userid
             WHERE uif.shortname {$insql}";

    $records = $DB->get_records_sql($sql, $params);

    // Log warning for non-existent field shortnames (misconfiguration).
    $foundshortnames = array_keys($records);
    $missing = array_diff($shortnames, $foundshortnames);
    if (!empty($missing)) {
        debugging('local_forceprofile: configured field shortnames not found in user_info_field: ' .
            implode(', ', $missing), DEBUG_DEVELOPER);
    }

    $incomplete = [];
    foreach ($records as $shortname => $record) {
        $value = $record->data ?? '';

        // Check empty.
        if ($value === '' || $value === null) {
            $incomplete[] = $shortname;
            continue;
        }

        // Check regex validation if configured.
        if (!empty($patterns[$shortname])) {
            $pattern = $patterns[$shortname];
            if (@preg_match($pattern, $value) !== 1) {
                $incomplete[] = $shortname;
            }
        }
    }

    return $incomplete;
}

/**
 * Check if a user has any incomplete profile fields.
 *
 * Wrapper around local_forceprofile_get_incomplete_fields for backward compatibility.
 *
 * @param int $userid The user ID to check.
 * @param array $shortnames Array of field shortnames to verify.
 * @return bool True if at least one existing field is empty or missing.
 */
function local_forceprofile_has_incomplete_fields(int $userid, array $shortnames): bool {
    $patterns = local_forceprofile_get_validation_patterns();
    return !empty(local_forceprofile_get_incomplete_fields($userid, $shortnames, $patterns));
}

/**
 * Parse validation patterns from plugin settings.
 *
 * Format: one line per pattern, "shortname:/regex/"
 *
 * @return array Associative array of shortname => regex pattern.
 */
function local_forceprofile_get_validation_patterns(): array {
    $setting = get_config('local_forceprofile', 'validation');
    if (empty($setting)) {
        return [];
    }

    $patterns = [];
    $lines = array_filter(array_map('trim', explode("\n", $setting)));
    foreach ($lines as $line) {
        // Split on first colon only.
        $colonpos = strpos($line, ':');
        if ($colonpos === false) {
            continue;
        }
        $shortname = trim(substr($line, 0, $colonpos));
        $pattern = trim(substr($line, $colonpos + 1));
        if (!empty($shortname) && !empty($pattern)) {
            // Validate that the regex compiles.
            if (@preg_match($pattern, '') !== false) {
                $patterns[$shortname] = $pattern;
            } else {
                debugging("local_forceprofile: invalid regex for field '{$shortname}': {$pattern}", DEBUG_DEVELOPER);
            }
        }
    }

    return $patterns;
}

/**
 * Record the timestamp when a user completes their profile.
 *
 * If already recorded, updates the timestamp.
 *
 * @param int $userid The user ID.
 */
function local_forceprofile_record_completion(int $userid): void {
    global $DB;

    $existing = $DB->get_record('local_forceprofile_compl', ['userid' => $userid]);
    $now = time();

    if ($existing) {
        $existing->timecompleted = $now;
        $DB->update_record('local_forceprofile_compl', $existing);
    } else {
        $record = new \stdClass();
        $record->userid = $userid;
        $record->timecompleted = $now;
        $completionid = $DB->insert_record('local_forceprofile_compl', $record);

        // Fire the profile_completed event.
        $event = \local_forceprofile\event\profile_completed::create([
            'userid' => $userid,
            'objectid' => $completionid,
        ]);
        $event->trigger();
    }
}

/**
 * Get a count of users with incomplete profiles.
 *
 * @param array $shortnames Field shortnames to check.
 * @param array $patterns Validation patterns.
 * @return array ['total' => int, 'incomplete' => int, 'complete' => int]
 */
function local_forceprofile_get_status_counts(array $shortnames, array $patterns = []): array {
    global $DB;

    if (empty($shortnames)) {
        return ['total' => 0, 'incomplete' => 0, 'complete' => 0];
    }

    // Get all non-admin, non-guest, confirmed users.
    $allusers = $DB->get_records_select('user',
        "deleted = 0 AND suspended = 0 AND confirmed = 1 AND id > 2",
        null, '', 'id');

    $incomplete = 0;
    foreach ($allusers as $user) {
        if (is_siteadmin($user->id)) {
            continue;
        }
        $fields = local_forceprofile_get_incomplete_fields($user->id, $shortnames, $patterns);
        if (!empty($fields)) {
            $incomplete++;
        }
    }

    $total = count($allusers);
    return [
        'total' => $total,
        'incomplete' => $incomplete,
        'complete' => $total - $incomplete,
    ];
}

/**
 * Get list of users with incomplete profiles.
 *
 * @param array $shortnames Field shortnames to check.
 * @param array $patterns Validation patterns.
 * @param int $page Page number (0-based).
 * @param int $perpage Results per page.
 * @return array ['users' => array, 'totalcount' => int]
 */
function local_forceprofile_get_incomplete_users(array $shortnames, array $patterns = [],
        int $page = 0, int $perpage = 50): array {
    global $DB;

    if (empty($shortnames)) {
        return ['users' => [], 'totalcount' => 0];
    }

    // Get all non-admin, non-guest, confirmed users.
    $allusers = $DB->get_records_select('user',
        "deleted = 0 AND suspended = 0 AND confirmed = 1 AND id > 2",
        null, 'lastname, firstname', 'id, username, firstname, lastname, email, lastaccess');

    $incompleteusers = [];
    foreach ($allusers as $user) {
        if (is_siteadmin($user->id)) {
            continue;
        }
        $fields = local_forceprofile_get_incomplete_fields($user->id, $shortnames, $patterns);
        if (!empty($fields)) {
            $user->incompletefields = $fields;
            $incompleteusers[] = $user;
        }
    }

    $totalcount = count($incompleteusers);
    $pagedusers = array_slice($incompleteusers, $page * $perpage, $perpage);

    return ['users' => $pagedusers, 'totalcount' => $totalcount];
}

/**
 * Inject form enhancements on the profile edit page.
 *
 * Adds required indicators and empty default options for configured fields.
 *
 * @return string Empty string (required by callback signature).
 */
function local_forceprofile_before_standard_html_head() {
    global $PAGE, $USER;

    // Only act on profile edit pages.
    try {
        $currenturl = $PAGE->url->get_path();
    } catch (\Throwable $e) {
        return '';
    }

    if ($currenturl !== '/user/edit.php' && $currenturl !== '/user/editadvanced.php') {
        return '';
    }

    // Plugin must be enabled.
    if (!get_config('local_forceprofile', 'enabled')) {
        return '';
    }

    // Not for guests.
    if (!isloggedin() || isguestuser()) {
        return '';
    }

    // Get configured field shortnames.
    $fieldssetting = get_config('local_forceprofile', 'fields');
    if (empty($fieldssetting)) {
        return '';
    }

    $shortnames = array_filter(array_map('trim', explode("\n", $fieldssetting)));
    if (empty($shortnames)) {
        return '';
    }

    // Determine which user is being edited.
    $edituserid = optional_param('id', $USER->id, PARAM_INT);

    // Get incomplete fields for the user being edited.
    $patterns = local_forceprofile_get_validation_patterns();
    $incompletefields = local_forceprofile_get_incomplete_fields($edituserid, $shortnames, $patterns);

    // Load AMD module with field data.
    $PAGE->requires->js_call_amd(
        'local_forceprofile/formenhancer',
        'init',
        [array_values($shortnames), array_values($incompletefields)]
    );

    return '';
}
