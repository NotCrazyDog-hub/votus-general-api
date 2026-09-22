<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CampusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'city' => $this->city,
            'ibge_city_code' => $this->ibge_city_code,
            'state' => $this->state,
            'region' => $this->region,
            'address' => $this->address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,

            'university' => new UniversityResource(
                $this->whenLoaded('university')
            ),

            'course_offerings' => CourseOfferingResource::collection(
                $this->whenLoaded('courseOfferings')
            ),
        ];
    }
}