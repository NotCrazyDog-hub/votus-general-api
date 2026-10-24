<?php

namespace App\Domains\GovernmentPlans\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tema. O proposals_count só aparece quando o tema veio com withCount('proposals')
 * (listagem de temas); na visão geral ele é omitido.
 */
class GovernmentPlanThemeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'proposals_count' => $this->whenCounted('proposals'),
        ];
    }
}