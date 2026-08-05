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
 * Validator for the Italian tax code (codice fiscale).
 *
 * Checks the 16 character layout and recomputes the check character, so that
 * a code with a perfect shape but a single mistyped letter is rejected.
 * Omocodia substitutions are supported: when two people would be assigned the
 * same code, the Agenzia delle Entrate replaces digits with letters
 * (0-9 becomes L, M, N, P, Q, R, S, T, U, V), starting from the rightmost one.
 *
 * @package    local_forceprofile
 * @copyright  2026 Oltrematica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class codicefiscale implements validator_interface {

    /** @var string Digits, or the letters that may stand in for them under omocodia. */
    private const DIGIT = '0-9LMNPQRSTUV';

    /** @var string Letters encoding the month of birth, from January to December. */
    private const MONTH = 'ABCDEHLMPRST';

    /** @var int[] Character values used on odd positions (1st, 3rd, ... counting from one). */
    private const ODD = [
        '0' => 1, '1' => 0, '2' => 5, '3' => 7, '4' => 9,
        '5' => 13, '6' => 15, '7' => 17, '8' => 19, '9' => 21,
        'A' => 1, 'B' => 0, 'C' => 5, 'D' => 7, 'E' => 9,
        'F' => 13, 'G' => 15, 'H' => 17, 'I' => 19, 'J' => 21,
        'K' => 2, 'L' => 4, 'M' => 18, 'N' => 20, 'O' => 11,
        'P' => 3, 'Q' => 6, 'R' => 8, 'S' => 12, 'T' => 14,
        'U' => 16, 'V' => 10, 'W' => 22, 'X' => 25, 'Y' => 24, 'Z' => 23,
    ];

    /** @var int[] Character values used on even positions (2nd, 4th, ... counting from one). */
    private const EVEN = [
        '0' => 0, '1' => 1, '2' => 2, '3' => 3, '4' => 4,
        '5' => 5, '6' => 6, '7' => 7, '8' => 8, '9' => 9,
        'A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4,
        'F' => 5, 'G' => 6, 'H' => 7, 'I' => 8, 'J' => 9,
        'K' => 10, 'L' => 11, 'M' => 12, 'N' => 13, 'O' => 14,
        'P' => 15, 'Q' => 16, 'R' => 17, 'S' => 18, 'T' => 19,
        'U' => 20, 'V' => 21, 'W' => 22, 'X' => 23, 'Y' => 24, 'Z' => 25,
    ];

    /**
     * The identifier used in the plugin settings.
     *
     * @return string
     */
    public function get_name(): string {
        return 'codicefiscale';
    }

    /**
     * Human readable name, shown in the settings page.
     *
     * @return string
     */
    public function get_display_name(): string {
        return get_string('validator_codicefiscale', 'local_forceprofile');
    }

    /**
     * Trim and uppercase, so that codes typed in lower case still validate.
     *
     * @param string $value The raw value.
     * @return string
     */
    public function normalise(string $value): string {
        return \core_text::strtoupper(trim($value));
    }

    /**
     * Check the layout and the check character.
     *
     * @param string $value A value already passed through normalise().
     * @return bool
     */
    public function validate(string $value): bool {
        if (!preg_match($this->get_pattern(), $value)) {
            return false;
        }

        return substr($value, 15, 1) === $this->compute_check_character(substr($value, 0, 15));
    }

    /**
     * The reason shown to the user when the code does not validate.
     *
     * @return string
     */
    public function get_error_message(): string {
        return get_string('error_codicefiscale', 'local_forceprofile');
    }

    /**
     * The regular expression describing a well formed tax code.
     *
     * Exposed so that the documentation and the settings page can suggest the
     * very same pattern instead of a hand written approximation.
     *
     * @return string A PCRE pattern, anchored, expecting an uppercase value.
     */
    public function get_pattern(): string {
        return '/^[A-Z]{6}[' . self::DIGIT . ']{2}[' . self::MONTH . ']' .
            '[' . self::DIGIT . ']{2}[A-Z][' . self::DIGIT . ']{3}[A-Z]$/';
    }

    /**
     * Recompute the 16th character from the first fifteen.
     *
     * @param string $body The first 15 characters of the tax code, uppercase.
     * @return string A single letter between A and Z.
     */
    private function compute_check_character(string $body): string {
        $sum = 0;
        for ($i = 0; $i < 15; $i++) {
            $char = $body[$i];
            // $i is zero based, so an even index is an odd position.
            $sum += ($i % 2 === 0) ? self::ODD[$char] : self::EVEN[$char];
        }

        return chr(ord('A') + ($sum % 26));
    }
}
