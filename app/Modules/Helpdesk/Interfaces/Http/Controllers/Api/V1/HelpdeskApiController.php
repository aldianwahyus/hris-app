<?php

declare(strict_types=1);

namespace App\Modules\Helpdesk\Interfaces\Http\Controllers\Api\V1;

use App\Models\User;
use App\Modules\Helpdesk\Application\ReplyTicket;
use App\Modules\Helpdesk\Application\SubmitTicket;
use App\Notifications\TicketReplied;
use App\Shared\Audit\Domain\AuditActor;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** ESS Mobile — cermin HelpdeskController, memakai Application-layer yang SAMA (SubmitTicket/ReplyTicket). */
final class HelpdeskApiController
{
    private const CATEGORIES = [
        'penggajian' => 'Penggajian',
        'absensi' => 'Absensi',
        'cuti_izin' => 'Cuti/Izin',
        'data_pegawai' => 'Data Pegawai',
        'akun_akses' => 'Akun/Akses Sistem',
        'lainnya' => 'Lainnya',
    ];

    private const PRIORITIES = ['rendah' => 'Rendah', 'sedang' => 'Sedang', 'tinggi' => 'Tinggi'];

    public function __construct(
        private readonly SubmitTicket $submit,
        private readonly ReplyTicket $reply,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $tickets = DB::table('hd_tickets')
            ->where('employee_id', $user->employee_id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        return response()->json(['data' => $tickets]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $validated = $request->validate([
            'category' => ['required', 'string', Rule::in(array_keys(self::CATEGORIES))],
            'subject' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:2000'],
            'priority' => ['required', 'string', Rule::in(array_keys(self::PRIORITIES))],
        ]);

        $id = $this->submit->handle(
            employeeId: $user->employee_id,
            category: $validated['category'],
            subject: $validated['subject'],
            description: $validated['description'],
            priority: $validated['priority'],
            actor: new AuditActor(
                actorId: $user->employee_id,
                actorRole: $user->getRoleNames()->implode(','),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ),
        );

        return response()->json(['id' => $id], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $ticket = DB::table('hd_tickets')->where('id', $id)->where('employee_id', $user->employee_id)->first();
        abort_if($ticket === null, 404);

        $replies = DB::table('hd_ticket_replies as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.author_employee_id')
            ->where('r.ticket_id', $id)
            ->where('r.is_internal_note', false)
            ->orderBy('r.created_at')
            ->select('r.message', 'r.created_at', 'r.author_employee_id', 'e.full_name as author_name')
            ->get();

        return response()->json(['data' => $ticket, 'replies' => $replies]);
    }

    public function reply(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $ticket = DB::table('hd_tickets')->where('id', $id)->where('employee_id', $user->employee_id)->first();
        abort_if($ticket === null, 404);

        $validated = $request->validate(['message' => ['required', 'string', 'max:2000']]);

        try {
            $this->reply->handle($id, $user->employee_id, $validated['message'], false);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $requesterName = DB::table('emp_employees')->where('id', $user->employee_id)->value('full_name') ?? 'Pegawai';

        /** @var object{id: string, employee_id: string, ticket_number: string, subject: string, assigned_to: ?string} $ticket */
        $this->notifyHc($ticket, $requesterName, $validated['message']);

        return response()->json(['message' => 'Balasan terkirim.']);
    }

    /** @param  object{id: string, employee_id: string, ticket_number: string, subject: string, assigned_to: ?string}  $ticket */
    private function notifyHc(object $ticket, string $requesterName, string $message): void
    {
        if ($ticket->assigned_to !== null) {
            $recipient = User::query()->where('employee_id', $ticket->assigned_to)->first();
            $recipient?->notify(new TicketReplied($ticket->ticket_number, $ticket->subject, $requesterName, $message));

            return;
        }

        $officeId = DB::table('emp_employees')->where('id', $ticket->employee_id)->value('office_id');
        $hrAdmins = User::query()
            ->whereHas('roles', fn ($q) => $q->where('name', 'hr_admin'))
            ->whereHas('employee', fn ($q) => $q->where('office_id', $officeId))
            ->get();

        foreach ($hrAdmins as $hrAdmin) {
            $hrAdmin->notify(new TicketReplied($ticket->ticket_number, $ticket->subject, $requesterName, $message));
        }
    }
}
