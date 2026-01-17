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
        ];
    }

    /**
     * Get the content.
     *
     * @param array $arg The arguments.
     * @return string The content.
     */
    public function get_content($arg) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($arg["contextid"], 'mod_interactivevideo', 'public', $arg["id"], 'id DESC', false);
        $file = reset($files);
        if ($file) {
            $url = \moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename(),
            )->out();
            // Encode URL for PDF.js.
            $url = urlencode($url);
        } else {
            // Copy file from 'content' to 'public' area.
            $files = $fs->get_area_files($arg["contextid"], 'mod_interactivevideo', 'content', $arg["id"], 'id DESC', false);
            $file = reset($files);
            if ($file) {
                $fileinfo = [
                    'contextid' => $file->get_contextid(),
                    'component' => 'mod_interactivevideo',
                    'filearea' => 'public',
                    'itemid' => $file->get_itemid(),
                    'filepath' => '/',
                    'filename' => $file->get_filename(),
                ];
                $newfile = $fs->create_file_from_storedfile($fileinfo, $file);
                $url = \moodle_url::make_pluginfile_url(
                    $newfile->get_contextid(),
                    $newfile->get_component(),
                    $newfile->get_filearea(),
                    $newfile->get_itemid(),
                    $newfile->get_filepath(),
                    $newfile->get_filename(),
                )->out();
                // Encode URL.
                $url = urlencode($url);
            }
        }
        return '<iframe id="iframe" src="https://view.officeapps.live.com/op/embed.aspx?src=' .
            $url . '" style="width: 100%; height: 100%" frameborder="0" allow="autoplay" class="iv-rounded-0"></iframe>';
    }
}
