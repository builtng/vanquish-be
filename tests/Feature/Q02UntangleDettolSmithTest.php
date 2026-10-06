<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\TrainingCounsellor;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\QcApplication;
use App\Console\Commands\CleanupQ02DettolSmith;

class Q02UntangleDettolSmithTest extends TestCase
{
    use RefreshDatabase;

    public function test_q02_untangle_dettol_smith_archives_qc002_and_deactivates_user()
    {
        // 1. Arrange pre-existing QC002 profile with Rooshan's data merged
        $tc = TrainingCounsellor::create([
            'tc_id' => 'QC002',
            'uuid' => 'qc002-test-uuid-1234',
            'name' => 'Dettol Smith',
            'legal_first_name' => 'Rooshan',
            'legal_last_name' => 'noyfb',
            'email' => 'cocopuffs27@icloud.com',
            'phone' => '4334434344',
            'counsellor_type' => 'Qualified',
            'status' => 'Active',
            'qualification_document' => 'documents/qual_doc.pdf',
            'dbs_certificate_qualified' => 'documents/dbs.pdf',
            'insurance_qualified' => 'documents/ins.pdf',
            'self_employment_proof' => 'documents/proof.pdf',
            'professional_membership' => 'documents/member.pdf',
        ]);

        $user = User::create([
            'name' => 'Dettol Smith',
            'email' => 'cocopuffs27@icloud.com',
            'password' => bcrypt('password123'),
            'role' => 'counsellor',
            'is_active' => true,
            'training_counsellor_id' => $tc->id,
        ]);

        $initialQcAppCount = QcApplication::count();

        // 2. Act: Run cleanup action
        $report = CleanupQ02DettolSmith::executeCleanup(false);

        // 3. Assert QC002 is archived and soft deleted via app code path
        $tcFresh = TrainingCounsellor::withTrashed()->find($tc->id);
        $this->assertNotNull($tcFresh->archived_at, "QC002 archived_at must be populated");
        $this->assertNotNull($tcFresh->deleted_at, "QC002 must be soft-deleted");

        // Assert no renaming or email modification occurred
        $this->assertEquals('Dettol Smith', $tcFresh->name);
        $this->assertEquals('cocopuffs27@icloud.com', $tcFresh->email);

        // Assert user is unlinked and deactivated
        $userFresh = User::find($user->id);
        $this->assertNull($userFresh->training_counsellor_id, "User must be unlinked from archived counsellor");
        $this->assertFalse((bool)$userFresh->is_active, "Counsellor portal login must be deactivated");

        // Assert exact activity log exists
        $log = ActivityLog::where('model_type', TrainingCounsellor::class)
            ->where('model_id', $tc->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($log, "Activity log must exist");
        $this->assertEquals("QC002 archived: test profile merged with a 24 Sep test application (Q02 clean-up).", $log->description);

        // Assert no recovered application was created
        $this->assertEquals($initialQcAppCount, QcApplication::count(), "No new qc_applications row should be created");

        // Assert document paths are reported in the output
        $this->assertArrayHasKey('documents', $report);
        $this->assertArrayHasKey('qualification_document', $report['documents']);
        $this->assertEquals('documents/qual_doc.pdf', $report['documents']['qualification_document']['stored_path']);
    }
}
