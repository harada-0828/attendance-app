<?php

namespace App\Policies;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AttendanceRecordPolicy
{
    use HandlesAuthorization;

    /**
     * 管理者ユーザーはすべての操作を許可する。
     */
    public function before(User $user, string $ability): ?bool
    {
        if ((bool) $user->admin_status) {
            return true;
        }

        return null;
    }

    /**
     * 指定された勤怠レコードの更新を許可するか判定する。
     */
    public function update(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return (int) $user->id === (int) $attendanceRecord->user_id;
    }

    /**
     * 指定された勤怠レコードの削除を許可するか判定する。
     */
    public function delete(User $user, AttendanceRecord $attendanceRecord): bool
    {
        return (int) $user->id === (int) $attendanceRecord->user_id;
    }
}
