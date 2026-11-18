<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Carbon\Carbon;

class AdminUpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $formatTime = function ($time) {
            if (empty($time) || $time === 'null') return $time;
            try {
                return Carbon::parse($time)->format('H:i');
            } catch (\Exception $e) {
                return $time;
            }
        };

        $this->merge([
            'new_clock_in'  => $formatTime($this->input('new_clock_in')),
            'new_clock_out' => $formatTime($this->input('new_clock_out')),
        ]);

        if ($this->has('new_break_in')) {
            $breakIns = array_map($formatTime, $this->input('new_break_in', []));
            $this->merge(['new_break_in' => $breakIns]);
        }

        if ($this->has('new_break_out')) {
            $breakOuts = array_map($formatTime, $this->input('new_break_out', []));
            $this->merge(['new_break_out' => $breakOuts]);
        }
    }

    public function rules(): array
    {
        return [
            'new_clock_in'   => ['required', 'date_format:H:i'],
            'new_clock_out'  => ['required', 'date_format:H:i', 'after:new_clock_in'],
            
            'new_break_in.*' => [
                'nullable', 
                'date_format:H:i',
                function ($attribute, $value, $fail) {
                    if (empty($value)) return;
                    $clockIn = $this->input('new_clock_in');
                    $clockOut = $this->input('new_clock_out');

                    if ($clockIn && $value < $clockIn) {
                        $fail('休憩時間が不適切な値です');
                    }
                    if ($clockOut && $value >= $clockOut) {
                        $fail('休憩時間が不適切な値です');
                    }
                },
            ],
            
            'new_break_out.*'=> [
                'nullable', 
                'date_format:H:i',
                function ($attribute, $value, $fail) {
                    if (empty($value)) return;
                    
                    preg_match('/\.(\d+)$/', $attribute, $matches);
                    $index = $matches[1] ?? null;
                    $breakIn = $index !== null ? $this->input("new_break_in.{$index}") : null;
                    $clockOut = $this->input('new_clock_out');

                    if ($breakIn && $value <= $breakIn) {
                        $fail('休憩時間が不適切な値です');
                    }
                    if ($clockOut && $value > $clockOut) {
                        $fail('休憩時間もしくは退勤時間が不適切な値です');
                    }

                    // ▼ 【追加】複数の休憩行同士の時間重複（バッティング）をチェックするロジック
                    $breakIns = $this->input('new_break_in', []);
                    $breakOuts = $this->input('new_break_out', []);
                    
                    $intervals = [];
                    foreach ($breakIns as $i => $in) {
                        $out = $breakOuts[$i] ?? null;
                        // 有効なペアかつ両方揃っているものだけ対象にする
                        if (!empty($in) && $in !== 'null' && !empty($out) && $out !== 'null') {
                            $intervals[] = ['start' => $in, 'end' => $out];
                        }
                    }

                    // 休憩期間同士が重なっていないか総当たりでチェック
                    for ($i = 0; $i < count($intervals); $i++) {
                        for ($j = $i + 1; $j < count($intervals); $j++) {
                            $b1 = $intervals[$i];
                            $b2 = $intervals[$j];

                            // 重複判定（例: 休憩1が 12:00〜13:00 で 休憩2が 12:30〜13:30 の場合など）
                            if ($b1['start'] < $b2['end'] && $b2['start'] < $b1['end']) {
                                $fail('休憩時間が重複しています');
                                return;
                            }
                        }
                    }
                },
            ],
            
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
            
            'new_break_in.*.date_format'     => '休憩時間が不適切な値です',
            'new_break_out.*.date_format'    => '休憩時間もしくは退勤時間が不適切な値です',
            
            'comment.required'               => '備考を記入してください',
            'comment.max'                    => '備考は255文字以内で入力してください',
        ];
    }
}