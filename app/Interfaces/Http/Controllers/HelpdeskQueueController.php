<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Models\User;
use App\Modules\Access\Contracts\CurrentActor;
use App\Modules\Access\Domain\Role;
use App\Modules\Helpdesk\Application\ReplyTicket;
use App\Modules\Helpdesk\Application\UpdateTicketStatus;
use App\Notifications\TicketReplied;
use App\Shared\Audit\Domain\AuditActor;
use DateTimeImmutable;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Antrean HR Helpdesk — HC memproses tiket pegawai. hr_admin lingkup
 * kantornya sendiri, hr_approver seluruh bank (pola PERSIS
 * DocumentRequestQueueController).
 */
final class HelpdeskQueueController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly ReplyTicket $reply,
        private readonly UpdateTicketStatus $updateStatus,
    ) {}

    public function index(Request $request): View
    {
        $officeId = $this->actor->hasRole(Role::HrAdmin->value) ? $this->actor->officeId() : null;
        $statusFilter = $request->string('status')->toString();

        $tickets = DB::table('hd_tickets as t')
            ->join('emp_employees as e', 'e.id', '=', 't.employee_id')
            ->leftJoin('emp_employees as a', 'a.id', '=', 't.assigned_to')
            ->when($officeId !== null, fn ($q) => $q->where('e.office_id', $officeId))
            ->when($statusFilter !== '', fn ($q) => $q->where('t.status', $statusFilter))
            ->when($statusFilter === '', fn ($q) => $q->whereIn('t.status', ['terbuka', 'diproses']))
            ->select('t.id', 't.ticket_number', 't.subject', 't.category', 't.status', 't.priority', 't.created_at', 'e.full_name', 'e.nrp', 'a.full_name as assigned_name')
            ->orderByDesc('t.created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.helpdesk-queue', ['tickets' => $tickets, 'statusFilter' => $statusFilter]);
    }

    public function show(string $id): View
    {
        $officeId = $this->actor->hasRole(Role::HrAdmin->value) ? $this->actor->officeId() : null;

        $ticket = DB::table('hd_tickets as t')
            ->join('emp_employees as e', 'e.id', '=', 't.employee_id')
            ->when($officeId !== null, fn ($q) => $q->where('e.office_id', $officeId))
            ->where('t.id', $id)
            ->select('t.*', 'e.full_name', 'e.nrp')
            ->first();

        abort_if($ticket === null, 404);

        $replies = DB::table('hd_ticket_replies as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.author_employee_id')
            ->where('r.ticket_id', $id)
            ->orderBy('r.created_at')
            ->select('r.message', 'r.created_at', 'r.is_internal_note', 'e.full_name as author_name')
            ->get();

        $hcStaff = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['hr_admin', 'hr_approver']))
            ->whereHas('employee', fn ($q) => $q->when($officeId !== null, fn ($qq) => $qq->where('office_id', $officeId)))
            ->with('employee:id,full_name')
            ->get()
            ->pluck('employee')
            ->filter();

        return view('admin.helpdesk-show', ['ticket' => $ticket, 'replies' => $replies, 'hcStaff' => $hcStaff]);
    }

    public function reply(Request $request, string $id): RedirectResponse
    {
        $ticket = $this->scopedTicket($id);
        $actorEmployeeId = $this->actor->employeeId();
        abort_if($actorEmployeeId === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $validated = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'is_internal_note' => ['nullable', 'boolean'],
        ]);

        $isInternalNote = (bool) ($validated['is_internal_note'] ?? false);

        try {
            $this->reply->handle($id, $actorEmployeeId, $validated['message'], $isInternalNote);
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        if (! $isInternalNote) {
            $employeeUser = User::query()->where('employee_id', $ticket->employee_id)->first();
            $actorName = DB::table('emp_employees')->where('id', $actorEmployeeId)->value('full_name') ?? 'HC';
            $employeeUser?->notify(new TicketReplied($ticket->ticket_number, $ticket->subject, $actorName, $validated['message']));
        }

        return redirect()->route('admin.helpdesk-show', $id)->with('sukses', 'Balasan terkirim.');
    }

    public function assign(Request $request, string $id): RedirectResponse
    {
        $ticket = $this->scopedTicket($id);

        $validated = $request->validate(['assigned_to' => ['required', 'uuid', 'exists:emp_employees,id']]);

        DB::table('hd_tickets')->where('id', $id)->update([
            'assigned_to' => $validated['assigned_to'],
            'updated_at' => new DateTimeImmutable,
            'version' => $ticket->version + 1,
        ]);

        return redirect()->route('admin.helpdesk-show', $id)->with('sukses', 'Tiket berhasil ditugaskan.');
    }

    public function updateStatus(Request $request, string $id): RedirectResponse
    {
        $this->scopedTicket($id);

        $validated = $request->validate(['status' => ['required', 'string', Rule::in(['terbuka', 'diproses', 'selesai', 'ditutup'])]]);

        try {
            $this->updateStatus->handle($id, $validated['status'], new AuditActor(
                actorId: $this->actor->employeeId(),
                actorRole: implode(',', $this->actor->roles()),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ));
        } catch (DomainException $e) {
            return back()->with('gagal', $e->getMessage());
        }

        return redirect()->route('admin.helpdesk-show', $id)->with('sukses', 'Status tiket diperbarui.');
    }

    public function pendingCount(): int
    {
        if (! $this->actor->hasPermission('helpdesk.manage')) {
            return 0;
        }

        $officeId = $this->actor->hasRole(Role::HrAdmin->value) ? $this->actor->officeId() : null;

        return DB::table('hd_tickets as t')
            ->join('emp_employees as e', 'e.id', '=', 't.employee_id')
            ->when($officeId !== null, fn ($q) => $q->where('e.office_id', $officeId))
            ->where('t.status', 'terbuka')
            ->count();
    }

    /** @return object{id: string, employee_id: string, ticket_number: string, subject: string, status: string, version: int} */
    private function scopedTicket(string $id): object
    {
        $officeId = $this->actor->hasRole(Role::HrAdmin->value) ? $this->actor->officeId() : null;

        $ticket = DB::table('hd_tickets as t')
            ->join('emp_employees as e', 'e.id', '=', 't.employee_id')
            ->when($officeId !== null, fn ($q) => $q->where('e.office_id', $officeId))
            ->where('t.id', $id)
            ->select('t.*')
            ->first();

        abort_if($ticket === null, 404);

        /** @var object{id: string, employee_id: string, ticket_number: string, subject: string, status: string, version: int} $ticket */
        return $ticket;
    }
}
