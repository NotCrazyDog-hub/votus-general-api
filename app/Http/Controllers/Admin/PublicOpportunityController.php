<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PublicOpportunity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicOpportunityController extends Controller
{
    /**
     * Lista as oportunidades para revisão.
     */
    public function index(Request $request): View
    {
        $query = PublicOpportunity::query()
            ->withCount('publications')
            ->orderByDesc('created_at');


        // Filtrar por situação da revisão.
        if ($request->filled('status')) {
            $query->where(
                'review_status',
                $request->input('status')
            );
        }


        // Campo de pesquisa.
        if ($request->filled('search')) {

            $search = trim(
                $request->input('search')
            );

            $query->where(function ($query) use ($search) {

                $query
                    ->where(
                        'title',
                        'ilike',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'agency',
                        'ilike',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'municipality',
                        'ilike',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'notice_number',
                        'ilike',
                        "%{$search}%"
                    );
            });
        }


        $opportunities = $query
            ->paginate(15)
            ->withQueryString();


        return view(
            'admin.public_opportunities.index',
            compact('opportunities')
        );
    }


    /**
     * Página de revisão/edição.
     */
    public function edit(
        PublicOpportunity $opportunity
    ): View {

        $opportunity->load([
            'publications' => function ($query) {
                $query->orderByDesc('gazette_date');
            }
        ]);


        return view(
            'admin.public_opportunities.edit',
            compact('opportunity')
        );
    }


    /**
     * Salva as alterações feitas manualmente.
     */
    public function update(
        Request $request,
        PublicOpportunity $opportunity
    ): RedirectResponse {

        $data = $request->validate([

            'type' => [
                'required',
                'string',
                'max:60',
            ],

            'title' => [
                'nullable',
                'string',
                'max:500',
            ],

            'notice_number' => [
                'nullable',
                'string',
                'max:100',
            ],

            'agency' => [
                'nullable',
                'string',
                'max:500',
            ],

            'municipality' => [
                'nullable',
                'string',
                'max:200',
            ],

            'state' => [
                'nullable',
                'string',
                'size:2',
            ],

            'positions_text' => [
                'nullable',
                'string',
            ],

            'education_levels_text' => [
                'nullable',
                'string',
            ],

            'vacancies' => [
                'nullable',
                'integer',
                'min:0',
            ],

            'salary_min' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'salary_max' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'registration_start' => [
                'nullable',
                'date',
            ],

            'registration_end' => [
                'nullable',
                'date',
                'after_or_equal:registration_start',
            ],

            'exam_date' => [
                'nullable',
                'date',
            ],

            'fee_min' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'fee_max' => [
                'nullable',
                'numeric',
                'min:0',
            ],

            'registration_url' => [
                'nullable',
                'url',
            ],

            'summary' => [
                'nullable',
                'string',
            ],
        ]);


        /*
         * Converte:
         *
         * Professor
         * Enfermeiro
         * Técnico
         *
         * para:
         *
         * [
         *   "Professor",
         *   "Enfermeiro",
         *   "Técnico"
         * ]
         */
        $positions = $this->linesToArray(
            $data['positions_text'] ?? ''
        );

        $educationLevels = $this->linesToArray(
            $data['education_levels_text'] ?? ''
        );


        unset(
            $data['positions_text'],
            $data['education_levels_text']
        );


        $data['positions'] = $positions;

        $data['education_levels'] =
            $educationLevels;


        // Padroniza a UF.
        if (!empty($data['state'])) {
            $data['state'] = strtoupper(
                $data['state']
            );
        }


        $opportunity->update($data);


        return redirect()
            ->route(
                'admin.public-opportunities.edit',
                $opportunity
            )
            ->with(
                'success',
                'Oportunidade atualizada com sucesso.'
            );
    }


    /**
     * Aprova a oportunidade.
     *
     * Depois disso ela pode aparecer
     * na página pública.
     */
    public function approve(
        PublicOpportunity $opportunity
    ): RedirectResponse {

        $opportunity->update([
            'review_status' => 'approved',
        ]);


        return redirect()
            ->route(
                'admin.public-opportunities.index'
            )
            ->with(
                'success',
                'Oportunidade publicada com sucesso.'
            );
    }


    /**
     * Descarta uma oportunidade.
     *
     * Não apagamos do banco porque assim
     * o n8n não recria a mesma oportunidade.
     */
    public function reject(
        PublicOpportunity $opportunity
    ): RedirectResponse {

        $opportunity->update([
            'review_status' => 'rejected',
        ]);


        return redirect()
            ->route(
                'admin.public-opportunities.index'
            )
            ->with(
                'success',
                'Oportunidade descartada.'
            );
    }

    public function togglePublished(PublicOpportunity $opportunity)
    {
        $opportunity->update([
            'review_status' => $opportunity->review_status === 'approved'
                ? 'pending'
                : 'approved',
        ]);

        return back()->with(
            'success',
            $opportunity->review_status === 'approved'
                ? 'Oportunidade publicada com sucesso.'
                : 'Oportunidade despublicada com sucesso.'
        );
    }


    /**
     * Transforma texto separado por linhas
     * em array.
     */
    private function linesToArray(
        ?string $text
    ): array {

        if (!$text) {
            return [];
        }


        return collect(
            preg_split(
                '/\r\n|\r|\n/',
                $text
            )
        )
            ->map(
                fn ($value) => trim($value)
            )
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}