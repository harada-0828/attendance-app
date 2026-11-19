<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAttendanceRequest;
use App\Models\Attendance;
use App\Models\Request as AttendanceRequest;
use App\Models\RequestRest;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RequestController extends Controller
{
    /**
     * ログインユーザーの勤怠修正履歴一覧画面を表示する。
     *
     * @return View
     */
    public function index(): View
    {
        $userId = Auth::id();
        $user = Auth::user();

        /** @var Collection<int, AttendanceRequest> $requests */
        $requests = AttendanceRequest::query()
            ->with('attendance')
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get();

        /** @var Collection<int, array<string, mixed>> $formattedApplications */
        $formattedApplications = $requests->map(function (AttendanceRequest $req): array {
            $dateObj = Carbon::parse($req->attendance?->date ?? now());

            return [
                'id' => $req->id,
                'attendance_id' => $req->attendance_id,
                'approval_status' => $req->approval_status ? '承認待ち' : '承認済み',
                'date' => $dateObj->format('Y/m/d'),
                'comment' => $req->reason,
                'application_date' => $req->created_at?->format('Y/m/d H:i') ?? '',
            ];
        });

        return view('user.user-application-list', compact('formattedApplications', 'user'));
    }

    /**
     * 勤怠詳細画面を表示する（承認待ち申請があれば申請内容を優先反映）。
     *
     * @param  int|string  $id
     * @return View
     */
    public function show(int|string $id): View
    {
        $userId = Auth::id();
        $user = Auth::user();

        /** @var Attendance $attendance */
        $attendance = Attendance::query()
            ->with(['user', 'rests'])
            ->where('user_id', $userId)
            ->findOrFail($id);

        $dateObj = Carbon::parse($attendance->date);

        /** @var AttendanceRequest|null $pendingRequest */
        $pendingRequest = AttendanceRequest::query()
            ->with('requestRests')
            ->where('attendance_id', $attendance->id)
            ->where('approval_status', true)
            ->latest('id')
            ->first();

        /** @var AttendanceRequest|null $latestRequest */
        $latestRequest = AttendanceRequest::query()
            ->where('attendance_id', $attendance->id)
            ->latest('id')
            ->first();

        $breaks = $attendance->rests
            ->map(fn ($rest): array => [
                'break_in' => $rest->break_start ? Carbon::parse($rest->break_start)->format('H:i') : '',
                'break_out' => $rest->break_end ? Carbon::parse($rest->break_end)->format('H:i') : '',
            ])
            ->values()
            ->all();

        $data = [
            'id' => $attendance->id,
            'year' => $dateObj->format('Y年'),
            'date' => $dateObj->format('n月j日'),
            'clock_in' => $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i') : '',
            'clock_out' => $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i') : '',
            'breaks' => $breaks,
            'comment' => $latestRequest?->reason ?? '',
            'application' => $pendingRequest,
        ];

        if ($pendingRequest) {
            $data['clock_in'] = $pendingRequest->requested_clock_in
                ? Carbon::parse($pendingRequest->requested_clock_in)->format('H:i')
                : $data['clock_in'];

            $data['clock_out'] = $pendingRequest->requested_clock_out
                ? Carbon::parse($pendingRequest->requested_clock_out)->format('H:i')
                : $data['clock_out'];

            $data['comment'] = $pendingRequest->reason ?? $data['comment'];

            if ($pendingRequest->requestRests->isNotEmpty()) {
                $data['breaks'] = $pendingRequest->requestRests
                    ->map(fn (RequestRest $r): array => [
                        'break_in' => $r->break_start ? Carbon::parse($r->break_start)->format('H:i') : '',
                        'break_out' => $r->break_end ? Carbon::parse($r->break_end)->format('H:i') : '',
                    ])
                    ->values()
                    ->all();
            }
        }

        return view('user.user-detail', compact('attendance', 'data', 'user'));
    }

    /**
     * 勤怠詳細の修正申請を登録し、詳細画面へリダイレクトする。
     *
     * @param  UpdateAttendanceRequest  $request
     * @param  int|string  $id
     * @return RedirectResponse
     */
    public function store(UpdateAttendanceRequest $request, int|string $id): RedirectResponse
    {
        $validated = $request->validated();
        $userId = Auth::id();

        /** @var Attendance $attendance */
        $attendance = Attendance::query()
            ->where('user_id', $userId)
            ->findOrFail($id);

        DB::transaction(function () use ($attendance, $validated, $userId): void {
            /** @var AttendanceRequest $attendanceRequest */
            $attendanceRequest = AttendanceRequest::create([
                'attendance_id' => $attendance->id,
                'user_id' => $userId,
                'requested_clock_in' => $validated['new_clock_in'],
                'requested_clock_out' => $validated['new_clock_out'],
                'reason' => $validated['comment'],
                'approval_status' => true,
            ]);

            $breakIns = collect($validated['new_break_in'] ?? []);
            $breakOuts = $validated['new_break_out'] ?? [];

            /** @var Collection<int, array{start: string, end: string}> $uniqueBreaks */
            $uniqueBreaks = $breakIns->reduce(
                function (Collection $carry, mixed $breakIn, int|string $index) use ($breakOuts): Collection {
                    $breakOut = $breakOuts[$index] ?? null;

                    if (
                        empty($breakIn) || $breakIn === 'null'
                        || empty($breakOut) || $breakOut === 'null'
                        || $breakIn === $breakOut
                    ) {
                        return $carry;
                    }

                    $newStart = (string) $breakIn;
                    $newEnd = (string) $breakOut;

                    $isDuplicate = $carry->contains(
                        fn (array $existing): bool => $newStart < $existing['end'] && $existing['start'] < $newEnd
                    );

                    if (!$isDuplicate) {
                        $carry->push([
                            'start' => $newStart,
                            'end' => $newEnd,
                        ]);
                    }

                    return $carry;
                },
                collect()
            );

            $uniqueBreaks->each(function (array $interval) use ($attendanceRequest): void {
                RequestRest::create([
                    'request_id' => $attendanceRequest->id,
                    'break_start' => $interval['start'],
                    'break_end' => $interval['end'],
                ]);
            });
        });

        return redirect()
            ->route('attendance.detail', $id)
            ->with('success', '修正申請を送信しました。');
    }
}