<?php

namespace App\Domains\Education\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UniversityResource extends JsonResource
{
    /**
     * Converte University para a resposta JSON da API. Expõe os campos: mec_code, name, acronym,
     * administrative_category, academic_organization, sector, website, admission_methods, campuses. As
     * relações aparecem apenas quando o controlador/serviço já as carregou, evitando consultas
     * adicionais durante a serialização. @return array<string, mixed>
     */
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