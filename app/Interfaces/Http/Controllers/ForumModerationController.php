<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Modules\Access\Contracts\CurrentActor;
use App\Shared\Audit\Domain\AuditAction;
use App\Shared\Audit\Domain\AuditActor;
use App\Shared\Audit\Domain\AuditEntry;
use App\Shared\Audit\Domain\AuditRepository;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Moderasi forum (BRD §5.9) — HC (permission:lms-catalog.manage) bisa
 * menghapus thread/balasan tidak pantas. Posting/membalas sendiri
 * TERBUKA semua pegawai lewat LmsForumController (tanpa permission).
 */
final class ForumModerationController extends Controller
{
    public function __construct(
        private readonly CurrentActor $actor,
        private readonly AuditRepository $audit,
    ) {}

    public function destroyThread(Request $request, string $id): RedirectResponse
    {
        $thread = DB::table('lms_forum_threads')->where('id', $id)->first();
        abort_if($thread === null, 404);

        DB::table('lms_forum_threads')->where('id', $id)->delete();

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_forum_thread',
            auditableId: $id,
            action: AuditAction::Deleted,
            oldValues: ['title' => $thread->title],
        ));

        return redirect()->route('lms.forum.index')->with('sukses', 'Diskusi dihapus.');
    }

    public function destroyReply(Request $request, string $threadId, string $id): RedirectResponse
    {
        $reply = DB::table('lms_forum_replies')->where('id', $id)->where('thread_id', $threadId)->first();
        abort_if($reply === null, 404);

        DB::table('lms_forum_replies')->where('id', $id)->delete();

        $this->audit->append(new AuditEntry(
            occurredAt: new DateTimeImmutable,
            actor: $this->currentAuditActor($request),
            auditableType: 'lms_forum_reply',
            auditableId: $id,
            action: AuditAction::Deleted,
        ));

        return redirect()->route('lms.forum.show', $threadId)->with('sukses', 'Balasan dihapus.');
    }

    private function currentAuditActor(Request $request): AuditActor
    {
        return new AuditActor(
            actorId: $this->actor->employeeId(),
            actorRole: implode(',', $this->actor->roles()),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
