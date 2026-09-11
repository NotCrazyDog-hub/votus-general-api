<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProposalCommentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'author_name' => $this->author_name ?: 'Visitante',
            'content' => $this->content,
            'created_at' => $this->created_at,
            'can_delete' => (bool) ($this->can_delete ?? false),
        ];
    }
}
