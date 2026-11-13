<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'new_clock_in'   => ['required', 'date_format:H:i'],
            'new_clock_out'  => ['required', 'date_format:H:i', 'after:new_clock_in'],
            
            // 休憩開始：形式チェック ＋ 出勤〜退勤の範囲内にあるか
            'new_break_in.*' => ['nullable', 'date_format:H:i', 'after_or_equal:new_clock_in', 'before:new_clock_out'],
            
            // 休憩終了：形式チェック ＋ 開始時間より後であること
            'new_break_out.*'=> ['nullable', 'date_format:H:i', 'after:new_break_in.*', 'before_or_equal:new_clock_out'],
            
            'comment'        => ['required', 'string', 'max:255'],
        ];
    }

   public function messages(): array
    {
        return [
            'new_clock_in.required'          => '出勤時間を入力してください',
            'new_clock_in.date_format'       => '出勤時間は「時:分」の形式で入力してください',
            
            'new_clock_out.required'         => '退勤時間を入力してください',
            'new_clock_out.date_format'      => '退勤時間は「時:分」の形式で入力してください',
            'new_clock_out.after'            => '出勤時間もしくは退勤時間が不適切な値です',
            
            // --- 休憩開始側のエラーメッセージ ---
            'new_break_in.*.date_format'     => '休憩時間が不適切な値です',
            'new_break_in.*.after_or_equal'  => '休憩時間が不適切な値です',
            'new_break_in.*.before'          => '休憩時間が不適切な値です',
            
            // --- 休憩終了側のエラーメッセージ ---
            'new_break_out.*.date_format'    => '',
            'new_break_out.*.after'          => '',
            'new_break_out.*.before_or_equal'=> '出勤時間もしくは退勤時間が不適切な値です',
            
            'comment.required'               => '備考を記入してください',
            'comment.max'                    => '備考は255文字以内で入力してください',
        ];
    }
}