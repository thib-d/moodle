<?php
// This file is part of the customcert module for Moodle - http://moodle.org/
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
 * This file contains the customcert element tenantlogo's core interaction API.
 *
 * Unlike the built-in "image" element, this element never stores an uploaded
 * file of its own. It resolves the logo of the certificate recipient's
 * tool_mutenancy tenant at render time (falling back to the site-wide admin
 * logo), so a single certificate template works for every whitelabel
 * customer without needing one template per tenant.
 *
 * @package    customcertelement_tenantlogo
 * @copyright  2026 GottaPhish
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customcertelement_tenantlogo;

/**
 * The customcert element tenantlogo's core interaction API.
 *
 * @package    customcertelement_tenantlogo
 * @copyright  2026 GottaPhish
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class element extends \mod_customcert\element {
    /**
     * This function renders the form elements when adding a customcert element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function render_form_elements($mform) {
        \mod_customcert\element_helper::render_form_element_width($mform);

        \mod_customcert\element_helper::render_form_element_height($mform);

        if (get_config('customcert', 'showposxy')) {
            \mod_customcert\element_helper::render_form_element_position($mform);
        }
    }

    /**
     * Performs validation on the element values.
     *
     * @param array $data the submitted data
     * @param array $files the submitted files
     * @return array the validation errors
     */
    public function validate_form_elements($data, $files) {
        $errors = [];

        $errors += \mod_customcert\element_helper::validate_form_element_width($data);
        $errors += \mod_customcert\element_helper::validate_form_element_height($data);

        if (get_config('customcert', 'showposxy')) {
            $errors += \mod_customcert\element_helper::validate_form_element_position($data);
        }

        return $errors;
    }

    /**
     * This will handle how form data will be saved into the data column in the
     * customcert_elements table.
     *
     * @param \stdClass $data the form data
     * @return string the json encoded array
     */
    public function save_unique_data($data) {
        $arrtostore = [
            'width' => !empty($data->width) ? (int) $data->width : 0,
            'height' => !empty($data->height) ? (int) $data->height : 0,
        ];

        return json_encode($arrtostore);
    }

    /**
     * Handles rendering the element on the pdf.
     *
     * @param \pdf $pdf the pdf object
     * @param bool $preview true if it is a preview, false otherwise
     * @param \stdClass $user the user we are rendering this for
     */
    public function render($pdf, $preview, $user) {
        $file = self::get_tenant_logo_file(!empty($user->id) ? (int) $user->id : 0);
        if (!$file) {
            return;
        }

        $sizeinfo = json_decode($this->get_data());
        $width = !empty($sizeinfo->width) ? (int) $sizeinfo->width : 0;
        $height = !empty($sizeinfo->height) ? (int) $sizeinfo->height : 0;

        $location = make_request_directory() . '/tenantlogo';
        $file->copy_content_to($location);

        if ($file->get_mimetype() == 'image/svg+xml') {
            $pdf->ImageSVG($location, $this->get_posx(), $this->get_posy(), $width, $height);
        } else {
            $pdf->Image($location, $this->get_posx(), $this->get_posy(), $width, $height);
        }
    }

    /**
     * Render the element in html.
     *
     * This function is used to render the element when we are using the
     * drag and drop interface to position it. There is no certificate
     * recipient in this context, so we preview using the currently logged in
     * user's own tenant (or the site logo, if they are not in one).
     *
     * @return string the html
     */
    public function render_html() {
        global $USER;

        $file = self::get_tenant_logo_file((int) $USER->id);
        if (!$file) {
            return '';
        }

        $sizeinfo = json_decode($this->get_data());
        $width = !empty($sizeinfo->width) ? (int) $sizeinfo->width : 0;
        $height = !empty($sizeinfo->height) ? (int) $sizeinfo->height : 0;

        $url = \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        );

        $style = '';
        if ($width || $height) {
            if ($width) {
                $style .= 'width: ' . $width . 'mm; ';
            }
            if ($height) {
                $style .= 'height: ' . $height . 'mm';
            }
        }

        return \html_writer::tag('img', '', ['src' => $url, 'style' => $style]);
    }

    /**
     * Sets the data on the form when editing an element.
     *
     * @param \MoodleQuickForm $mform the edit_form instance
     */
    public function definition_after_data($mform) {
        if (!empty($this->get_data())) {
            $sizeinfo = json_decode($this->get_data());

            if (isset($sizeinfo->width) && $mform->elementExists('width')) {
                $mform->getElement('width')->setValue($sizeinfo->width);
            }

            if (isset($sizeinfo->height) && $mform->elementExists('height')) {
                $mform->getElement('height')->setValue($sizeinfo->height);
            }
        }

        parent::definition_after_data($mform);
    }

    /**
     * Resolves the logo to use for a given recipient: their tool_mutenancy
     * tenant's logo if one is configured, otherwise the site-wide admin logo.
     * Returns null if neither exists (e.g. tool_mutenancy isn't installed, or
     * no logo has ever been uploaded anywhere).
     *
     * @param int $userid the certificate recipient, 0 if unknown
     * @return \stored_file|null
     */
    protected static function get_tenant_logo_file(int $userid): ?\stored_file {
        $contextid = null;
        $logo = null;

        if ($userid && class_exists('\tool_mutenancy\local\tenancy') && \tool_mutenancy\local\tenancy::is_active()) {
            $tenantid = \tool_mutenancy\local\tenancy::get_user_tenantid($userid);
            if ($tenantid && \tool_mutenancy\local\config::is_overridden($tenantid, 'core_admin', 'logo')) {
                $logo = \tool_mutenancy\local\config::get($tenantid, 'core_admin', 'logo');
                $contextid = \context_tenant::instance($tenantid)->id;
            }
        }

        if (!$logo) {
            $logo = get_config('core_admin', 'logo');
            $contextid = \context_system::instance()->id;
        }

        if (!$logo) {
            return null;
        }

        $fs = get_file_storage();
        $file = $fs->get_file($contextid, 'core_admin', 'logo', 0, '/', $logo);

        return $file ?: null;
    }
}
