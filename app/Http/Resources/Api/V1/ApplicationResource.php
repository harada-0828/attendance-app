<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    /**
     * リソースを配列へ変換する。
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'requested_clock_in' => optional($this->requested_clock_in)->format('H:i:s'),
            'requested_clock_out' => optional($this->requested_clock_out)->format('H:i:s'),
            'reason' => $this->reason,
            'approval_status' => (bool) $this->approval_status,
        ];
    }
}
