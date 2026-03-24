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
 * Settings for local_forceprofile.
 *
 * @package    local_forceprofile
 * @copyright  2026 Oltrematica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_forceprofile', get_string('pluginname', 'local_forceprofile'));

    // Enabled/disabled.
    $settings->add(new admin_setting_configcheckbox(
        'local_forceprofile/enabled',
        get_string('setting_enabled', 'local_forceprofile'),
        get_string('setting_enabled_desc', 'local_forceprofile'),
        0
    ));

    // Fields to check (shortnames, one per line).
    $settings->add(new admin_setting_configtextarea(
        'local_forceprofile/fields',
        get_string('setting_fields', 'local_forceprofile'),
        get_string('setting_fields_desc', 'local_forceprofile'),
        ''
    ));

    // Validation patterns (shortname:regex per line).
    $settings->add(new admin_setting_configtextarea(
        'local_forceprofile/validation',
        get_string('setting_validation', 'local_forceprofile'),
        get_string('setting_validation_desc', 'local_forceprofile'),
        ''
    ));

    // Custom message.
    $settings->add(new admin_setting_configtextarea(
        'local_forceprofile/message',
        get_string('setting_message', 'local_forceprofile'),
        get_string('setting_message_desc', 'local_forceprofile'),
        get_string('notification_message', 'local_forceprofile')
    ));

    // Redirect URL (must be a local path starting with /).
    $settings->add(new admin_setting_configtext(
        'local_forceprofile/redirecturl',
        get_string('setting_redirecturl', 'local_forceprofile'),
        get_string('setting_redirecturl_desc', 'local_forceprofile'),
        '/user/edit.php',
        PARAM_LOCALURL
    ));

    $ADMIN->add('localplugins', $settings);

    // Status page (external page).
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_forceprofile_status',
        get_string('status_title', 'local_forceprofile'),
        new moodle_url('/local/forceprofile/status.php'),
        'local/forceprofile:viewstatus'
    ));
}
