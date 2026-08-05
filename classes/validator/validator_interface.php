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

namespace local_forceprofile\validator;

/**
 * Contract implemented by every named field validator.
 *
 * A validator encapsulates a check that cannot be expressed as a regular
 * expression, such as a checksum. Validators are addressed by name in the
 * plugin settings, using one "shortname:validatorname" line per field.
 *
 * @package    local_forceprofile
 * @copyright  2026 Oltrematica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface validator_interface {

    /**
     * The identifier used in the plugin settings.
     *
     * @return string Lowercase, no spaces.
     */
    public function get_name(): string;

    /**
     * Human readable name, shown in the settings page.
     *
     * @return string Translated string.
     */
    public function get_display_name(): string;

    /**
     * Clean a raw stored value before validating it.
     *
     * Stored data is rarely canonical: values get pasted with whitespace or
     * typed in lower case. Validators declare here how to make a value
     * comparable, and the same normalisation is applied both to profile data
     * and to values submitted through the signup form.
     *
     * @param string $value The raw value.
     * @return string The normalised value.
     */
    public function normalise(string $value): string;

    /**
     * Check whether a normalised value is acceptable.
     *
     * @param string $value A value already passed through normalise().
     * @return bool True when the value is valid.
     */
    public function validate(string $value): bool;

    /**
     * The reason shown to the user when validate() returns false.
     *
     * @return string Translated string, phrased to complete "Field name: ...".
     */
    public function get_error_message(): string;
}
