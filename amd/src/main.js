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
 * Office viewer module
 *
 * @module     local_ivofficeviewer/main
 * @copyright  2024 Sokunthearith Makara <sokunthearithmakara@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import $ from 'jquery';
import Iframe from 'ivplugin_iframe/main';
export default class OfficeViewer extends Iframe {
    /**
     * Renders the container for the given annotation.
     *
     * @param {Object} annotation - The annotation object.
     * @param {string} annotation.id - The ID of the annotation.
     */
    renderContainer(annotation) {
        let $message = $(`#message[data-id='${annotation.id}']`);
        $message.addClass("hasiframe");
        super.renderContainer(annotation);
    }
    /**
     * Runs the interaction for the given annotation.
     *
     * @param {Object} annotation - The annotation object containing interaction details.
     * @param {number} annotation.id - The unique identifier for the annotation.
     * @param {boolean} annotation.completed - Indicates if the annotation has been completed.
     * @param {number} annotation.hascompletion - Indicates if the annotation has completion tracking.
     * @param {string} annotation.completiontracking - The type of completion tracking for the annotation.
     * @param {string} annotation.displayoptions - The display options for the annotation.
     *
     * @returns {Promise<void>} - A promise that resolves when the interaction is complete.
     */
    async runInteraction(annotation) {
        await this.player.pause();

        // Check if device is online.
        if (!navigator.onLine) {
            this.addNotification('You need to be online to view this content.');
            this.player.play();
            return;
        }

        let self = this;

        // Apply content.
        const applyContent = async(annotation) => {
            const data = await this.render(annotation, 'html');
            $(`#message[data-id='${annotation.id}'] .modal-body`).attr('id', 'content').html(data).fadeIn(300);
            if (annotation.hascompletion == 0 || annotation.completed) {
                this.postContentRender(annotation);
                return;
            }
            if (annotation.completiontracking == 'view') {
                this.postContentRender(annotation);
                this.toggleCompletion(annotation.id, "mark-done", "automatic");
                return;
            }
        };

        await this.renderViewer(annotation);
        this.renderContainer(annotation);
        applyContent(annotation);

        if (annotation.displayoptions == 'popup') {
            $('#annotation-modal').on('shown.bs.modal', function() {
                self.setModalDraggable('#annotation-modal .modal-dialog');
            });
        }

        if (annotation.completiontracking == 'manual') {
            this.enableManualCompletion(annotation);
        }
    }
}