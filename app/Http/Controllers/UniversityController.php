<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseOfferingResource;
use App\Http\Resources\UniversityResource;
use App\Models\University;
use App\Services\Opportunities\UniversityCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UniversityController extends Controller
{
    public function __construct(
        private readonly UniversityCatalogService $catalog
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'state' => ['nullable', 'string', 'size:2'],
            'city_code' => ['nullable', 'string', 'size:7'],
            'course' => ['nullable', 'string', 'max:255'],
            'sector' => ['nullable', Rule::in(['public', 'private'])],
            'modality' => ['nullable', 'string', 'max:100'],
        ]);

        $offerings = $this->catalog->filterOfferings($filters);

        return CourseOfferingResource::collection($offerings)
            ->additional([
                'filter_options' => [
                    'states' => $this->catalog->states(),
                    'modalities' => $this->catalog->modalities(),

                    'municipalities' => ! empty($filters['state'])
                        ? $this->catalog->municipalities($filters['state'])
                        : [],

                    'courses' => (! empty($filters['state']) && ! empty($filters['city_code']))
                        ? $this->catalog->courses($filters['state'], $filters['city_code'])
                        : [],
                ],
            ])
            ->response();
    }

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

    public function municipalities(Request $request): JsonResponse
    {
        $data = $request->validate([
            'state' => ['required', 'string', 'size:2'],
        ]);

        return response()->json(
            $this->catalog->municipalities($data['state'])
        );
    }

    public function courses(Request $request): JsonResponse
    {
        $data = $request->validate([
            'state' => ['required', 'string', 'size:2'],
            'city_code' => ['required', 'string', 'size:7'],
        ]);

        return response()->json(
            $this->catalog->courses($data['state'], $data['city_code'])
        );
    }
}