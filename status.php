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
 * Status page: shows users with incomplete profiles.
 *
 * @package    local_forceprofile
 * @copyright  2026 Oltrematica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/lib.php');

admin_externalpage_setup('local_forceprofile_status');

$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);

// Get configured fields.
$fieldssetting = get_config('local_forceprofile', 'fields');
$shortnames = [];
if (!empty($fieldssetting)) {
    $shortnames = array_filter(array_map('trim', explode("\n", $fieldssetting)));
}
$patterns = local_forceprofile_get_validation_patterns();

$PAGE->set_url(new moodle_url('/local/forceprofile/status.php', ['page' => $page, 'perpage' => $perpage]));
$PAGE->set_title(get_string('status_title', 'local_forceprofile'));
$PAGE->set_heading(get_string('status_title', 'local_forceprofile'));

echo $OUTPUT->header();

if (empty($shortnames)) {
    echo $OUTPUT->notification(get_string('status_nofields', 'local_forceprofile'), 'warning');
    echo $OUTPUT->footer();
    die;
}

// Summary counts.
$counts = local_forceprofile_get_status_counts($shortnames, $patterns);

echo html_writer::start_div('local-forceprofile-summary mb-4');
echo html_writer::start_div('d-flex gap-3 flex-wrap');

echo html_writer::div(
    html_writer::tag('strong', $counts['total']) . ' ' . get_string('status_total_users', 'local_forceprofile'),
    'badge bg-secondary p-2 fs-6'
);
echo html_writer::div(
    html_writer::tag('strong', $counts['incomplete']) . ' ' . get_string('status_incomplete', 'local_forceprofile'),
    'badge bg-warning text-dark p-2 fs-6'
);
echo html_writer::div(
    html_writer::tag('strong', $counts['complete']) . ' ' . get_string('status_complete', 'local_forceprofile'),
    'badge bg-success p-2 fs-6'
);

echo html_writer::end_div();
echo html_writer::end_div();

// Users table.
$result = local_forceprofile_get_incomplete_users($shortnames, $patterns, $page, $perpage);

if (empty($result['users'])) {
    echo $OUTPUT->notification(get_string('status_allusers_complete', 'local_forceprofile'), 'success');
    echo $OUTPUT->footer();
    die;
}

$table = new html_table();
$table->head = [
    get_string('username'),
    get_string('fullname'),
    get_string('email'),
    get_string('status_missing_fields', 'local_forceprofile'),
    get_string('lastaccess'),
    get_string('actions'),
];
$table->attributes['class'] = 'table table-striped table-hover generaltable';

foreach ($result['users'] as $user) {
    $profileurl = new moodle_url('/user/profile.php', ['id' => $user->id]);
    $editurl = new moodle_url('/user/editadvanced.php', ['id' => $user->id]);

    $missingbadges = '';
    foreach ($user->incompletefields as $field) {
        $missingbadges .= html_writer::span($field, 'badge bg-warning text-dark me-1');
    }

    $lastaccess = $user->lastaccess ? userdate($user->lastaccess, get_string('strftimedatetimeshort', 'langconfig')) : '-';

    $actions = html_writer::link($profileurl, get_string('status_view_profile', 'local_forceprofile'),
        ['class' => 'btn btn-sm btn-outline-primary me-1']);
    $actions .= html_writer::link($editurl, get_string('edit'),
        ['class' => 'btn btn-sm btn-outline-secondary']);

    $table->data[] = [
        $user->username,
        fullname($user),
        $user->email,
        $missingbadges,
        $lastaccess,
        $actions,
    ];
}

echo html_writer::table($table);

// Pagination.
$baseurl = new moodle_url('/local/forceprofile/status.php', ['perpage' => $perpage]);
echo $OUTPUT->paging_bar($result['totalcount'], $page, $perpage, $baseurl);

echo $OUTPUT->footer();
