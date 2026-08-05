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

namespace local_forceprofile;

use local_forceprofile\validator\validator_interface;

/**
 * Registry of the named validators available to the plugin settings.
 *
 * The plugin ships a small set of built-in validators, addressed by short
 * name. Any class implementing {@see validator_interface} can also be used by
 * writing its fully qualified name in the settings, so a site can add its own
 * checks without patching this plugin.
 *
 * @package    local_forceprofile
 * @copyright  2026 Oltrematica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class validator_manager {

    /** @var array<string,class-string<validator_interface>> Built-in validators, keyed by short name. */
    private const BUILTIN = [
        'codicefiscale' => validator\codicefiscale::class,
    ];

    /**
     * List the built-in validators.
     *
     * @return validator_interface[] Instances keyed by short name.
     */
    public static function get_builtin_validators(): array {
        $validators = [];
        foreach (array_keys(self::BUILTIN) as $name) {
            $validators[$name] = self::instantiate($name);
        }

        return $validators;
    }

    /**
     * Resolve a validator by short name or by fully qualified class name.
     *
     * @param string $name A built-in short name, or a class implementing validator_interface.
     * @return validator_interface|null Null when the name cannot be resolved.
     */
    public static function instantiate(string $name): ?validator_interface {
        $name = trim($name);

        if (isset(self::BUILTIN[$name])) {
            $class = self::BUILTIN[$name];
            return new $class();
        }

        // Allow other plugins to plug in their own validator classes.
        $class = ltrim($name, '\\');
        if (class_exists($class) && is_subclass_of($class, validator_interface::class)) {
            return new $class();
        }

        return null;
    }
}
