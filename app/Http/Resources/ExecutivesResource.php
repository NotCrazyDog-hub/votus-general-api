<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExecutivesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'photo_url' => $this->photo_url,
            'external_id' => $this->external_id,
            'name' => $this->name,
            'display_name' => $this->display_name,
            'office' => $this->office,
            'level' => $this->level,
            'state' => $this->state,

            'party' => [
                'acronym' => $this->party_acronym,
                'name' => $this->party_name,
            ],

            'birth_date' => $this->birth_date?->format('Y-m-d'),
            'birth_place' => $this->birth_place,
            'occupation' => $this->occupation,
            'education' => $this->education,
            'biography' => $this->biography,

            'photo_url' => $this->photo_url,

            'started_at' => $this->started_at?->format('Y-m-d'),
            'ended_at' => $this->ended_at?->format('Y-m-d'),
            'is_current' => $this->is_current,

            'source' => [
                'name' => $this->source_name,
                'url' => $this->source_url,
            ],

            'actions' => $this->whenLoaded('actions', function () {
                return $this->actions->map(function ($action) {
                    return [
                        'id' => $action->id,
                        'title' => $action->title,
                        'summary' => $action->summary,
                        'action_type' => $action->action_type,
                        'occurred_at' => $action->occurred_at?->format('Y-m-d'),
                        'published_at' => $action->published_at?->toIso8601String(),
                        'location' => $action->location,

                        'source' => [
                            'name' => $action->source_name,
                            'type' => $action->source_type,
                            'url' => $action->source_url,
                        ],

                        'entities' => $action->entities,
                        'relevance_score' => $action->relevance_score !== null
                            ? (float) $action->relevance_score
                            : null,

                        'analysis_status' => $action->analysis_status,
                        'analyzed_at' => $action->analyzed_at?->toIso8601String(),
                    ];
                });
            }),
        ];
    }
}