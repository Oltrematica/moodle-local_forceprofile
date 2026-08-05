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
 * Unit tests for the Italian tax code validator.
 *
 * @package    local_forceprofile
 * @copyright  2026 Oltrematica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forceprofile\validator;

/**
 * Tests for the codicefiscale validator.
 *
 * @covers \local_forceprofile\validator\codicefiscale
 */
final class codicefiscale_test extends \advanced_testcase {

    /**
     * Data provider for values that must be accepted.
     *
     * @return array[] Array of [value, explanation].
     */
    public static function valid_provider(): array {
        return [
            'real code' => ['BDRNLT79P41D969B', 'Real code taken from production data.'],
            'canonical example' => ['RSSMRA85T10A562S', 'Classic Mario Rossi example.'],
            'lowercase' => ['bdrnlt79p41d969b', 'Stored lowercase — must be normalised.'],
            'mixed case' => ['BdRnLt79P41d969b', 'Stored mixed case — must be normalised.'],
            'surrounding spaces' => ['  BDRNLT79P41D969B  ', 'Copy-pasted with whitespace.'],
            'omocodia last digit' => ['BDRNLT79P41D96VQ', 'Belfiore digit 9 replaced by V.'],
            'omocodia day digit' => ['BDRNLT79P4MD969T', 'Day digit 1 replaced by M.'],
        ];
    }

    /**
     * Test: well formed tax codes with a correct check character are accepted.
     *
     * @dataProvider valid_provider
     * @param string $value The tax code under test.
     * @param string $explanation Why the value is expected to be valid.
     */
    public function test_valid_codes(string $value, string $explanation): void {
        $validator = new codicefiscale();
        $this->assertTrue($validator->validate($validator->normalise($value)), $explanation);
    }

    /**
     * Data provider for values that must be rejected.
     *
     * @return array[] Array of [value, explanation].
     */
    public static function invalid_provider(): array {
        return [
            'wrong check character' => [
                'ADRNLT79P41D969B',
                'Well formed but the check character should be C.',
            ],
            'truncated' => ['BDRNLT79P41D969', 'Only 15 characters.'],
            'too long' => ['BDRNLT79P41D969BX', '17 characters.'],
            'empty' => ['', 'Nothing entered.'],
            'only spaces' => ['   ', 'Whitespace only.'],
            'free text' => ['not a tax code', 'Random text.'],
            'digits in surname block' => ['BDRNL179P41D969B', 'Digit inside the first six letters.'],
            'invalid month letter' => ['BDRNLT79Z41D969B', 'Z is not a valid month letter.'],
            'letter in belfiore prefix position' => ['BDRNLT79P4149698', 'Belfiore code must start with a letter.'],
            'invalid omocodia letter' => ['BDRNLT79P41D96OB', 'O is not a valid omocodia substitution.'],
        ];
    }

    /**
     * Test: malformed tax codes or wrong check characters are rejected.
     *
     * @dataProvider invalid_provider
     * @param string $value The tax code under test.
     * @param string $explanation Why the value is expected to be invalid.
     */
    public function test_invalid_codes(string $value, string $explanation): void {
        $validator = new codicefiscale();
        $this->assertFalse($validator->validate($validator->normalise($value)), $explanation);
    }

    /**
     * Test: normalisation uppercases and trims but does not otherwise alter the value.
     */
    public function test_normalise(): void {
        $validator = new codicefiscale();
        $this->assertSame('BDRNLT79P41D969B', $validator->normalise(' bdrnlt79p41d969b '));
        $this->assertSame('BDRNLT79P41D969B', $validator->normalise("BDRNLT79P41D969B\n"));
    }

    /**
     * Test: the validator exposes its identifier and a translated error message.
     */
    public function test_metadata(): void {
        $validator = new codicefiscale();
        $this->assertSame('codicefiscale', $validator->get_name());
        $this->assertNotEmpty($validator->get_display_name());
        $this->assertNotEmpty($validator->get_error_message());
    }
}
