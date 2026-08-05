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
 * Form enhancer for local_forceprofile.
 *
 * Flags the profile fields that are empty or hold an invalid value on the
 * profile edit page, so the user can see at a glance what has to be fixed.
 * Fields that are already correct are left untouched.
 *
 * @module     local_forceprofile/formenhancer
 * @copyright  2026 Oltrematica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Escape a string for safe injection into an HTML attribute.
     *
     * @param {string} text - The raw text.
     * @return {string} The escaped text.
     */
    var escapeAttribute = function(text) {
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    };

    /**
     * Add the warning icon next to a field label.
     *
     * @param {HTMLElement} element - The form element of the profile field.
     * @param {string} message - The reason shown in the tooltip.
     */
    var markField = function(element, message) {
        var fitem = element.closest('.fitem');
        if (!fitem) {
            return;
        }

        var addon = fitem.querySelector('.form-label-addon');
        if (!addon || addon.querySelector('.local-forceprofile-flag')) {
            return;
        }

        var title = escapeAttribute(message);
        addon.insertAdjacentHTML('afterbegin',
            '<div class="text-danger local-forceprofile-flag" title="' + title + '">' +
            '<i class="icon fa fa-circle-exclamation text-danger fa-fw" ' +
            'title="' + title + '" role="img" aria-label="' + title + '"></i></div>');
    };

    /**
     * Prepend an empty option to a select that has no value yet.
     *
     * Without it the first option looks like a deliberate choice, and the user
     * can save the form without ever touching the field.
     *
     * @param {HTMLSelectElement} element - The select element.
     * @param {string} chooseLabel - Localised "Choose..." label.
     */
    var addEmptyOption = function(element, chooseLabel) {
        for (var i = 0; i < element.options.length; i++) {
            if (element.options[i].value === '') {
                return;
            }
        }

        var emptyOption = document.createElement('option');
        emptyOption.value = '';
        emptyOption.text = chooseLabel;
        element.insertBefore(emptyOption, element.firstChild);
        element.selectedIndex = 0;
    };

    return {
        /**
         * Initialise the form enhancer.
         *
         * @param {Object[]} problemFields - Fields needing attention.
         * @param {string} problemFields[].shortname - Profile field shortname.
         * @param {string} problemFields[].reason - Either "empty" or "invalid".
         * @param {string} problemFields[].message - Localised explanation.
         * @param {string} chooseLabel - Localised "Choose..." label for empty selects.
         */
        init: function(problemFields, chooseLabel) {
            if (!problemFields || !problemFields.length) {
                return;
            }

            problemFields.forEach(function(field) {
                var element = document.getElementById('id_profile_field_' + field.shortname);
                if (!element) {
                    return;
                }

                markField(element, field.message);

                if (element.tagName === 'SELECT' && field.reason === 'empty') {
                    addEmptyOption(element, chooseLabel);
                }
            });
        }
    };
});
