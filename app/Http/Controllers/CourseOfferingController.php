<?php

namespace App\Http\Controllers;

use App\Http\Resources\CourseOfferingResource;
use App\Models\CourseOffering;
use App\Services\Opportunities\UniversityCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CourseOfferingController extends Controller
{
    public function __construct(
        private readonly UniversityCatalogService $catalog
    ) {
    }

    /**
     * Busca de cursos, filtrável por estado, cidade, nome
     * normalizado (curso), setor da universidade e modalidade.
     *
     * A tela "Buscar universidades" do front chama este método:
     * ela lista ofertas de curso, cada uma com sua universidade
     * e campus aninhados, não universidades soltas.
     */
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

    public function show(CourseOffering $courseOffering): CourseOfferingResource
    {
        $courseOffering->load([
            'campus.university.admissionMethods' =>
                fn ($query) => $query
                    ->where('active', true)
                    ->orderBy('name'),

            'curricula.subjects',
        ]);

        return new CourseOfferingResource($courseOffering);
    }

    /**
     * Popula o select de município — só habilitado depois
     * que o estado foi escolhido no front.
     */
    public function municipalities(Request $request): JsonResponse
    {
        $data = $request->validate([
            'state' => ['required', 'string', 'size:2'],
        ]);

        return response()->json(
            $this->catalog->municipalities($data['state'])
        );
    }

    /**
     * Popula o select de curso — só habilitado depois
     * que o município foi escolhido no front.
     */
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