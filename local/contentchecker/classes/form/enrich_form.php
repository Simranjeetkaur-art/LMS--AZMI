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

namespace local_contentchecker\form;

use local_contentchecker\local\content_source;
use local_contentchecker\local\enrichment;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Insert a registered asset or a comparison table into an activity.
 *
 * @package    local_contentchecker
 * @copyright  2026 Arizona Medical Sciences Institute
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrich_form extends \moodleform {

    /**
     * Build the form.
     *
     * @return void
     */
    protected function definition(): void {
        $mform = $this->_form;
        $courseid = (int) $this->_customdata['courseid'];

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        // Only activities this plugin can actually read and write are offered,
        // so an editor cannot pick a target the insert would then refuse.
        $targets = [];
        foreach (content_source::for_course($courseid) as $item) {
            $targets[$item->cmid . ':' . $item->recordid] =
                get_string('enrich:target', 'local_contentchecker', (object) [
                    'section' => $item->sectionnum,
                    'name' => $item->name,
                ]);
        }

        if (!$targets) {
            $mform->addElement('static', 'notargets', '',
                get_string('enrich:notargets', 'local_contentchecker'));
            return;
        }

        $mform->addElement('select', 'target',
            get_string('enrich:activity', 'local_contentchecker'), $targets);
        $mform->addRule('target', null, 'required', null, 'client');

        $kinds = [
            'asset' => get_string('enrich:kind:asset', 'local_contentchecker'),
            'table' => get_string('enrich:kind:table', 'local_contentchecker'),
        ];
        // Only offered when at least one image source is actually configured,
        // so the mode cannot be selected and then fail with an empty picker.
        if (\local_contentchecker\image\source_registry::available()) {
            $kinds['image'] = get_string('enrich:kind:image', 'local_contentchecker');
        }

        $mform->addElement('select', 'kind',
            get_string('enrich:kind', 'local_contentchecker'), $kinds);

        // --- Registered asset ---------------------------------------------
        $assets = [];
        foreach (enrichment::assets() as $asset) {
            $assets[$asset->id] = $asset->name . ' (' .
                get_string('asset:type:' . $asset->assettype, 'local_contentchecker') . ')';
        }

        if ($assets) {
            $mform->addElement('select', 'assetid',
                get_string('enrich:asset', 'local_contentchecker'), $assets);
        } else {
            $mform->addElement('static', 'noassets',
                get_string('enrich:asset', 'local_contentchecker'),
                get_string('enrich:noassets', 'local_contentchecker'));
        }
        $mform->hideIf('assetid', 'kind', 'neq', 'asset');
        $mform->hideIf('noassets', 'kind', 'neq', 'asset');

        // --- Comparison table ---------------------------------------------
        $mform->addElement('text', 'tablecaption',
            get_string('enrich:tablecaption', 'local_contentchecker'), ['size' => 60]);
        $mform->setType('tablecaption', PARAM_TEXT);
        $mform->hideIf('tablecaption', 'kind', 'neq', 'table');

        $mform->addElement('textarea', 'tablecsv',
            get_string('enrich:tablecsv', 'local_contentchecker'),
            ['rows' => 8, 'cols' => 60]);
        $mform->setType('tablecsv', PARAM_RAW);
        $mform->addHelpButton('tablecsv', 'enrich:tablecsv', 'local_contentchecker');
        $mform->hideIf('tablecsv', 'kind', 'neq', 'table');

        // --- Image picker --------------------------------------------------
        // Rendered as static markup inside the form so it shares the single
        // target selector above; the insert itself goes through its own web
        // service when a result is clicked, not through this form's submit.
        if (\local_contentchecker\image\source_registry::available()) {
            $mform->addElement('static', 'imagepicker',
                get_string('enrich:imagesearch', 'local_contentchecker'),
                self::picker_markup());
            $mform->hideIf('imagepicker', 'kind', 'neq', 'image');
        }

        $this->add_action_buttons(true, get_string('enrich:insert', 'local_contentchecker'));
    }

    /**
     * Validate the submission.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Errors keyed by element name.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (($data['kind'] ?? '') === 'table') {
            $rows = self::parse_csv($data['tablecsv'] ?? '');
            if (count($rows) < 2) {
                // A header row plus at least one data row, or there is nothing
                // to compare.
                $errors['tablecsv'] = get_string('enrich:tabletooshort', 'local_contentchecker');
            } else {
                $width = count($rows[0]);
                foreach ($rows as $row) {
                    if (count($row) !== $width) {
                        $errors['tablecsv'] =
                            get_string('enrich:tableragged', 'local_contentchecker');
                        break;
                    }
                }
            }
        }

        if (($data['kind'] ?? '') === 'asset' && empty($data['assetid'])) {
            $errors['assetid'] = get_string('enrich:noassets', 'local_contentchecker');
        }

        if (($data['kind'] ?? '') === 'image') {
            // Images are inserted by clicking a search result, not by submitting
            // the form. Saying so beats silently doing nothing.
            $errors['kind'] = get_string('enrich:imageusepicker', 'local_contentchecker');
        }

        return $errors;
    }

    /**
     * Markup for the image picker.
     *
     * Static HTML only; all behaviour is attached by the AMD module, so the
     * form still renders sensibly if JavaScript is unavailable.
     *
     * @return string HTML.
     */
    protected static function picker_markup(): string {
        $sources = [];
        foreach (\local_contentchecker\image\source_registry::available() as $source) {
            $sources[$source->get_id()] = $source->get_name();
        }

        $out = \html_writer::start_div('cct-image-picker', ['data-cct-image-picker' => '1']);

        $out .= \html_writer::start_div('form-inline mb-2');
        $out .= \html_writer::select($sources, 'imagesource', key($sources), false, [
            'class' => 'custom-select mr-2',
            'data-cct-image-source' => '1',
            'aria-label' => get_string('image:source', 'local_contentchecker'),
        ]);
        $out .= \html_writer::empty_tag('input', [
            'type' => 'search',
            'class' => 'form-control mr-2',
            'data-cct-image-query' => '1',
            'placeholder' => get_string('image:queryplaceholder', 'local_contentchecker'),
            'aria-label' => get_string('image:query', 'local_contentchecker'),
        ]);
        $out .= \html_writer::tag('button', get_string('image:search', 'local_contentchecker'), [
            'type' => 'button',
            'class' => 'btn btn-secondary',
            'data-cct-image-search' => '1',
        ]);
        $out .= \html_writer::end_div();

        $out .= \html_writer::div('', 'cct-image-status', [
            'data-cct-image-status' => '1',
            'role' => 'status',
            'aria-live' => 'polite',
        ]);
        $out .= \html_writer::div('', 'cct-image-results', ['data-cct-image-results' => '1']);
        $out .= \html_writer::tag('p',
            get_string('image:attributionnote', 'local_contentchecker'),
            ['class' => 'text-muted small mt-2']);

        $out .= \html_writer::end_div();

        return $out;
    }

    /**
     * Split pasted CSV into rows of cells.
     *
     * @param string $csv The pasted text.
     * @return array List of row arrays.
     */
    public static function parse_csv(string $csv): array {
        $rows = [];
        foreach (preg_split('/\R/', trim($csv)) as $line) {
            if (trim($line) === '') {
                continue;
            }
            // The $escape argument is passed explicitly: PHP 8.4 deprecates
            // relying on the default, and '' is the RFC 4180 behaviour where a
            // backslash is a literal character rather than an escape. Drug
            // names and dosages are far more likely to contain a stray
            // backslash than to intend one as an escape.
            $rows[] = array_map('trim', str_getcsv($line, ',', '"', ''));
        }
        return $rows;
    }
}
