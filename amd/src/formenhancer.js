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
 * Adds required indicators and empty default options for configured fields
 * on the profile edit page.
 *
 * @module     local_forceprofile/formenhancer
 * @copyright  2026 Oltrematica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    return {
        /**
         * Initialise the form enhancer.
         *
         * @param {string[]} fieldShortnames - List of configured field shortnames.
         * @param {string[]} incompleteFields - List of shortnames that are currently incomplete.
         */
        init: function(fieldShortnames, incompleteFields) {
            fieldShortnames.forEach(function(shortname) {
                var el = document.getElementById('id_profile_field_' + shortname);
                if (!el) {
                    return;
                }

                // 1. Add required icon next to the field label.
                var fitem = el.closest('.fitem');
                if (fitem) {
                    var addon = fitem.querySelector('.form-label-addon');
                    if (addon && !addon.querySelector('.fa-circle-exclamation')) {
                        var reqHtml = '<div class="text-danger" title="Compilazione obbligatoria">' +
                            '<i class="icon fa fa-circle-exclamation text-danger fa-fw " ' +
                            'title="Compilazione obbligatoria" role="img" ' +
                            'aria-label="Compilazione obbligatoria"></i></div>';
                        addon.insertAdjacentHTML('afterbegin', reqHtml);
                    }
                }

                // 2. For SELECT fields that are incomplete, prepend an empty "Scegli..." option.
                if (el.tagName === 'SELECT' && incompleteFields.indexOf(shortname) !== -1) {
                    var hasEmpty = false;
                    for (var i = 0; i < el.options.length; i++) {
                        if (el.options[i].value === '') {
                            hasEmpty = true;
                            break;
                        }
                    }
                    if (!hasEmpty) {
                        var emptyOpt = document.createElement('option');
                        emptyOpt.value = '';
                        emptyOpt.text = 'Scegli...';
                        el.insertBefore(emptyOpt, el.firstChild);
                        el.selectedIndex = 0;
                    }
                }
            });
        }
    };
});
