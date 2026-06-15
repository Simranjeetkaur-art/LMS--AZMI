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

use local_azmsi\privacy\provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\writer;
use core\context\system as context_system;

/**
 * Privacy provider tests (AGENT_03 AC4).
 *
 * @package    local_azmsi
 * @covers     \local_azmsi\privacy\provider
 * @copyright  2026 AZMSI
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Seed one research record (+ child rows) and one application for a user.
     *
     * @param int $userid
     * @return void
     */
    protected function seed_user_data(int $userid): void {
        global $DB;
        $researchid = $DB->insert_record('local_azmsi_research', (object) [
            'userid' => $userid, 'title' => 'My dissertation', 'track' => 'AI',
            'status' => 'active', 'progress' => 10, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('local_azmsi_research_milestone', (object) [
            'researchid' => $researchid, 'seq' => 0, 'title' => 'Proposal', 'status' => 'active',
            'timemodified' => time(),
        ]);
        $DB->insert_record('local_azmsi_research_doc', (object) [
            'researchid' => $researchid, 'filename' => 'Draft.pdf', 'type' => 'proposal',
            'status' => 'draft', 'timemodified' => time(),
        ]);
        $DB->insert_record('local_azmsi_application', (object) [
            'userid' => $userid, 'program' => 'eMD', 'stage' => 'applied', 'status' => 'open',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * The user's data is found, exported, and fully deleted.
     */
    public function test_export_and_delete_for_user(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->seed_user_data($user->id);
        $this->seed_user_data($other->id);

        // Contexts.
        $contextlist = provider::get_contexts_for_userid($user->id);
        $this->assertCount(1, $contextlist);

        // Export.
        $approved = new approved_contextlist($user, 'local_azmsi', [context_system::instance()->id]);
        provider::export_user_data($approved);
        $writer = writer::with_context(context_system::instance());
        $this->assertTrue($writer->has_any_data());

        // Delete this user only.
        provider::delete_data_for_user($approved);
        $this->assertFalse($DB->record_exists('local_azmsi_research', ['userid' => $user->id]));
        $this->assertFalse($DB->record_exists('local_azmsi_application', ['userid' => $user->id]));
        // The other user's data survives.
        $this->assertTrue($DB->record_exists('local_azmsi_research', ['userid' => $other->id]));
    }

    /**
     * Deleting all users in the system context wipes every AZMSI table.
     */
    public function test_delete_all_in_context(): void {
        global $DB;
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->seed_user_data($user->id);

        provider::delete_data_for_all_users_in_context(context_system::instance());
        $this->assertSame(0, $DB->count_records('local_azmsi_research'));
        $this->assertSame(0, $DB->count_records('local_azmsi_research_milestone'));
        $this->assertSame(0, $DB->count_records('local_azmsi_research_doc'));
        $this->assertSame(0, $DB->count_records('local_azmsi_application'));
    }
}
