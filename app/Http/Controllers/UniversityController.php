<?php

namespace App\Http\Controllers;

use App\Http\Resources\UniversityResource;
use App\Models\University;

class UniversityController extends Controller
{
    public function show(University $university): UniversityResource
    {
        $university->load([
            'admissionMethods' => fn ($query) => $query
                ->where('active', true)
                ->orderBy('name'),

            'campuses' => fn ($query) => $query
                ->orderBy('state')
                ->orderBy('city'),

            'campuses.courseOfferings' => fn ($query) =>
                $query->orderBy('name'),
        ]);

        return new UniversityResource($university);
    }
}