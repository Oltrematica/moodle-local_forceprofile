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
 * Privacy provider for local_forceprofile.
 *
 * @package    local_forceprofile
 * @copyright  2026 Oltrematica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forceprofile\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider — stores profile completion timestamps.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe the data stored by this plugin.
     *
     * @param collection $collection
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_forceprofile_compl', [
            'userid' => 'privacy:metadata:userid',
            'timecompleted' => 'privacy:metadata:timecompleted',
        ], 'privacy:metadata');

        return $collection;
    }

    /**
     * Get the list of contexts that contain user data.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {local_forceprofile_compl} c
                  JOIN {context} ctx ON ctx.instanceid = c.userid AND ctx.contextlevel = :contextlevel
                 WHERE c.userid = :userid";

        $contextlist->add_from_sql($sql, [
            'contextlevel' => CONTEXT_USER,
            'userid' => $userid,
        ]);

        return $contextlist;
    }

    /**
     * Get the list of users within a context.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }

        $sql = "SELECT userid FROM {local_forceprofile_compl} WHERE userid = :userid";
        $userlist->add_from_sql('userid', $sql, ['userid' => $context->instanceid]);
    }

    /**
     * Export user data.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        $record = $DB->get_record('local_forceprofile_compl', ['userid' => $userid]);

        if ($record) {
            $data = (object) [
                'timecompleted' => \core_privacy\local\request\transform::datetime($record->timecompleted),
            ];
            writer::with_context(\context_user::instance($userid))
                ->export_data([get_string('pluginname', 'local_forceprofile')], $data);
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }
        $DB->delete_records('local_forceprofile_compl', ['userid' => $context->instanceid]);
    }

    /**
     * Delete data for the specified user.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $DB->delete_records('local_forceprofile_compl', ['userid' => $contextlist->get_user()->id]);
    }

    /**
     * Delete data for users in the specified userlist.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }

        $DB->delete_records('local_forceprofile_compl', ['userid' => $context->instanceid]);
    }
}
