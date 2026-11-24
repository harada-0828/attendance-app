<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexAttendanceRecordRequest;
use App\Http\Requests\Api\V1\StoreAttendanceRecordRequest;
use App\Http\Requests\Api\V1\UpdateAttendanceRecordRequest;
use App\Http\Resources\AttendanceRecordResource;
use App\Models\AttendanceRecord;
use App\Models\Request as AttendanceRequest;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceRecordController extends Controller
{
    /**
     * 勤怠レコード一覧を取得する。
     */
    public function index(IndexAttendanceRecordRequest $request): AnonymousResourceCollection
    {
        $validated = $request->validated();

        $query = AttendanceRecord::query()
            ->with(['user', 'breaks', 'applications'])
            ->orderByDesc('date')
            ->orderByDesc('id');

        if (! empty($validated['user_id'])) {
            $query->where('user_id', (int) $validated['user_id']);
        }

        if (! empty($validated['date'])) {
            $query->whereDate('date', $validated['date']);
        }

        if (! empty($validated['month'])) {
            $parts = explode('-', (string) $validated['month']);
            if (count($parts) === 2) {
                [$year, $month] = $parts;
                $query->whereYear('date', (int) $year)
                    ->whereMonth('date', (int) $month);
            }
        }

        $perPage = (int) ($validated['per_page'] ?? 20);
        $records = $query->paginate($perPage);

        return AttendanceRecordResource::collection($records);
    }

    /**
     * 指定された勤怠レコードの詳細を取得する。
     */
    public function show(AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        $attendanceRecord->load(['user', 'breaks', 'applications']);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * 勤怠レコードを新規登録する。
     */
    public function store(StoreAttendanceRecordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $authUser = $request->user();

        $targetUserId = (! empty($validated['user_id']) && (bool) $authUser?->admin_status)
            ? (int) $validated['user_id']
            : (int) $authUser?->id;

        $date = (string) $validated['date'];
        $clockIn = ! empty($validated['clock_in'])
            ? "{$date} {$validated['clock_in']}"
            : null;
        $clockOut = ! empty($validated['clock_out'])
            ? "{$date} {$validated['clock_out']}"
            : null;

        /** @var AttendanceRecord $record */
        $record = DB::transaction(function () use ($validated, $targetUserId, $date, $clockIn, $clockOut, $authUser): AttendanceRecord {
            // attendances テーブルには comment カラムがないため、勤怠本体と修正申請（requests.reason）を分けて保存する
            $created = AttendanceRecord::create([
                'user_id' => $targetUserId,
                'date' => $date,
                'clock_in' => $clockIn,
                'clock_out' => $clockOut,
            ]);

            if (! empty($validated['comment'])) {
                AttendanceRequest::create([
                    'attendance_id' => $created->id,
                    'user_id' => $authUser?->id ?? $targetUserId,
                    'requested_clock_in' => $clockIn ?? "{$date} 00:00:00",
                    'requested_clock_out' => $clockOut ?? "{$date} 00:00:00",
                    'reason' => (string) $validated['comment'],
                    'approval_status' => true,
                ]);
            }

            return $created;
        });

        $record->load(['user', 'breaks']);

        return (new AttendanceRecordResource($record))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * 指定された勤怠レコードを更新する。
     *
     *
     * @throws ValidationException
     */
    public function update(UpdateAttendanceRecordRequest $request, AttendanceRecord $attendanceRecord): AttendanceRecordResource
    {
        $this->authorize('update', $attendanceRecord);

        $validated = $request->validated();
        $authUser = $request->user();

        $date = $validated['date'] ?? optional($attendanceRecord->date)->format('Y-m-d');

        // 比較用の出勤・退勤日時を組み立てる（部分更新時は既存値をフォールバックに使う）
        $effectiveClockInStr = array_key_exists('clock_in', $validated)
            ? ($validated['clock_in'] ? "{$date} {$validated['clock_in']}" : null)
            : optional($attendanceRecord->clock_in)->format('Y-m-d H:i:s');

        $effectiveClockOutStr = array_key_exists('clock_out', $validated)
            ? ($validated['clock_out'] ? "{$date} {$validated['clock_out']}" : null)
            : optional($attendanceRecord->clock_out)->format('Y-m-d H:i:s');

        if ($effectiveClockInStr && $effectiveClockOutStr) {
            $clockInCarbon = Carbon::parse($effectiveClockInStr);
            $clockOutCarbon = Carbon::parse($effectiveClockOutStr);

            if ($clockOutCarbon->lessThanOrEqualTo($clockInCarbon)) {
                throw ValidationException::withMessages([
                    'clock_out' => ['退勤時刻は出勤時刻より後の時刻を指定してください。'],
                ]);
            }
        }

        $updateData = [];

        if (array_key_exists('date', $validated)) {
            $updateData['date'] = $date;
        }

        if (array_key_exists('clock_in', $validated)) {
            $updateData['clock_in'] = $validated['clock_in']
                ? "{$date} {$validated['clock_in']}"
                : null;
        }

        if (array_key_exists('clock_out', $validated)) {
            $updateData['clock_out'] = $validated['clock_out']
                ? "{$date} {$validated['clock_out']}"
                : null;
        }

        if (! empty($validated['user_id']) && (bool) $authUser?->admin_status) {
            $updateData['user_id'] = (int) $validated['user_id'];
        }

        DB::transaction(function () use ($attendanceRecord, $updateData, $validated, $authUser, $date, $effectiveClockInStr, $effectiveClockOutStr): void {
            if (! empty($updateData)) {
                $attendanceRecord->update($updateData);
            }

            if (array_key_exists('comment', $validated) && ! empty($validated['comment'])) {
                AttendanceRequest::create([
                    'attendance_id' => $attendanceRecord->id,
                    'user_id' => $authUser?->id ?? $attendanceRecord->user_id,
                    'requested_clock_in' => $effectiveClockInStr ?? "{$date} 00:00:00",
                    'requested_clock_out' => $effectiveClockOutStr ?? "{$date} 00:00:00",
                    'reason' => (string) $validated['comment'],
                    'approval_status' => true,
                ]);
            }
        });

        $attendanceRecord->load(['user', 'breaks']);

        return new AttendanceRecordResource($attendanceRecord);
    }

    /**
     * 指定された勤怠レコードを削除する。
     */
    public function destroy(AttendanceRecord $attendanceRecord): Response
    {
        $this->authorize('delete', $attendanceRecord);

        $attendanceRecord->delete();

        return response()->noContent();
    }
}
