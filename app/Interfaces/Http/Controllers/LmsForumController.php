<?php

declare(strict_types=1);

namespace App\Interfaces\Http\Controllers;

use App\Core\Domain\Uuid7;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Social & Collaborative Learning (BRD §5.9) — forum diskusi, ESS,
 * TANPA middleware permission (semua pegawai boleh membuat thread dan
 * membalas, pola sama LmsEnrollmentController). Moderasi (hapus) lewat
 * ForumModerationController (HC).
 */
final class LmsForumController extends Controller
{
    public function index(Request $request): View
    {
        $courseId = $request->query('course_id');

        $threads = DB::table('lms_forum_threads as t')
            ->join('emp_employees as e', 'e.id', '=', 't.employee_id')
            ->leftJoin('lms_courses as c', 'c.id', '=', 't.course_id')
            ->when($courseId, fn ($q) => $q->where('t.course_id', $courseId))
            ->select('t.id', 't.title', 't.is_pinned', 't.created_at', 'e.full_name', 'c.title as course_title')
            ->orderByDesc('t.is_pinned')
            ->orderByDesc('t.created_at')
            ->get()
            ->map(function ($thread) {
                $thread->reply_count = DB::table('lms_forum_replies')->where('thread_id', $thread->id)->count();

                return $thread;
            });

        $courses = DB::table('lms_courses')->whereNull('deleted_at')->orderBy('title')->get(['id', 'title']);

        return view('lms.forum-index', compact('threads', 'courses', 'courseId'));
    }

    public function create(): View
    {
        $courses = DB::table('lms_courses')->whereNull('deleted_at')->orderBy('title')->get(['id', 'title']);

        return view('lms.forum-create', compact('courses'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $validated = $request->validate([
            'course_id' => ['nullable', 'uuid', 'exists:lms_courses,id'],
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
        ]);

        $id = (string) Uuid7::generate();
        $now = new DateTimeImmutable;

        DB::table('lms_forum_threads')->insert([
            'id' => $id,
            'course_id' => $validated['course_id'] ?? null,
            'employee_id' => $user->employee_id,
            'title' => $validated['title'],
            'body' => $validated['body'],
            'is_pinned' => false,
            'created_at' => $now,
            'updated_at' => $now,
            'version' => 1,
        ]);

        return redirect()->route('lms.forum.show', $id)->with('sukses', 'Diskusi dibuat.');
    }

    public function show(string $id): View
    {
        $thread = DB::table('lms_forum_threads as t')
            ->join('emp_employees as e', 'e.id', '=', 't.employee_id')
            ->leftJoin('lms_courses as c', 'c.id', '=', 't.course_id')
            ->where('t.id', $id)
            ->select('t.*', 'e.full_name', 'c.title as course_title')
            ->first();

        abort_if($thread === null, 404);

        $replies = DB::table('lms_forum_replies as r')
            ->join('emp_employees as e', 'e.id', '=', 'r.employee_id')
            ->where('r.thread_id', $id)
            ->select('r.*', 'e.full_name')
            ->orderBy('r.created_at')
            ->get();

        return view('lms.forum-show', compact('thread', 'replies'));
    }

    public function storeReply(Request $request, string $threadId): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null || $user->employee_id === null, 403, 'Akun ini belum ditautkan ke data pegawai.');

        $thread = DB::table('lms_forum_threads')->where('id', $threadId)->first();
        abort_if($thread === null, 404);

        $validated = $request->validate([
            'body' => ['required', 'string'],
        ]);

        $now = new DateTimeImmutable;

        DB::table('lms_forum_replies')->insert([
            'id' => (string) Uuid7::generate(),
            'thread_id' => $threadId,
            'employee_id' => $user->employee_id,
            'body' => $validated['body'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return redirect()->route('lms.forum.show', $threadId)->with('sukses', 'Balasan terkirim.');
    }
}
