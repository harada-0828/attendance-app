<?php

namespace App\Http\Requests\Api\V1;

use App\Models\AttendanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAttendanceRecordRequest extends FormRequest
{
    /**
     * リクエストの認可を判定する。
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * バリデーションルールを定義する。
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var AttendanceRecord|null $record */
        $record = $this->route('attendanceRecord');
        $recordId = $record?->id;

        $targetUserId = $this->filled('user_id') && (bool) $this->user()?->admin_status
            ? (int) $this->input('user_id')
            : (int) ($record?->user_id ?? $this->user()?->id);

        $dateRule = Rule::unique((new AttendanceRecord)->getTable(), 'date')
            ->where(fn ($query) => $query->where('user_id', $targetUserId));

        if ($recordId) {
            $dateRule = $dateRule->ignore($recordId);
        }

        return [
            'user_id' => ['sometimes', 'integer', 'exists:users,id'],
            'date' => ['sometimes', 'required', 'date_format:Y-m-d', $dateRule],
            'clock_in' => ['sometimes', 'required', 'date_format:H:i:s'],
            'clock_out' => ['sometimes', 'nullable', 'date_format:H:i:s'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * 日本語バリデーションメッセージを返す。
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.required' => '勤怠日は必須です。',
            'date.date_format' => '勤怠日は YYYY-MM-DD 形式で指定してください。',
            'date.unique' => 'この日付の勤怠は既に登録されています。',
            'clock_in.required' => '出勤時刻は必須です。',
            'clock_in.date_format' => '出勤時刻は HH:MM:SS 形式で指定してください。',
            'clock_out.date_format' => '退勤時刻は HH:MM:SS 形式で指定してください。',
            'comment.max' => '備考は 255 文字以内で入力してください。',
        ];
    }
}
