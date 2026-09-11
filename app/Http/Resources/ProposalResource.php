<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProposalResource extends JsonResource
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
            'title' => $this->title,
            'content' => $this->content,
            'category' => $this->category,
            'author' => $this->author,
            'created_at' => $this->created_at,
            'votes' => [
                'legal' => (int) $this->legal_votes_count,
                'not_support' => (int) $this->not_support_votes_count,
            ],
            'viewer_vote' => $this->viewer_vote,
        ];
    }
}
