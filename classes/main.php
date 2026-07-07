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
 * Class main
 *
 * @package    local_ivofficeviewer
 * @copyright  2024 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class main extends \ivplugin_richtext\main {
    /**
     * Get the property.
     */
    public function get_property() {
        return [
            'name' => 'officeviewer',
            'icon' => 'bi bi-file-word',
            'title' => get_string('officeviewercontent', 'local_ivofficeviewer'),
            'amdmodule' => 'local_ivofficeviewer/main',
            'class' => 'local_ivofficeviewer\\main',
            'form' => 'local_ivofficeviewer\\form',
            'hascompletion' => true,
            'hastimestamp' => true,
            'hasreport' => true,
            'description' => get_string('officeviewerdescription', 'local_ivofficeviewer'),
            'author' => 'tsmakara',
            'authorlink' => 'mailto:sokunthearithmakara@gmail.com',
            'tutorial' => get_string('tutorialurl', 'local_ivofficeviewer'),
            'preloadstrings' => false,
            'flexbook' => true,
            'fbdescription' => get_string('fbdescription', 'local_ivofficeviewer'),
            'fbamdmodule' => 'local_ivofficeviewer/fbmain',
            'fbform' => 'local_ivofficeviewer\\fbform',
            'dndextensions' => ['odt', 'docx', 'doc', 'odp', 'pptx', 'ppt', 'xlsm', 'xlsx', 'xls', 'ods'],
            'component' => 'local_ivofficeviewer',
        ];
    }

    /**
     * Flexbook DND / programmatic creation fields merged from flexbook_defaults.
     *
     * @return string[]
     */
    public function get_dnd_default_fields(): array {
        return array_merge(parent::get_dnd_default_fields(), ['char2']);
    }

    /**
     * Apply completion and advanced defaults after DND merge.
     *
     * @param \stdClass $data
     * @return void
     */
    protected function apply_creation_defaults(\stdClass $data): void {
        $courseid = (int) ($data->courseid ?? 0);
        if (!$this->has_flexbook_course_defaults($courseid, 'officeviewer')) {
            $data->advanced = json_encode($this->flexbook_advanced());
        } else {
            $this->normalize_merged_completion($data);
            if (empty($data->advanced)) {
                $data->advanced = json_encode($this->flexbook_advanced());
            }
        }
    }

    /**
     * Create a new interaction instance.
     *
     * @param array $data The data for the new instance.
     * @return \stdClass The newly created interaction record.
     */
    public function create_instance($data) {
        global $DB, $CFG;
        $data = (object) $data;
        $draftitemid = $data->draftitemid;
        unset($data->draftitemid);

        $this->apply_creation_defaults($data);

        if (!$this->has_flexbook_course_defaults((int) ($data->courseid ?? 0), 'officeviewer')) {
            $data->char2 = helper::VIEWER_OFFICE365;
        }

        $data->id = $DB->insert_record('flexbook_items', $data);

        // Save files from draft area.
        if ($draftitemid) {
            require_once($CFG->libdir . '/filelib.php');
            \file_save_draft_area_files(
                $draftitemid,
                $data->contextid,
                'mod_flexbook',
                'content',
                $data->id,
                ['subdirs' => 0, 'maxfiles' => 1]
            );
        }

        return \mod_flexbook\util::get_item($data->id, $data->contextid);
    }

    /**
     * Resolves the document URL for the selected source.
     *
     * @param array $arg The arguments.
     * @return string
     */
    protected function resolve_document_url($arg) {
        $iframeurl = helper::normalize_iframeurl($arg['iframeurl'] ?? '');
        if (!empty($iframeurl)) {
            return $iframeurl;
        }

        $plugin = 'mod_' . (isset($arg['plugin']) ? $arg['plugin'] : 'interactivevideo');
        $fs = get_file_storage();
        $files = $fs->get_area_files($arg['contextid'], $plugin, 'public', $arg['id'], 'id DESC', false);
        $file = reset($files);

        if (!$file) {
            // Copy file from 'content' to 'public' area.
            $files = $fs->get_area_files($arg['contextid'], $plugin, 'content', $arg['id'], 'id DESC', false);
            $file = reset($files);
            if ($file) {
                $fileinfo = [
                    'contextid' => $file->get_contextid(),
                    'component' => $plugin,
                    'filearea' => 'public',
                    'itemid' => $file->get_itemid(),
                    'filepath' => '/',
                    'filename' => $file->get_filename(),
                ];
                $file = $fs->create_file_from_storedfile($fileinfo, $file);
            }
        }

        if (!$file) {
            return '';
        }

        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
        )->out();
    }

    /**
     * Get the content.
     *
     * @param array $arg The arguments.
     * @return string The content.
     */
    public function get_content($arg) {
        $documenturl = $this->resolve_document_url($arg);
        if (empty($documenturl)) {
            return '<div class="alert alert-danger" role="alert">' . get_string('nofile', 'local_ivofficeviewer') . '</div>';
        }

        $viewer = helper::normalize_viewer($arg['char2'] ?? '');
        $embedurl = helper::build_viewer_embed_url($documenturl, $viewer);

        return '<iframe id="iframe" src="' . s($embedurl) .
            '" style="width: 100%; height: 100%" frameborder="0" allow="autoplay" class="iv-rounded-0"></iframe>';
    }
}
