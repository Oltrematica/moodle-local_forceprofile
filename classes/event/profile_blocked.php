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
 * Event: user blocked due to incomplete profile.
 *
 * @package    local_forceprofile
 * @copyright  2026 Oltrematica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forceprofile\event;

/**
 * Event fired when a user is redirected because their profile is incomplete.
 */
class profile_blocked extends \core\event\base {

    /**
     * Initialise the event.
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->context = \context_system::instance();
    }

    /**
     * Returns localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_profile_blocked', 'local_forceprofile');
    }

    /**
     * Returns event description.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' was redirected to complete their profile. " .
            "Incomplete fields: {$this->other['fields']}.";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/user/edit.php', ['id' => $this->userid]);
    }
}
