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
 * Helper class for Office Viewer.
 *
 * @package    local_ivofficeviewer
 * @copyright  2026 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /** @var string Office 365 viewer identifier. */
    public const VIEWER_OFFICE365 = 'office';

    /** @var string Google Drive viewer identifier. */
    public const VIEWER_GOOGLEDRIVE = 'google';

    /**
     * Normalizes iframeurl values loaded from the database.
     *
     * @param mixed $iframeurl
     * @return string
     */
    public static function normalize_iframeurl($iframeurl) {
        if ($iframeurl === null || $iframeurl === 'null') {
            return '';
        }
        return (string) $iframeurl;
    }

    /**
     * Normalizes viewer values loaded from the database.
     *
     * @param mixed $viewer
     * @return string
     */
    public static function normalize_viewer($viewer) {
        if ($viewer === null || $viewer === 'null' || $viewer === '') {
            return self::VIEWER_OFFICE365;
        }
        return (string) $viewer;
    }

    /**
     * Builds the embed URL for the selected online viewer.
     *
     * @param string $fileurl The document URL.
     * @param string $viewer The viewer identifier.
     * @return string
     */
    public static function build_viewer_embed_url($fileurl, $viewer) {
        $viewer = self::normalize_viewer($viewer);
        $encodedurl = urlencode($fileurl);

        if ($viewer === self::VIEWER_GOOGLEDRIVE) {
            return 'https://docs.google.com/viewer?url=' . $encodedurl . '&embedded=true';
        }

        return 'https://view.officeapps.live.com/op/embed.aspx?src=' . $encodedurl;
    }

    /**
     * Adds the office viewer elements to a moodleform.
     *
     * @param \MoodleQuickForm $mform The form to add elements to.
     */
    public static function add_officeviewer_elements(&$mform) {
        global $PAGE;

        $mform->addElement('text', 'title', '<i class="bi bi-quote iv-mr-2"></i>' . get_string('title', 'mod_interactivevideo'));
        $mform->setType('title', PARAM_TEXT);
        $mform->setDefault('title', get_string('defaulttitle', 'mod_interactivevideo'));
        $mform->addRule('title', get_string('required'), 'required', null, 'client');

        // Source type.
        $sourcetypes = [
            'file' => get_string('sourcefile', 'local_ivofficeviewer'),
            'url' => get_string('sourceurl', 'local_ivofficeviewer'),
        ];
        $mform->addElement(
            'select',
            'char1',
            '<i class="bi bi-box-arrow-in-right iv-mr-2"></i>' . get_string('sourcetype', 'local_ivofficeviewer'),
            $sourcetypes
        );
        $mform->setType('char1', PARAM_ALPHA);
        $mform->setDefault('char1', 'file');

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
        $mform->hideIf('content', 'char1', 'eq', 'url');

        // External URL.
        $mform->addElement(
            'text',
            'iframeurl',
            '<i class="bi bi-globe iv-mr-2"></i>' . get_string('externalurl', 'local_ivofficeviewer'),
            ['placeholder' => 'https://example.com/document.docx']
        );
        $mform->setType('iframeurl', PARAM_TEXT);
        $mform->hideIf('iframeurl', 'char1', 'eq', 'file');

        // Viewer type.
        $viewertypes = [
            self::VIEWER_OFFICE365 => get_string('vieweroffice365', 'local_ivofficeviewer'),
            self::VIEWER_GOOGLEDRIVE => get_string('viewergoogledrive', 'local_ivofficeviewer'),
        ];
        $mform->addElement(
            'select',
            'char2',
            '<i class="bi bi-window iv-mr-2"></i>' . get_string('viewertype', 'local_ivofficeviewer'),
            $viewertypes
        );
        $mform->setType('char2', PARAM_ALPHA);
        $mform->setDefault('char2', self::VIEWER_OFFICE365);
    }

    /**
     * Prepares the office data for the form.
     *
     * @param object $data The data object.
     * @param string $component The component name.
     * @return object The prepared data.
     */
    public static function prepare_office_data($data, $component) {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $draftitemid = file_get_submitted_draft_itemid('content');
        file_prepare_draft_area($draftitemid, $data->contextid, $component, 'content', $data->id);
        $data->content = $draftitemid;
        $data->iframeurl = self::normalize_iframeurl($data->iframeurl ?? '');

        if (empty($data->char1) || $data->char1 == 'null') {
            $data->char1 = !empty($data->iframeurl) ? 'url' : 'file';
        }
        $data->char2 = self::normalize_viewer($data->char2 ?? '');

        return $data;
    }

    /**
     * Saves the office data to the database.
     *
     * @param object $fromform The form data.
     * @param string $component The component name.
     */
    public static function save_office_data($fromform, $component) {
        global $DB;
        $sourcetype = isset($fromform->char1) ? $fromform->char1 : 'file';
        $table = $component === 'mod_flexbook' ? 'flexbook_items' : 'interactivevideo_items';

        if ($sourcetype === 'file') {
            // Clear URL in DB since source type is file.
            $DB->set_field($table, 'iframeurl', '', ['id' => $fromform->id]);

            if (!empty($fromform->content)) {
                $draftitemid = $fromform->content;
                file_save_draft_area_files(
                    $draftitemid,
                    $fromform->contextid,
                    $component,
                    'content',
                    $fromform->id,
                );

                file_save_draft_area_files(
                    $draftitemid,
                    $fromform->contextid,
                    $component,
                    'public',
                    $fromform->id,
                    ['subdirs' => 0, 'maxfiles' => 1]
                );
            }
        } else {
            // Source type is URL. Clear files from the content and public areas.
            $fs = get_file_storage();
            $fs->delete_area_files($fromform->contextid, $component, 'content', $fromform->id);
            $fs->delete_area_files($fromform->contextid, $component, 'public', $fromform->id);
        }
    }

    /**
     * Validates the office viewer elements.
     *
     * @param array $data The submitted form data.
     * @param array $files The submitted files.
     * @return array Array of errors.
     */
    public static function validate_officeviewer_elements($data, $files) {
        $errors = [];
        $sourcetype = isset($data['char1']) ? $data['char1'] : 'file';

        if ($sourcetype === 'file') {
            if (empty($data['content'])) {
                $errors['content'] = get_string('required');
            } else {
                $usercontext = \context_user::instance($GLOBALS['USER']->id);
                $fs = get_file_storage();
                $draftfiles = $fs->get_area_files($usercontext->id, 'user', 'draft', $data['content'], 'id', false);
                if (count($draftfiles) == 0) {
                    $errors['content'] = get_string('required');
                }
            }
        } else if ($sourcetype === 'url') {
            $iframeurl = self::normalize_iframeurl($data['iframeurl'] ?? '');
            if (empty($iframeurl)) {
                $errors['iframeurl'] = get_string('required');
            } else if (!preg_match('/^https?:\/\//i', $iframeurl) || !clean_param($iframeurl, PARAM_URL)) {
                $errors['iframeurl'] = get_string('invalidurl', 'local_ivofficeviewer');
            }
        }

        return $errors;
    }
}
