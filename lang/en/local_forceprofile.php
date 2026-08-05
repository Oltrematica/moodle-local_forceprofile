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
 * English language strings for local_forceprofile.
 *
 * @package    local_forceprofile
 * @copyright  2026 Oltrematica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Plugin.
$string['pluginname'] = 'Force Profile Completion';

// Settings.
$string['setting_enabled'] = 'Enable profile completion check';
$string['setting_enabled_desc'] = 'When enabled, users with incomplete profile fields will be redirected to their profile edit page.';
$string['setting_fields'] = 'Fields to check (shortnames, one per line)';
$string['setting_fields_desc'] = 'Enter the shortnames of the custom profile fields to check, one per line.';
$string['setting_validation'] = 'Field validation patterns';
$string['setting_validation_desc'] = 'Optional regex validation for fields. One per line, format: <code>shortname:/pattern/</code><br>Example: <code>tax_code:/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/i</code>';
$string['setting_validators'] = 'Field validators';
$string['setting_validators_desc'] = 'Optional named validators for checks a regular expression cannot express, such as a checksum. One per line, format: <code>shortname:validatorname</code><br>Example: <code>tax_code:codicefiscale</code><br>A validator can be combined with a regex pattern on the same field: the value has to satisfy both. Available validators:';
$string['setting_signupvalidation'] = 'Validate on self-registration';
$string['setting_signupvalidation_desc'] = 'When enabled, the configured fields are also checked on the self-registration form, so a new account cannot be created with a missing or invalid value. Only fields published on the signup form are checked.';
$string['setting_message'] = 'Message to show to the user';
$string['setting_message_desc'] = 'Warning message displayed when a user is redirected to complete their profile.';
$string['setting_redirecturl'] = 'Redirect URL';
$string['setting_redirecturl_desc'] = 'The URL where users will be redirected to complete their profile.';

// Notification.
$string['notification_message'] = 'You must complete your profile before proceeding. Please fill in all required fields.';
$string['notification_fieldlist'] = 'Fields to correct:';
$string['fieldproblem'] = '{$a->field}: {$a->reason}';
$string['reason_empty'] = 'this required field has not been filled in';
$string['reason_invalid'] = 'the value entered is not in a valid format';

// Validators.
$string['validator_codicefiscale'] = 'Italian tax code (codice fiscale)';
$string['error_codicefiscale'] = 'the value entered is not a valid Italian tax code — check the format and the final control character';

// Capabilities.
$string['forceprofile:exempt'] = 'Exempt from forced profile completion';
$string['forceprofile:viewstatus'] = 'View profile completion status page';

// Privacy.
$string['privacy:metadata'] = 'The Force Profile Completion plugin stores the timestamp of when a user completed their required profile fields.';
$string['privacy:metadata:userid'] = 'The ID of the user who completed their profile.';
$string['privacy:metadata:timecompleted'] = 'The timestamp when the user completed all required profile fields.';

// Events.
$string['event_profile_blocked'] = 'User blocked for incomplete profile';
$string['event_profile_completed'] = 'User completed required profile fields';

// Status page.
$string['status_title'] = 'Profile Completion Status';
$string['status_nofields'] = 'No fields configured. Go to plugin settings to add field shortnames.';
$string['status_total_users'] = 'total users';
$string['status_incomplete'] = 'incomplete';
$string['status_complete'] = 'complete';
$string['status_allusers_complete'] = 'All users have completed their profile fields.';
$string['status_missing_fields'] = 'Missing fields';
$string['status_view_profile'] = 'View';
