<?php

namespace App\Http\Controllers;

use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\University;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class UniversityController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'state' => [
                'nullable',
                'string',
                'size:2',
            ],

            'city_code' => [
                'nullable',
                'string',
                'size:7',
            ],

            'course' => [
                'nullable',
                'string',
                'max:255',
            ],

            'sector' => [
                'nullable',
                Rule::in([
                    'public',
                    'private',
                ]),
            ],

            'modality' => [
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        $baseQuery = CourseOffering::query()
            ->with([
                'campus.university.admissionMethods' =>
                    fn ($query) => $query
                        ->where('active', true)
                        ->orderBy('name'),
            ]);

        if (! empty($filters['state'])) {
            $baseQuery->whereHas(
                'campus',
                fn ($query) => $query->where(
                    'state',
                    strtoupper($filters['state'])
                )
            );
        }

        if (! empty($filters['city_code'])) {
            $baseQuery->whereHas(
                'campus',
                fn ($query) => $query->where(
                    'ibge_city_code',
                    $filters['city_code']
                )
            );
        }

        if (! empty($filters['course'])) {
            $baseQuery->where(
                'normalized_name',
                $filters['course']
            );
        }

        if (! empty($filters['modality'])) {
            $baseQuery->where(
                'modality',
                $filters['modality']
            );
        }

        /*
        * Se o usuário escolher um setor específico,
        * exibimos somente aquele setor.
        */
        if (! empty($filters['sector'])) {
            $baseQuery->whereHas(
                'campus.university',
                fn ($query) => $query->where(
                    'sector',
                    $filters['sector']
                )
            );
        }

        $publicOfferings = null;
        $privateOfferings = null;

        if (
            empty($filters['sector']) ||
            $filters['sector'] === 'public'
        ) {
            $publicOfferings = (clone $baseQuery)
                ->whereHas(
                    'campus.university',
                    fn ($query) => $query->where(
                        'sector',
                        'public'
                    )
                )
                ->orderBy('name')
                ->paginate(
                    12,
                    ['*'],
                    'public_page'
                )
                ->withQueryString();
        }

        if (
            empty($filters['sector']) ||
            $filters['sector'] === 'private'
        ) {
            $privateOfferings = (clone $baseQuery)
                ->whereHas(
                    'campus.university',
                    fn ($query) => $query->where(
                        'sector',
                        'private'
                    )
                )
                ->orderBy('name')
                ->paginate(
                    12,
                    ['*'],
                    'private_page'
                )
                ->withQueryString();
        }

        $states = Campus::query()
            ->select('state')
            ->distinct()
            ->orderBy('state')
            ->pluck('state');

        $municipalities = collect();
        $courses = collect();

        if (! empty($filters['state'])) {
            $municipalities = Campus::query()
                ->where(
                    'state',
                    strtoupper($filters['state'])
                )
                ->select([
                    'ibge_city_code',
                    'city',
                ])
                ->distinct()
                ->orderBy('city')
                ->get();
        }

        if (
            ! empty($filters['state']) &&
            ! empty($filters['city_code'])
        ) {
            $courses = CourseOffering::query()
                ->whereHas(
                    'campus',
                    function ($query) use ($filters) {
                        $query
                            ->where(
                                'state',
                                strtoupper($filters['state'])
                            )
                            ->where(
                                'ibge_city_code',
                                $filters['city_code']
                            );
                    }
                )
                ->select([
                    'name',
                    'normalized_name',
                ])
                ->distinct()
                ->orderBy('name')
                ->get();
        }

        $modalities = CourseOffering::query()
            ->whereNotNull('modality')
            ->select('modality')
            ->distinct()
            ->orderBy('modality')
            ->pluck('modality');

        return view('universities.index', [
            'states' => $states,
            'municipalities' => $municipalities,
            'courses' => $courses,
            'modalities' => $modalities,
            'publicOfferings' => $publicOfferings,
            'privateOfferings' => $privateOfferings,
            'filters' => $filters,
        ]);
    }

    public function show(
        University $university
    ): View {
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

        return view('universities.show', [
            'university' => $university,
        ]);
    }

    private function normalize(
        string $value
    ): string {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->squish()
            ->toString();
    }

    public function municipalities(
        Request $request
    ): JsonResponse {
        $data = $request->validate([
            'state' => [
                'required',
                'string',
                'size:2',
            ],
        ]);

        $municipalities = Campus::query()
            ->where('state', strtoupper($data['state']))
            ->select([
                'ibge_city_code',
                'city',
            ])
            ->distinct()
            ->orderBy('city')
            ->get();

        return response()->json($municipalities);
    }

    public function courses(
        Request $request
    ): JsonResponse {
        $data = $request->validate([
            'state' => [
                'required',
                'string',
                'size:2',
            ],

            'city_code' => [
                'required',
                'string',
                'size:7',
            ],
        ]);

        $courses = CourseOffering::query()
            ->whereHas('campus', function ($query) use ($data) {
                $query
                    ->where('state', strtoupper($data['state']))
                    ->where(
                        'ibge_city_code',
                        $data['city_code']
                    );
            })
            ->select([
                'name',
                'normalized_name',
            ])
            ->distinct()
            ->orderBy('name')
            ->get();

        return response()->json($courses);
    }
}