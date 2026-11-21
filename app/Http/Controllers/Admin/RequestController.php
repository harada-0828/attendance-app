<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Request as AttendanceRequest;
use App\Models\RequestRest;
use App\Models\Rest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RequestController extends Controller
{
    /**
     * 一般ユーザーの修正申請一覧画面を表示する（管理者は除外）。
     *
     * @return View
     */
    public function index(): View
    {
        $user = Auth::user();

        /** @var Collection<int, AttendanceRequest> $requests */
        $requests = AttendanceRequest::query()
            ->with(['attendance.user', 'user'])
            ->get();

        $applications = $requests
            ->filter(function (AttendanceRequest $req): bool {
                /** @var User|null $applicant */
                $applicant = $req->user ?? $req->attendance?->user;

                return !$applicant || !(bool) $applicant->admin_status;
            })
            ->map(function (AttendanceRequest $req): object {
                $item = new \stdClass();
                $item->id = $req->id;
                $item->approval_status = $req->approval_status ? '承認待ち' : '承認済み';
                $item->user = $req->user ?? $req->attendance?->user;
                $item->AttendanceRecord = $req->attendance;
                $item->comment = $req->reason;
                $item->application_date = $req->created_at;

                return $item;
            })
            ->values();

        return view('admin.admin-application-list', compact('applications', 'user'));
    }

    /**
     * 修正申請承認画面を表示する。
     *
     * @param  int|string  $attendance_correct_request_id
     * @return View
     */
    public function show(int|string $attendance_correct_request_id): View
    {
        $user = Auth::user();

        /** @var AttendanceRequest $requestData */
        $requestData = AttendanceRequest::query()
            ->with(['user', 'attendance.user', 'requestRests'])
            ->findOrFail($attendance_correct_request_id);

        $application = new \stdClass();
        $application->id = $requestData->id;
        $application->approval_status = $requestData->approval_status ? '承認待ち' : '承認済み';
        $application->user = $requestData->user ?? $requestData->attendance?->user;
        $application->AttendanceRecord = $requestData->attendance;

        $targetDate = $requestData->attendance?->date ?? $requestData->created_at;
        $application->new_date = Carbon::parse($targetDate);

        $application->new_clock_in = $requestData->requested_clock_in
            ? Carbon::parse($requestData->requested_clock_in)->format('H:i')
            : ($requestData->attendance?->clock_in ? Carbon::parse($requestData->attendance->clock_in)->format('H:i') : '');

        $application->new_clock_out = $requestData->requested_clock_out
            ? Carbon::parse($requestData->requested_clock_out)->format('H:i')
            : ($requestData->attendance?->clock_out ? Carbon::parse($requestData->attendance->clock_out)->format('H:i') : '');

        $application->proposalBreaks = $requestData->requestRests
            ->map(function (RequestRest $r): object {
                $breakItem = new \stdClass();
                $breakItem->break_in = $r->break_start;
                $breakItem->break_out = $r->break_end;

                return $breakItem;
            })
            ->values();

        $application->comment = $requestData->reason;

        return view('admin.admin-application-detail', compact('application', 'user'));
    }

    /**
     * 修正申請を承認し、勤怠および休憩データへ反映して前の画面へリダイレクトする。
     *
     * @param  int|string  $attendance_correct_request_id
     * @return RedirectResponse
     */
    public function update(int|string $attendance_correct_request_id): RedirectResponse
    {
        /** @var AttendanceRequest $attendanceRequest */
        $attendanceRequest = AttendanceRequest::query()
            ->with(['attendance.rests', 'requestRests'])
            ->findOrFail($attendance_correct_request_id);

        DB::transaction(function () use ($attendanceRequest): void {
            $attendance = $attendanceRequest->attendance;

            if ($attendance) {
                if ($attendanceRequest->requested_clock_in) {
                    $attendance->clock_in = $attendanceRequest->requested_clock_in;
                }
                if ($attendanceRequest->requested_clock_out) {
                    $attendance->clock_out = $attendanceRequest->requested_clock_out;
                }
                $attendance->save();

                $attendance->rests()->delete();

                $attendanceRequest->requestRests->each(function (RequestRest $reqRest) use ($attendance): void {
                    Rest::create([
                        'attendance_id' => $attendance->id,
                        'break_start' => $reqRest->break_start,
                        'break_end' => $reqRest->break_end,
                    ]);
                });
            }

            $attendanceRequest->update([
                'approval_status' => false,
            ]);
        });

        return redirect()
            ->back()
            ->with('success', '修正申請を承認し、勤怠および休憩データに反映しました。');
    }
}