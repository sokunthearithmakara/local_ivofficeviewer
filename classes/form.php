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

namespace local_ivofficeviewer;

/**
 * Class form
 *
 * @package    local_ivofficeviewer
 * @copyright  2024 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class form extends \mod_interactivevideo\form\base_form {
    /**
     * Sets data for dynamic submission
     * @return void
     */
    public function set_data_for_dynamic_submission(): void {
        global $CFG;

        $data = $this->set_data_default();

        require_once("{$CFG->libdir}/filelib.php");

        // Load the file in the draft area. mod_interactive, content.
        $draftitemid = file_get_submitted_draft_itemid('content');
        file_prepare_draft_area(
            $draftitemid,
            $data->contextid,
            'mod_interactivevideo',
            'content',
            $data->id
        );

        $data->content = $draftitemid;

        $this->set_data($data);
    }

    /**
     * Process dynamic submission
     *
     * @return void
     */
    public function process_dynamic_submission() {
        $fromform = parent::process_dynamic_submission();

        $draftitemid = $fromform->content;
        file_save_draft_area_files(
            $draftitemid,
            $fromform->contextid,
            'mod_interactivevideo',
            'content',
            $fromform->id,
        );

        file_save_draft_area_files(
            $draftitemid,
            $fromform->contextid,
            'mod_interactivevideo',
            'public',
            $fromform->id,
            ['subdirs' => 0, 'maxfiles' => 1]
        );

        return $fromform;
    }

    /**
     * Form definition
     *
     * @return void
     */
    public function definition() {
        global $PAGE;
        $mform = &$this->_form;

        $mform->addElement(
            'html',
            '<div class="alert alert-warning" role="alert">'
                . get_string('officeviewerpublicfilewarning', 'local_ivofficeviewer') . '</div>'
        );

        $this->standard_elements();

        $mform->addElement('text', 'title', '<i class="bi bi-quote iv-mr-2"></i>' . get_string('title', 'mod_interactivevideo'));
        $mform->setType('title', PARAM_TEXT);
        $mform->setDefault('title', get_string('defaulttitle', 'mod_interactivevideo'));
        $mform->addRule('title', get_string('required'), 'required', null, 'client');

        // Office upload.
        $filemanageroptions = [
            'maxbytes'       => $PAGE->course->maxbytes,
            'subdirs'        => 0,
            'maxfiles'       => 1,
            'accepted_types' => '.odt .docx .doc .odp .pptx .ppt .xlsm .xlsx .xls .ods',
        ];

        $mform->addElement(
            'filemanager',
            'content',
            '<i class="bi bi-file iv-mr-2"></i>' . get_string('officefile', 'local_ivofficeviewer'),
            null,
            $filemanageroptions
        );
        $mform->addRule(
            'content',
            get_string('required'),
            'required',
            null,
            'client'
        );

        $this->completion_tracking_field('none', [
            'none' => get_string('completionnone', 'mod_interactivevideo'),
            'manual' => get_string('completionmanual', 'mod_interactivevideo'),
            'view' => get_string('completiononview', 'mod_interactivevideo'),
        ]);
        $this->xp_form_field();
        $mform->hideIf('xp', 'completiontracking', 'eq', 'none');
        $this->display_options_field();
        $this->advanced_form_fields([
            'hascompletion' => true,
        ]);
        $this->close_form();
    }
}
