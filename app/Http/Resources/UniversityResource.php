<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UniversityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'mec_code' => $this->mec_code,
            'name' => $this->name,
            'acronym' => $this->acronym,
            'administrative_category' => $this->administrative_category,
            'academic_organization' => $this->academic_organization,
            'sector' => $this->sector,
            'website' => $this->website,

            'admission_methods' => AdmissionMethodResource::collection(
                $this->whenLoaded('admissionMethods')
            ),

            'campuses' => CampusResource::collection(
                $this->whenLoaded('campuses')
            ),
        ];
    }
}