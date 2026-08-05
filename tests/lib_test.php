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
 * Unit tests for local_forceprofile lib functions.
 *
 * @package    local_forceprofile
 * @copyright  2026 Oltrematica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_forceprofile;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/forceprofile/lib.php');

/**
 * Tests for lib.php functions.
 *
 * @covers ::local_forceprofile_has_incomplete_fields
 * @covers ::local_forceprofile_get_incomplete_fields
 * @covers ::local_forceprofile_get_field_problems
 * @covers ::local_forceprofile_get_validation_patterns
 * @covers ::local_forceprofile_get_field_validators
 * @covers ::local_forceprofile_validate_extend_signup_form
 * @covers ::local_forceprofile_record_completion
 */
class lib_test extends \advanced_testcase {

    /**
     * Helper: create a custom profile field.
     *
     * @param string $shortname
     * @param string $datatype
     * @return int Field ID.
     */
    private function create_profile_field(string $shortname, string $datatype = 'text'): int {
        global $DB;
        $field = new \stdClass();
        $field->shortname = $shortname;
        $field->name = ucfirst($shortname);
        $field->datatype = $datatype;
        $field->categoryid = 1; // Default category.
        $field->sortorder = $DB->count_records('user_info_field') + 1;
        return $DB->insert_record('user_info_field', $field);
    }

    /**
     * Helper: set a profile field value for a user.
     *
     * @param int $userid
     * @param int $fieldid
     * @param string $data
     */
    private function set_profile_field_data(int $userid, int $fieldid, string $data): void {
        global $DB;
        $existing = $DB->get_record('user_info_data', ['userid' => $userid, 'fieldid' => $fieldid]);
        if ($existing) {
            $existing->data = $data;
            $DB->update_record('user_info_data', $existing);
        } else {
            $record = new \stdClass();
            $record->userid = $userid;
            $record->fieldid = $fieldid;
            $record->data = $data;
            $DB->insert_record('user_info_data', $record);
        }
    }

    /**
     * Test: user with all fields filled is not flagged as incomplete.
     */
    public function test_complete_user_not_flagged(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $fieldid1 = $this->create_profile_field('testfield1');
        $fieldid2 = $this->create_profile_field('testfield2');

        $this->set_profile_field_data($user->id, $fieldid1, 'value1');
        $this->set_profile_field_data($user->id, $fieldid2, 'value2');

        $result = local_forceprofile_has_incomplete_fields($user->id, ['testfield1', 'testfield2']);
        $this->assertFalse($result);
    }

    /**
     * Test: user with empty field is flagged as incomplete.
     */
    public function test_empty_field_flagged(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $fieldid1 = $this->create_profile_field('testfield1');
        $fieldid2 = $this->create_profile_field('testfield2');

        $this->set_profile_field_data($user->id, $fieldid1, 'value1');
        $this->set_profile_field_data($user->id, $fieldid2, '');

        $result = local_forceprofile_has_incomplete_fields($user->id, ['testfield1', 'testfield2']);
        $this->assertTrue($result);
    }

    /**
     * Test: user with missing data record is flagged as incomplete.
     */
    public function test_missing_data_record_flagged(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->create_profile_field('testfield1');
        // No data record created for this field.

        $result = local_forceprofile_has_incomplete_fields($user->id, ['testfield1']);
        $this->assertTrue($result);
    }

    /**
     * Test: non-existent shortname is silently skipped (not treated as incomplete).
     */
    public function test_nonexistent_shortname_skipped(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $fieldid = $this->create_profile_field('realfield');
        $this->set_profile_field_data($user->id, $fieldid, 'value');

        // 'fakefield' does not exist — should be skipped, only 'realfield' counts.
        $result = local_forceprofile_has_incomplete_fields($user->id, ['realfield', 'fakefield']);
        $this->assertFalse($result);
    }

    /**
     * Test: all shortnames non-existent returns not incomplete (nothing to enforce).
     */
    public function test_all_nonexistent_shortnames(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $result = local_forceprofile_has_incomplete_fields($user->id, ['fake1', 'fake2']);
        $this->assertFalse($result);
    }

    /**
     * Test: empty shortnames array returns not incomplete.
     */
    public function test_empty_shortnames(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $result = local_forceprofile_has_incomplete_fields($user->id, []);
        $this->assertFalse($result);
    }

    /**
     * Test: get_incomplete_fields returns correct list of incomplete fields.
     */
    public function test_get_incomplete_fields_returns_correct_list(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $fieldid1 = $this->create_profile_field('field_a');
        $fieldid2 = $this->create_profile_field('field_b');
        $fieldid3 = $this->create_profile_field('field_c');

        $this->set_profile_field_data($user->id, $fieldid1, 'filled');
        $this->set_profile_field_data($user->id, $fieldid2, '');
        // field_c has no data record.

        $incomplete = local_forceprofile_get_incomplete_fields(
            $user->id,
            ['field_a', 'field_b', 'field_c']
        );

        $this->assertContains('field_b', $incomplete);
        $this->assertContains('field_c', $incomplete);
        $this->assertNotContains('field_a', $incomplete);
    }

    /**
     * Test: regex validation — valid value passes.
     */
    public function test_regex_validation_passes(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $fieldid = $this->create_profile_field('taxcode');
        $this->set_profile_field_data($user->id, $fieldid, 'RSSMRA85M01H501Z');

        $patterns = ['taxcode' => '/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/i'];
        $incomplete = local_forceprofile_get_incomplete_fields($user->id, ['taxcode'], $patterns);

        $this->assertEmpty($incomplete);
    }

    /**
     * Test: regex validation — invalid value fails.
     */
    public function test_regex_validation_fails(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $fieldid = $this->create_profile_field('taxcode');
        $this->set_profile_field_data($user->id, $fieldid, 'invalid-code');

        $patterns = ['taxcode' => '/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/i'];
        $incomplete = local_forceprofile_get_incomplete_fields($user->id, ['taxcode'], $patterns);

        $this->assertContains('taxcode', $incomplete);
    }

    /**
     * Test: field with value but no regex pattern is considered valid.
     */
    public function test_field_without_pattern_accepted(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $fieldid = $this->create_profile_field('nopattern');
        $this->set_profile_field_data($user->id, $fieldid, 'anything');

        $patterns = []; // No patterns configured.
        $incomplete = local_forceprofile_get_incomplete_fields($user->id, ['nopattern'], $patterns);

        $this->assertEmpty($incomplete);
    }

    /**
     * Test: parsing validation patterns from settings format.
     */
    public function test_get_validation_patterns(): void {
        $this->resetAfterTest();

        set_config('validation', "taxcode:/^[A-Z]{6}$/i\nprofession:/^.{2,}$/", 'local_forceprofile');

        $patterns = local_forceprofile_get_validation_patterns();

        $this->assertArrayHasKey('taxcode', $patterns);
        $this->assertArrayHasKey('profession', $patterns);
        $this->assertEquals('/^[A-Z]{6}$/i', $patterns['taxcode']);
        $this->assertEquals('/^.{2,}$/', $patterns['profession']);
    }

    /**
     * Test: invalid regex in patterns is skipped.
     */
    public function test_invalid_regex_skipped(): void {
        $this->resetAfterTest();

        set_config('validation', "good:/^ok$/\nbad:/[invalid", 'local_forceprofile');

        $patterns = local_forceprofile_get_validation_patterns();

        $this->assertArrayHasKey('good', $patterns);
        $this->assertArrayNotHasKey('bad', $patterns);
    }

    /**
     * Test: record_completion stores timestamp.
     */
    public function test_record_completion(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();

        $before = time();
        local_forceprofile_record_completion($user->id);
        $after = time();

        $record = $DB->get_record('local_forceprofile_compl', ['userid' => $user->id]);
        $this->assertNotFalse($record);
        $this->assertGreaterThanOrEqual($before, $record->timecompleted);
        $this->assertLessThanOrEqual($after, $record->timecompleted);
    }

    /**
     * Test: record_completion updates timestamp on second call.
     */
    public function test_record_completion_updates(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();

        local_forceprofile_record_completion($user->id);
        $first = $DB->get_record('local_forceprofile_compl', ['userid' => $user->id]);

        // Wait a tiny bit to ensure different timestamp.
        sleep(1);
        local_forceprofile_record_completion($user->id);
        $second = $DB->get_record('local_forceprofile_compl', ['userid' => $user->id]);

        $this->assertEquals($first->id, $second->id); // Same record.
        $this->assertGreaterThanOrEqual($first->timecompleted, $second->timecompleted);
    }

    /**
     * Test: profile_completed event is fired on first completion.
     */
    public function test_profile_completed_event_fired(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $sink = $this->redirectEvents();

        local_forceprofile_record_completion($user->id);

        $events = $sink->get_events();
        $sink->close();

        $found = false;
        foreach ($events as $event) {
            if ($event instanceof \local_forceprofile\event\profile_completed) {
                $this->assertEquals($user->id, $event->userid);
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'profile_completed event should have been fired');
    }

    /**
     * Test: profile_completed event NOT fired on re-completion (update).
     */
    public function test_profile_completed_event_not_fired_on_update(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        local_forceprofile_record_completion($user->id);

        $sink = $this->redirectEvents();
        local_forceprofile_record_completion($user->id);
        $events = $sink->get_events();
        $sink->close();

        $found = false;
        foreach ($events as $event) {
            if ($event instanceof \local_forceprofile\event\profile_completed) {
                $found = true;
                break;
            }
        }
        $this->assertFalse($found, 'profile_completed event should NOT fire on update');
    }

    /**
     * Test: parsing named validators from settings format.
     */
    public function test_get_field_validators(): void {
        $this->resetAfterTest();

        set_config('validators', "CF:codicefiscale\n  taxcode : codicefiscale  ", 'local_forceprofile');

        $validators = local_forceprofile_get_field_validators();

        $this->assertArrayHasKey('CF', $validators);
        $this->assertArrayHasKey('taxcode', $validators);
        $this->assertInstanceOf(\local_forceprofile\validator\codicefiscale::class, $validators['CF']);
        $this->assertInstanceOf(\local_forceprofile\validator\codicefiscale::class, $validators['taxcode']);
    }

    /**
     * Test: unknown validator names are skipped instead of breaking the check.
     */
    public function test_get_field_validators_unknown_skipped(): void {
        $this->resetAfterTest();

        set_config('validators', "CF:codicefiscale\nother:doesnotexist", 'local_forceprofile');

        $validators = local_forceprofile_get_field_validators();
        $this->assertDebuggingCalled();

        $this->assertArrayHasKey('CF', $validators);
        $this->assertArrayNotHasKey('other', $validators);
    }

    /**
     * Test: an empty validators setting yields an empty list.
     */
    public function test_get_field_validators_empty(): void {
        $this->resetAfterTest();

        set_config('validators', '', 'local_forceprofile');
        $this->assertSame([], local_forceprofile_get_field_validators());
    }

    /**
     * Test: a tax code with a wrong check character is flagged as invalid.
     */
    public function test_validator_detects_wrong_check_character(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $fieldid = $this->create_profile_field('CF');
        $this->set_profile_field_data($user->id, $fieldid, 'ADRNLT79P41D969B');

        $validators = ['CF' => new \local_forceprofile\validator\codicefiscale()];
        $incomplete = local_forceprofile_get_incomplete_fields($user->id, ['CF'], [], $validators);

        $this->assertContains('CF', $incomplete);
    }

    /**
     * Test: a valid tax code passes the validator.
     */
    public function test_validator_accepts_valid_tax_code(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $fieldid = $this->create_profile_field('CF');
        $this->set_profile_field_data($user->id, $fieldid, 'BDRNLT79P41D969B');

        $validators = ['CF' => new \local_forceprofile\validator\codicefiscale()];
        $incomplete = local_forceprofile_get_incomplete_fields($user->id, ['CF'], [], $validators);

        $this->assertEmpty($incomplete);
    }

    /**
     * Test: values stored lowercase are normalised before validation.
     *
     * Roughly half of the real records are stored lowercase or mixed case.
     */
    public function test_validator_normalises_stored_value(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $fieldid = $this->create_profile_field('CF');
        $this->set_profile_field_data($user->id, $fieldid, ' bdrnlt79p41d969b ');

        $validators = ['CF' => new \local_forceprofile\validator\codicefiscale()];
        $incomplete = local_forceprofile_get_incomplete_fields($user->id, ['CF'], [], $validators);

        $this->assertEmpty($incomplete);
    }

    /**
     * Test: regex and named validator can be combined on the same field.
     */
    public function test_regex_and_validator_combined(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $fieldid = $this->create_profile_field('CF');
        // Formally a tax code, but the check character is wrong.
        $this->set_profile_field_data($user->id, $fieldid, 'ADRNLT79P41D969B');

        $patterns = ['CF' => '/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/i'];
        $validators = ['CF' => new \local_forceprofile\validator\codicefiscale()];

        // The regex alone accepts it.
        $this->assertEmpty(local_forceprofile_get_incomplete_fields($user->id, ['CF'], $patterns));
        // The validator catches it.
        $this->assertContains('CF', local_forceprofile_get_incomplete_fields($user->id, ['CF'], $patterns, $validators));
    }

    /**
     * Test: field problems distinguish an empty field from an invalid one.
     */
    public function test_get_field_problems_reasons(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $cfid = $this->create_profile_field('CF');
        $this->create_profile_field('profession');
        $okid = $this->create_profile_field('city');

        $this->set_profile_field_data($user->id, $cfid, 'ADRNLT79P41D969B');
        $this->set_profile_field_data($user->id, $okid, 'Rimini');

        $validators = ['CF' => new \local_forceprofile\validator\codicefiscale()];
        $problems = local_forceprofile_get_field_problems(
            $user->id,
            ['CF', 'profession', 'city'],
            [],
            $validators
        );

        $this->assertArrayHasKey('CF', $problems);
        $this->assertArrayHasKey('profession', $problems);
        $this->assertArrayNotHasKey('city', $problems);

        $this->assertSame(LOCAL_FORCEPROFILE_REASON_INVALID, $problems['CF']->reason);
        $this->assertSame(LOCAL_FORCEPROFILE_REASON_EMPTY, $problems['profession']->reason);

        // The message must name the field so the user knows what to fix.
        $this->assertStringContainsString('CF', $problems['CF']->message);
        $this->assertNotEmpty($problems['profession']->message);
    }

    /**
     * Test: get_incomplete_fields keeps returning a plain list of shortnames.
     */
    public function test_get_incomplete_fields_backward_compatible(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->create_profile_field('field_a');

        $incomplete = local_forceprofile_get_incomplete_fields($user->id, ['field_a']);

        $this->assertSame(['field_a'], $incomplete);
    }

    /**
     * Test: signup validation rejects an invalid tax code on the self-registration form.
     */
    public function test_signup_validation_rejects_invalid_value(): void {
        $this->resetAfterTest();

        set_config('enabled', 1, 'local_forceprofile');
        set_config('fields', 'CF', 'local_forceprofile');
        set_config('validators', 'CF:codicefiscale', 'local_forceprofile');
        set_config('signupvalidation', 1, 'local_forceprofile');

        $errors = local_forceprofile_validate_extend_signup_form(['profile_field_CF' => 'ADRNLT79P41D969B']);

        $this->assertArrayHasKey('profile_field_CF', $errors);
        $this->assertNotEmpty($errors['profile_field_CF']);
    }

    /**
     * Test: signup validation accepts a valid tax code.
     */
    public function test_signup_validation_accepts_valid_value(): void {
        $this->resetAfterTest();

        set_config('enabled', 1, 'local_forceprofile');
        set_config('fields', 'CF', 'local_forceprofile');
        set_config('validators', 'CF:codicefiscale', 'local_forceprofile');
        set_config('signupvalidation', 1, 'local_forceprofile');

        $errors = local_forceprofile_validate_extend_signup_form(['profile_field_CF' => 'bdrnlt79p41d969b']);

        $this->assertSame([], $errors);
    }

    /**
     * Test: signup validation requires configured fields that are present on the form.
     */
    public function test_signup_validation_requires_empty_field(): void {
        $this->resetAfterTest();

        set_config('enabled', 1, 'local_forceprofile');
        set_config('fields', "CF\nprofession", 'local_forceprofile');
        set_config('signupvalidation', 1, 'local_forceprofile');

        $errors = local_forceprofile_validate_extend_signup_form([
            'profile_field_CF' => '   ',
            // 'profession' is not on the signup form at all.
        ]);

        $this->assertArrayHasKey('profile_field_CF', $errors);
        $this->assertArrayNotHasKey('profile_field_profession', $errors);
    }

    /**
     * Test: signup validation is skipped when the plugin or the setting is off.
     */
    public function test_signup_validation_can_be_disabled(): void {
        $this->resetAfterTest();

        set_config('fields', 'CF', 'local_forceprofile');
        set_config('validators', 'CF:codicefiscale', 'local_forceprofile');

        // Plugin disabled.
        set_config('enabled', 0, 'local_forceprofile');
        set_config('signupvalidation', 1, 'local_forceprofile');
        $this->assertSame([], local_forceprofile_validate_extend_signup_form(['profile_field_CF' => 'nope']));

        // Plugin enabled but signup validation off.
        set_config('enabled', 1, 'local_forceprofile');
        set_config('signupvalidation', 0, 'local_forceprofile');
        $this->assertSame([], local_forceprofile_validate_extend_signup_form(['profile_field_CF' => 'nope']));
    }

    /**
     * Test: signup validation also honours regex patterns.
     */
    public function test_signup_validation_applies_regex(): void {
        $this->resetAfterTest();

        set_config('enabled', 1, 'local_forceprofile');
        set_config('fields', 'phone', 'local_forceprofile');
        set_config('validation', 'phone:/^\+?[0-9]{8,15}$/', 'local_forceprofile');
        set_config('signupvalidation', 1, 'local_forceprofile');

        $this->assertArrayHasKey(
            'profile_field_phone',
            local_forceprofile_validate_extend_signup_form(['profile_field_phone' => 'abc'])
        );
        $this->assertSame(
            [],
            local_forceprofile_validate_extend_signup_form(['profile_field_phone' => '+390541123456'])
        );
    }
}
