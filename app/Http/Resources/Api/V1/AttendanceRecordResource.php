<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceRecordResource extends JsonResource
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
            'user_id' => $this->user_id,
            'user_name' => $this->when(!$this->relationLoaded('user'), fn () => $this->user?->name),
            'user' => new UserResource($this->whenLoaded('user')),'date' => optional($this->date)->format('Y-m-d'),
            'clock_in' => optional($this->clock_in)->format('H:i:s'),
            'clock_out' => optional($this->clock_out)->format('H:i:s'),
            'total_time' => $this->total_time,
            'total_break_time' => $this->total_break_time,
            'comment' => $this->comment,
            'breaks' => AttendanceBreakResource::collection($this->whenLoaded('breaks')),
            'applications' => ApplicationResource::collection($this->whenLoaded('applications')),
        ];
    }
}