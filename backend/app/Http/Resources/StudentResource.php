<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Matches `Student` in student-app/src/types/index.ts */
class StudentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phone' => $this->phone ?? '',
            'name' => $this->name ?? '',
            'nickname' => $this->nickname ?? '',
            'email' => $this->email ?? '',
            'emailVerified' => $this->email_verified_at !== null,
            'targetExam' => $this->target_exam ?? '',
            'attemptYear' => $this->attempt_year ?? 0,
        ];
    }
}
