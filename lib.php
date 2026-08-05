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

use local_forceprofile\validator\validator_interface;
use local_forceprofile\validator_manager;

/** The field holds no value at all. */
define('LOCAL_FORCEPROFILE_REASON_EMPTY', 'empty');

/** The field holds a value that fails its regex pattern or its named validator. */
define('LOCAL_FORCEPROFILE_REASON_INVALID', 'invalid');

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

    // Load validation patterns and named validators.
    $patterns = local_forceprofile_get_validation_patterns();
    $validators = local_forceprofile_get_field_validators();

    // Check if any required field is empty or invalid.
    $problems = local_forceprofile_get_field_problems($USER->id, $shortnames, $patterns, $validators);
    if (empty($problems)) {
        // Profile is complete — cache result and record completion.
        $SESSION->local_forceprofile_complete = true;
        local_forceprofile_record_completion($USER->id);
        return;
    }

    // Fire the profile_blocked event.
    $event = \local_forceprofile\event\profile_blocked::create([
        'userid' => $USER->id,
        'other' => ['fields' => implode(', ', array_keys($problems))],
    ]);
    $event->trigger();

    // Redirect to profile edit page with a warning naming the offending fields.
    \core\notification::warning(local_forceprofile_build_notification($problems));

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
 * @param array $validators Associative array of shortname => validator_interface.
 * @return array List of shortnames that are empty or fail validation.
 */
function local_forceprofile_get_incomplete_fields(int $userid, array $shortnames, array $patterns = [],
        array $validators = []): array {
    return array_keys(local_forceprofile_get_field_problems($userid, $shortnames, $patterns, $validators));
}

/**
 * Get the incomplete or invalid fields for a user, together with the reason.
 *
 * This is the single point where a profile is judged complete: the callbacks,
 * the status page and the signup validation all end up here, so a check added
 * to this function applies everywhere.
 *
 * @param int $userid The user ID to check.
 * @param array $shortnames Array of field shortnames to verify.
 * @param array $patterns Associative array of shortname => regex pattern.
 * @param array $validators Associative array of shortname => validator_interface.
 * @return stdClass[] Keyed by shortname, each with shortname, name, reason and message.
 */
function local_forceprofile_get_field_problems(int $userid, array $shortnames, array $patterns = [],
        array $validators = []): array {
    global $DB;

    if (empty($shortnames)) {
        return [];
    }

    // Build IN clause for shortnames.
    list($insql, $params) = $DB->get_in_or_equal($shortnames, SQL_PARAMS_NAMED);
    $params['userid'] = $userid;

    $sql = "SELECT uif.shortname, uif.name, uid.data
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

    $problems = [];
    foreach ($records as $shortname => $record) {
        $validator = $validators[$shortname] ?? null;
        $problem = local_forceprofile_check_value(
            $record->data ?? '',
            $patterns[$shortname] ?? null,
            $validator
        );

        if ($problem === null) {
            continue;
        }

        // Profile fields are site level, and the context has to be explicit here:
        // this runs from after_require_login(), before $PAGE is necessarily set up.
        $fieldname = format_string($record->name ?: $shortname, true, [
            'context' => \context_system::instance(),
        ]);
        $problems[$shortname] = (object)[
            'shortname' => $shortname,
            'name' => $fieldname,
            'reason' => $problem,
            'message' => local_forceprofile_describe_problem($fieldname, $problem, $validator),
        ];
    }

    return $problems;
}

/**
 * Decide whether a single value satisfies its pattern and its validator.
 *
 * @param string|null $value The raw stored or submitted value.
 * @param string|null $pattern Optional regex the value has to match.
 * @param validator_interface|null $validator Optional named validator.
 * @return string|null One of the LOCAL_FORCEPROFILE_REASON_* constants, or null when the value is fine.
 */
function local_forceprofile_check_value(?string $value, ?string $pattern = null,
        ?validator_interface $validator = null): ?string {
    if ($value === null || trim($value) === '') {
        return LOCAL_FORCEPROFILE_REASON_EMPTY;
    }

    if (!empty($pattern) && @preg_match($pattern, $value) !== 1) {
        return LOCAL_FORCEPROFILE_REASON_INVALID;
    }

    if ($validator !== null && !$validator->validate($validator->normalise($value))) {
        return LOCAL_FORCEPROFILE_REASON_INVALID;
    }

    return null;
}

/**
 * Build the sentence shown to the user for a single problematic field.
 *
 * @param string $fieldname The display name of the profile field.
 * @param string $reason One of the LOCAL_FORCEPROFILE_REASON_* constants.
 * @param validator_interface|null $validator The validator attached to the field, if any.
 * @return string Translated text, safe to render as HTML.
 */
function local_forceprofile_describe_problem(string $fieldname, string $reason,
        ?validator_interface $validator = null): string {
    if ($reason === LOCAL_FORCEPROFILE_REASON_EMPTY) {
        $detail = get_string('reason_empty', 'local_forceprofile');
    } else if ($validator !== null) {
        $detail = $validator->get_error_message();
    } else {
        $detail = get_string('reason_invalid', 'local_forceprofile');
    }

    return get_string('fieldproblem', 'local_forceprofile', (object)[
        'field' => $fieldname,
        'reason' => $detail,
    ]);
}

/**
 * Compose the warning notification listing every field the user has to fix.
 *
 * @param stdClass[] $problems As returned by local_forceprofile_get_field_problems().
 * @return string HTML for \core\notification::warning().
 */
function local_forceprofile_build_notification(array $problems): string {
    $message = get_config('local_forceprofile', 'message');
    if (empty($message)) {
        $message = get_string('notification_message', 'local_forceprofile');
    }

    $output = html_writer::div(format_string($message));

    if (!empty($problems)) {
        // Messages are built from format_string() output and language strings,
        // so they are already safe to render as HTML.
        $items = [];
        foreach ($problems as $problem) {
            $items[] = $problem->message;
        }
        $output .= html_writer::tag('p', get_string('notification_fieldlist', 'local_forceprofile'), [
            'class' => 'mb-1 mt-2 fw-bold',
        ]);
        $output .= html_writer::alist($items, ['class' => 'mb-0']);
    }

    return $output;
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
    $validators = local_forceprofile_get_field_validators();
    return !empty(local_forceprofile_get_incomplete_fields($userid, $shortnames, $patterns, $validators));
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
 * Parse the named validators assigned to fields in the plugin settings.
 *
 * Format: one line per field, "shortname:validatorname". The validator name is
 * either a built-in one (see {@see validator_manager}) or the fully qualified
 * name of a class implementing validator_interface.
 *
 * @return validator_interface[] Associative array of shortname => validator instance.
 */
function local_forceprofile_get_field_validators(): array {
    $setting = get_config('local_forceprofile', 'validators');
    if (empty($setting)) {
        return [];
    }

    $validators = [];
    $lines = array_filter(array_map('trim', explode("\n", $setting)));
    foreach ($lines as $line) {
        // Split on first colon only, mirroring the regex setting.
        $colonpos = strpos($line, ':');
        if ($colonpos === false) {
            continue;
        }
        $shortname = trim(substr($line, 0, $colonpos));
        $name = trim(substr($line, $colonpos + 1));
        if ($shortname === '' || $name === '') {
            continue;
        }

        $validator = validator_manager::instantiate($name);
        if ($validator === null) {
            debugging("local_forceprofile: unknown validator '{$name}' for field '{$shortname}'", DEBUG_DEVELOPER);
            continue;
        }

        $validators[$shortname] = $validator;
    }

    return $validators;
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
 * @param array $validators Named validators, keyed by shortname.
 * @return array ['total' => int, 'incomplete' => int, 'complete' => int]
 */
function local_forceprofile_get_status_counts(array $shortnames, array $patterns = [],
        array $validators = []): array {
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
        $fields = local_forceprofile_get_incomplete_fields($user->id, $shortnames, $patterns, $validators);
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
 * @param array $validators Named validators, keyed by shortname.
 * @return array ['users' => array, 'totalcount' => int]
 */
function local_forceprofile_get_incomplete_users(array $shortnames, array $patterns = [],
        int $page = 0, int $perpage = 50, array $validators = []): array {
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
        $problems = local_forceprofile_get_field_problems($user->id, $shortnames, $patterns, $validators);
        if (!empty($problems)) {
            $user->incompletefields = array_keys($problems);
            $user->fieldproblems = $problems;
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

    // Only the fields that actually need attention are highlighted: marking every
    // configured field would put a red icon on values that are already correct.
    $patterns = local_forceprofile_get_validation_patterns();
    $validators = local_forceprofile_get_field_validators();
    $problems = local_forceprofile_get_field_problems($edituserid, $shortnames, $patterns, $validators);

    if (empty($problems)) {
        return '';
    }

    $fields = [];
    foreach ($problems as $problem) {
        $fields[] = [
            'shortname' => $problem->shortname,
            'reason' => $problem->reason,
            'message' => $problem->message,
        ];
    }

    // Load AMD module with field data.
    $PAGE->requires->js_call_amd(
        'local_forceprofile/formenhancer',
        'init',
        [$fields, get_string('choosedots')]
    );

    return '';
}

/**
 * Callback invoked by the self-registration form to validate its data.
 *
 * Without this, a user can sign up with a malformed value and only discover it
 * on the next page load, when the profile check locks them out of the site.
 *
 * @param array $data The submitted signup form data.
 * @return array Errors keyed by form element name, empty when everything is fine.
 */
function local_forceprofile_validate_extend_signup_form($data) {
    if (!get_config('local_forceprofile', 'enabled')) {
        return [];
    }

    if (!get_config('local_forceprofile', 'signupvalidation')) {
        return [];
    }

    $fieldssetting = get_config('local_forceprofile', 'fields');
    if (empty($fieldssetting)) {
        return [];
    }

    $shortnames = array_filter(array_map('trim', explode("\n", $fieldssetting)));
    if (empty($shortnames)) {
        return [];
    }

    $patterns = local_forceprofile_get_validation_patterns();
    $validators = local_forceprofile_get_field_validators();

    $errors = [];
    foreach ($shortnames as $shortname) {
        $element = 'profile_field_' . $shortname;

        // The field may not be published on the signup form at all — nothing to check.
        if (!array_key_exists($element, (array)$data)) {
            continue;
        }

        $value = $data[$element];
        if (!is_scalar($value) && $value !== null) {
            continue;
        }

        $validator = $validators[$shortname] ?? null;
        $reason = local_forceprofile_check_value(
            $value === null ? null : (string)$value,
            $patterns[$shortname] ?? null,
            $validator
        );

        if ($reason === LOCAL_FORCEPROFILE_REASON_EMPTY) {
            $errors[$element] = get_string('required');
        } else if ($reason === LOCAL_FORCEPROFILE_REASON_INVALID) {
            $errors[$element] = $validator !== null
                ? $validator->get_error_message()
                : get_string('reason_invalid', 'local_forceprofile');
        }
    }

    return $errors;
}
