<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceBreakResource extends JsonResource
{
    /**
     * リソースを配列へ変換する。
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'break_in' => optional($this->break_start)->format('H:i:s'),
            'break_out' => optional($this->break_end)->format('H:i:s'),
        ];
    }
}