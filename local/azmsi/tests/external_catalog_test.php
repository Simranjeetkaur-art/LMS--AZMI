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

namespace local_azmsi;

use local_azmsi\external\get_program_catalog;
use local_azmsi\local\program;

/**
 * Tests the catalog WS: typed return + capability enforcement (AGENT_03 AC2).
 *
 * @package    local_azmsi
 * @covers     \local_azmsi\external\get_program_catalog
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class external_catalog_test extends \externallib_advanced_testcase {
    /**
     * A user without local/azmsi:ws_catalog is rejected.
     */
    public function test_requires_capability(): void {
        $this->resetAfterTest();
        $user = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        get_program_catalog::execute();
    }

    /**
     * With the capability granted, the function returns the typed tree.
     */
    public function test_returns_typed_tree_with_capability(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        program::seed();

        // Grant ws_catalog to a fresh role at system context and switch to it.
        $user = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        $context = \context_system::instance();
        assign_capability('local/azmsi:ws_catalog', CAP_ALLOW, $roleid, $context->id, true);
        role_assign($roleid, $user->id, $context->id);
        $this->setUser($user);

        $result = get_program_catalog::execute();
        // Validate against the declared external structure (strict typing).
        $clean = \core_external\external_api::clean_returnvalue(
            get_program_catalog::execute_returns(),
            $result
        );
        $this->assertSame('eMD', $clean['program']);
        $this->assertCount(3, $clean['years']);
    }
}
