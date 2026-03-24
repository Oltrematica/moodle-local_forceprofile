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
 * Event: user completed their forced profile fields.
 *
 * @package    local_forceprofile
 * @copyright  2026 Oltrematica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forceprofile\event;

/**
 * Event fired when a user successfully completes all required profile fields.
 */
class profile_completed extends \core\event\base {

    /**
     * Initialise the event.
     */
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_forceprofile_compl';
        $this->context = \context_system::instance();
    }

    /**
     * Returns localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_profile_completed', 'local_forceprofile');
    }

    /**
     * Returns event description.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' completed all required profile fields.";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/user/profile.php', ['id' => $this->userid]);
    }
}
