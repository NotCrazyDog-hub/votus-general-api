<?php

namespace App\Domains\Education\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Education\Http\Resources\UniversityResource;
use App\Domains\Education\Models\University;

class UniversityController extends Controller
{
    /** Carrega as relações públicas da universidade em ordem definida e retorna o recurso detalhado. */
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