<?php

declare(strict_types=1);

namespace Tests\Feature\Employee;

use App\Models\User;
use App\Modules\Employee\Application\SubmitNewEmployeeRequest;
use App\Shared\Audit\Domain\AuditActor;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Onboarding Terstruktur — modul baru (evaluasi PM/client 2026-09-02).
 * GenerateOnboardingChecklist dipicu dari
 * EmployeeApprovalQueueController::approveNewEmployee() PERSIS pola
 * triggerBekalCutiIfFirstThisYear pada Cuti — lihat catatan kelas di
 * GenerateOnboardingChecklist.
 */
final class OnboardingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_hr_admin_dapat_membuat_template_checklist(): void
    {
        $hrAdmin = $this->userWithNrp('2021.05.0302');

        $response = $this->actingAs($hrAdmin)->post('/persetujuan/onboarding/template/buat', [
            'name' => 'Onboarding Pegawai Tetap',
            'employment_status_scope' => 'tetap',
            'items' => [
                ['item_name' => 'Aktivasi email kantor', 'category' => 'it'],
                ['item_name' => 'Perkenalan tim', 'category' => 'hc'],
            ],
        ]);

        $templateId = DB::table('onb_checklist_templates')->where('name', 'Onboarding Pegawai Tetap')->value('id');
        $this->assertNotNull($templateId);
        $response->assertRedirect(route('admin.onboarding-template-index'));
        $this->assertSame(2, DB::table('onb_checklist_template_items')->where('template_id', $templateId)->count());
    }

    public function test_menyetujui_pegawai_baru_membangkitkan_checklist_dari_template_yang_cocok(): void
    {
        $this->createTemplate('Template Tetap', 'tetap', ['Aktivasi email', 'Serah terima kartu akses']);
        $this->createTemplate('Template Outsource', 'outsource', ['Briefing K3']);

        $employeeId = $this->approveNewEmployeeRequest('tetap');

        $checklist = DB::table('onb_employee_checklists')->where('employee_id', $employeeId)->first();
        $this->assertNotNull($checklist);
        $this->assertSame(2, DB::table('onb_employee_checklist_items')->where('checklist_id', $checklist->id)->count());
    }

    public function test_menyetujui_pegawai_baru_tanpa_template_yang_cocok_tidak_membuat_checklist(): void
    {
        $employeeId = $this->approveNewEmployeeRequest('kontrak');

        $this->assertSame(0, DB::table('onb_employee_checklists')->where('employee_id', $employeeId)->count());
    }

    public function test_template_catch_all_dipakai_jika_tidak_ada_yang_spesifik(): void
    {
        $this->createTemplate('Template Umum', null, ['Orientasi umum']);

        $employeeId = $this->approveNewEmployeeRequest('outsource');

        $checklist = DB::table('onb_employee_checklists')->where('employee_id', $employeeId)->first();
        $this->assertNotNull($checklist);
        $this->assertSame(1, DB::table('onb_employee_checklist_items')->where('checklist_id', $checklist->id)->count());
    }

    public function test_hc_dapat_menandai_item_selesai_dan_checklist_lengkap_saat_semua_selesai(): void
    {
        $this->createTemplate('Template Tetap', 'tetap', ['Item Satu', 'Item Dua']);
        $employeeId = $this->approveNewEmployeeRequest('tetap');
        $checklistId = DB::table('onb_employee_checklists')->where('employee_id', $employeeId)->value('id');
        $itemIds = DB::table('onb_employee_checklist_items')->where('checklist_id', $checklistId)->pluck('id');

        $hrApprover = $this->userWithNrp('2014.02.0061');

        $this->actingAs($hrApprover)->post("/persetujuan/onboarding/{$checklistId}/item/{$itemIds[0]}", ['is_done' => '1']);
        $this->assertNull(DB::table('onb_employee_checklists')->where('id', $checklistId)->value('completed_at'));

        $this->actingAs($hrApprover)->post("/persetujuan/onboarding/{$checklistId}/item/{$itemIds[1]}", ['is_done' => '1']);
        $this->assertNotNull(DB::table('onb_employee_checklists')->where('id', $checklistId)->value('completed_at'));
    }

    public function test_membatalkan_centang_item_mengosongkan_completed_at(): void
    {
        $this->createTemplate('Template Tetap', 'tetap', ['Item Satu']);
        $employeeId = $this->approveNewEmployeeRequest('tetap');
        $checklistId = DB::table('onb_employee_checklists')->where('employee_id', $employeeId)->value('id');
        $itemId = DB::table('onb_employee_checklist_items')->where('checklist_id', $checklistId)->value('id');
        $hrApprover = $this->userWithNrp('2014.02.0061');

        $this->actingAs($hrApprover)->post("/persetujuan/onboarding/{$checklistId}/item/{$itemId}", ['is_done' => '1']);
        $this->assertNotNull(DB::table('onb_employee_checklists')->where('id', $checklistId)->value('completed_at'));

        $this->actingAs($hrApprover)->post("/persetujuan/onboarding/{$checklistId}/item/{$itemId}", ['is_done' => '0']);
        $this->assertNull(DB::table('onb_employee_checklists')->where('id', $checklistId)->value('completed_at'));
    }

    public function test_toggle_template_aktif_nonaktif(): void
    {
        $templateId = $this->createTemplate('Template Uji Toggle', 'tetap', ['Item']);
        $hrAdmin = $this->userWithNrp('2021.05.0302');

        $this->actingAs($hrAdmin)->post("/persetujuan/onboarding/template/{$templateId}/toggle");
        $this->assertFalse((bool) DB::table('onb_checklist_templates')->where('id', $templateId)->value('is_active'));

        $this->actingAs($hrAdmin)->post("/persetujuan/onboarding/template/{$templateId}/toggle");
        $this->assertTrue((bool) DB::table('onb_checklist_templates')->where('id', $templateId)->value('is_active'));
    }

    private function createTemplate(string $name, ?string $employmentStatusScope, array $itemNames): string
    {
        $hrApprover = $this->userWithNrp('2014.02.0061');

        $this->actingAs($hrApprover)->post('/persetujuan/onboarding/template/buat', [
            'name' => $name,
            'employment_status_scope' => $employmentStatusScope,
            'items' => array_map(fn (string $n) => ['item_name' => $n, 'category' => 'hc'], $itemNames),
        ]);

        return DB::table('onb_checklist_templates')->where('name', $name)->value('id');
    }

    private function approveNewEmployeeRequest(string $employmentStatus): string
    {
        static $counter = 0;
        $counter++;
        $nrp = '2099.01.'.str_pad((string) $counter, 4, '0', STR_PAD_LEFT);

        $sysAdminId = $this->employeeId('SYSADMIN');

        $requestId = app(SubmitNewEmployeeRequest::class)->handle(
            proposedData: [
                'nrp' => $nrp,
                'full_name' => 'Pegawai Uji Onboarding '.$counter,
                'join_date' => '2026-01-01',
                'employment_status' => $employmentStatus,
                'office_id' => DB::table('md_offices')->where('code', 'KC-MTR')->value('id'),
                'position_id' => DB::table('md_positions')->where('code', 'OFC')->value('id'),
            ],
            requestedBy: $sysAdminId,
            actor: new AuditActor(actorId: $sysAdminId, actorRole: 'system_admin'),
        );

        $hrApprover = $this->userWithNrp('2014.02.0061');
        $this->actingAs($hrApprover)->post("/persetujuan/pegawai-baru/{$requestId}/setujui");

        return DB::table('emp_new_employee_requests')->where('id', $requestId)->value('created_employee_id');
    }

    private function employeeId(string $nrp): string
    {
        return DB::table('emp_employees')->where('nrp', $nrp)->value('id');
    }

    private function userWithNrp(string $nrp): User
    {
        return User::query()->where('employee_id', $this->employeeId($nrp))->firstOrFail();
    }
}
