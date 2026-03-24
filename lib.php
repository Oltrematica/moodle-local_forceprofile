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

    // Check if any required field is empty.
    if (!local_forceprofile_has_incomplete_fields($USER->id, $shortnames)) {
        $SESSION->local_forceprofile_complete = true;
        return;
    }

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
 * Check if a user has any incomplete profile fields.
 *
 * Only checks fields that actually exist in user_info_field.
 * Non-existent shortnames are silently skipped (logged as debug notice).
 *
 * @param int $userid The user ID to check.
 * @param array $shortnames Array of field shortnames to verify.
 * @return bool True if at least one existing field is empty or missing.
 */
function local_forceprofile_has_incomplete_fields(int $userid, array $shortnames): bool {
    global $DB;

    if (empty($shortnames)) {
        return false;
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

    // If no configured fields exist at all, nothing to enforce.
    if (empty($records)) {
        return false;
    }

    // Check each existing field value.
    foreach ($records as $record) {
        if (!isset($record->data) || $record->data === '' || $record->data === null) {
            return true;
        }
    }

    return false;
}
