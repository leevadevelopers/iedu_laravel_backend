<?php

namespace App\Http\Resources\Parent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParentDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'children' => $this->when(isset($this->children), $this->children),
            'alerts' => $this->when(isset($this->alerts), $this->alerts),
            'messages' => $this->when(isset($this->messages), $this->messages),
        ];
    }
}

