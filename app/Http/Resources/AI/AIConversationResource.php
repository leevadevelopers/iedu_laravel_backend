<?php

namespace App\Http\Resources\AI;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AIConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'subject' => $this->subject,
            'question' => $this->question,
            'answer' => $this->answer,
            'explanation' => $this->explanation,
            'examples' => $this->examples,
            'practice_problems' => $this->practice_problems,
            'audio_url' => $this->audio_url,
            'image_url' => $this->image_url,
            'context' => $this->context,
            'tokens_used' => $this->tokens_used,
            'cost' => $this->cost ? (float) $this->cost : null,
            'status' => $this->status,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}

