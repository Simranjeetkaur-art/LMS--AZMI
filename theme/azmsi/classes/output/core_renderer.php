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

namespace theme_azmsi\output;

/**
 * AZMSI core renderer.
 *
 * Extends Moove's renderer so all Moove output overrides are preserved; adds
 * only the AZMSI-specific output methods consumed by the (additively overridden)
 * drawers template via the `{{{ output.* }}}` pattern.
 *
 * @package    theme_azmsi
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \theme_moove\output\core_renderer {
    /** @var string|null Memoised AZMSI left-nav HTML ('' = none for this viewer). */
    protected $azmsileftnavhtml = null;

    /**
     * Render the capability-gated AZMSI left navigation sidebar.
     *
     * Returns an empty string for guests / users with no menu, so the drawers
     * template can omit the whole left drawer. Memoised because the drawers
     * template asks for it more than once (presence flag + content).
     *
     * @return string HTML
     */
    public function azmsi_left_nav(): string {
        if ($this->azmsileftnavhtml !== null) {
            return $this->azmsileftnavhtml;
        }
        if (!isloggedin() || isguestuser()) {
            return $this->azmsileftnavhtml = '';
        }
        $nav = new left_nav();
        $data = $nav->export_for_template($this);
        $this->azmsileftnavhtml = empty($data['haslinks'])
            ? '' : $this->render_from_template('theme_azmsi/left_nav', $data);
        return $this->azmsileftnavhtml;
    }

    /**
     * Presence flag for the AZMSI left navigation, for template sections.
     *
     * @return string '1' when there is a left nav to show, '' otherwise
     */
    public function azmsi_has_left_nav(): string {
        return $this->azmsi_left_nav() === '' ? '' : '1';
    }

    /**
     * Render the capability-gated "Switch portal" sidebar component.
     *
     * Returns an empty string when the current user has no permitted portal
     * links, so the markup simply does not appear. Called from the drawers
     * template as {{{ output.azmsi_switch_portal }}}.
     *
     * @return string HTML
     */
    public function azmsi_switch_portal(): string {
        $portal = new switch_portal();
        $data = $portal->export_for_template($this);
        if (empty($data['haslinks'])) {
            return '';
        }
        return $this->render_from_template('theme_azmsi/switchportal', $data);
    }

    /**
     * Render the AZMSI role-aware site footer (replaces Moove's default footer).
     *
     * @return string HTML
     */
    public function azmsi_site_footer(): string {
        global $USER;

        $userid = (isloggedin() && !isguestuser()) ? (int) $USER->id : 0;
        $data = portal_chrome::footer_for_user($userid);

        $standardfooter = $this->standard_footer_html();
        // Keep privacy links only — drop Moodle mobile-app promo from the legal strip.
        $standardfooter = preg_replace('~<a[^>]*class="mobilelink"[^>]*>.*?</a>~s', '', $standardfooter);
        $standardfooter = preg_replace('~<div>\s*</div>~', '', $standardfooter);
        $standardfooter = trim($standardfooter);

        $debugfooter = trim($this->debug_footer_html());

        $data['standardfooterhtml'] = $standardfooter;
        $data['debugfooterhtml'] = $this->debug_footer_html();
        $data['standardendofbodyhtml'] = $this->standard_end_of_body_html();
        $data['hasstandardfooter'] = $standardfooter !== '';
        $data['hasdebug'] = $debugfooter !== '';
        $data['haslegal'] = $data['hasstandardfooter'];

        return $this->render_from_template('theme_azmsi/footer', $data);
    }
}
