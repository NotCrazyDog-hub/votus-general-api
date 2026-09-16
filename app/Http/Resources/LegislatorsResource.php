<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LegislatorsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'external_id' => $this->external_id,
            'chamber' => $this->chamber,
            'parliamentary_name' => $this->parliamentary_name,
            'photo_url' => $this->photo_url,
            'party' => $this->party,
            'state' => $this->state,
            'electoral_status' => $this->electoral_status,
            'status' => $this->status,
            'metrics' => [
                'effectiveness' => [
                    'rate' => $this->effectiveness_rate !== null ? (float) $this->effectiveness_rate : null,
                    'wilson_lower' => $this->effectiveness_wilson_lower !== null ? (float) $this->effectiveness_wilson_lower : null,
                    'total_bills' => $this->effectiveness_total_bills,
                    'advanced_bills' => $this->effectiveness_advanced_bills,
                    'calculated_at' => $this->effectiveness_calculated_at,
                ],
                'productivity' => [
                    'bills_per_year' => $this->productivity_bills_per_year !== null ? (float) $this->productivity_bills_per_year : null,
                ],
                'thematic_focus' => [
                    'index' => $this->thematic_focus_index !== null ? (float) $this->thematic_focus_index : null,
                    'total_bills' => $this->thematic_focus_total_bills,
                    'top_topic' => $this->whenLoaded('thematicFocusTopTopic', fn () => [
                        'id' => $this->thematicFocusTopTopic->id,
                        'name' => $this->thematicFocusTopTopic->name,
                        'share' => (float) $this->thematic_focus_top_topic_share,
                    ]),
                ],
            ],
        ];
    }
}
