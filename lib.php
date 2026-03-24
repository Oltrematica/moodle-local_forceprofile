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
    global $DB, $USER, $PAGE;

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

    // Avoid redirect loops: allow profile edit, logout, and password change pages.
    $currenturl = $PAGE->url->get_path();
    $allowedpaths = [
        '/user/edit.php',
        '/user/editadvanced.php',
        '/login/logout.php',
        '/login/change_password.php',
    ];
    foreach ($allowedpaths as $path) {
        if (strpos($currenturl, $path) !== false) {
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
        return;
    }

    // Redirect to profile edit page with warning.
    $message = get_config('local_forceprofile', 'message');
    if (empty($message)) {
        $message = get_string('notification_message', 'local_forceprofile');
    }
    \core\notification::warning($message);

    $redirecturl = get_config('local_forceprofile', 'redirecturl');
    if (empty($redirecturl)) {
        $redirecturl = '/user/edit.php';
    }
    $url = new \moodle_url($redirecturl, ['id' => $USER->id]);
    redirect($url);
}

/**
 * Check if a user has any incomplete profile fields.
 *
 * @param int $userid The user ID to check.
 * @param array $shortnames Array of field shortnames to verify.
 * @return bool True if at least one field is empty or missing.
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

    // If we got fewer records than shortnames, some fields don't exist — treat as incomplete.
    if (count($records) < count($shortnames)) {
        return true;
    }

    // Check each field value.
    foreach ($records as $record) {
        if (!isset($record->data) || $record->data === '' || $record->data === null) {
            return true;
        }
    }

    return false;
}
