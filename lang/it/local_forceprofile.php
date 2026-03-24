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
 * Italian language strings for local_forceprofile.
 *
 * @package    local_forceprofile
 * @copyright  2026 Oltrematica
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Plugin.
$string['pluginname'] = 'Completamento profilo obbligatorio';

// Settings.
$string['setting_enabled'] = 'Abilita controllo completamento profilo';
$string['setting_enabled_desc'] = 'Se abilitato, gli utenti con campi profilo incompleti verranno reindirizzati alla pagina di modifica del profilo.';
$string['setting_fields'] = 'Campi da controllare (shortname, uno per riga)';
$string['setting_fields_desc'] = 'Inserisci gli shortname dei campi personalizzati del profilo da controllare, uno per riga.';
$string['setting_validation'] = 'Pattern di validazione campi';
$string['setting_validation_desc'] = 'Validazione regex opzionale per i campi. Uno per riga, formato: <code>shortname:/pattern/</code><br>Esempio: <code>CF:/^[A-Z]{6}[0-9]{2}[A-Z][0-9]{2}[A-Z][0-9]{3}[A-Z]$/i</code>';
$string['setting_message'] = 'Messaggio da mostrare all\'utente';
$string['setting_message_desc'] = 'Messaggio di avviso mostrato quando un utente viene reindirizzato per completare il profilo.';
$string['setting_redirecturl'] = 'URL di redirect';
$string['setting_redirecturl_desc'] = 'L\'URL dove gli utenti verranno reindirizzati per completare il profilo.';

// Notification.
$string['notification_message'] = 'Per procedere è necessario completare il profilo. Compila tutti i campi obbligatori mancanti.';

// Capabilities.
$string['forceprofile:exempt'] = 'Esente dal completamento profilo obbligatorio';
$string['forceprofile:viewstatus'] = 'Visualizza pagina stato completamento profilo';

// Privacy.
$string['privacy:metadata'] = 'Il plugin Completamento profilo obbligatorio memorizza il timestamp di quando un utente ha completato i campi profilo richiesti.';
$string['privacy:metadata:userid'] = 'L\'ID dell\'utente che ha completato il profilo.';
$string['privacy:metadata:timecompleted'] = 'Il timestamp di quando l\'utente ha completato tutti i campi profilo richiesti.';

// Events.
$string['event_profile_blocked'] = 'Utente bloccato per profilo incompleto';
$string['event_profile_completed'] = 'Utente ha completato i campi profilo richiesti';

// Status page.
$string['status_title'] = 'Stato completamento profilo';
$string['status_nofields'] = 'Nessun campo configurato. Vai nelle impostazioni del plugin per aggiungere gli shortname dei campi.';
$string['status_total_users'] = 'utenti totali';
$string['status_incomplete'] = 'incompleti';
$string['status_complete'] = 'completi';
$string['status_allusers_complete'] = 'Tutti gli utenti hanno completato i campi profilo richiesti.';
$string['status_missing_fields'] = 'Campi mancanti';
$string['status_view_profile'] = 'Visualizza';
