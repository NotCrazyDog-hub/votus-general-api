<?php

namespace App\Http\Controllers;

use App\Models\PublicOpportunity;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicOpportunityController extends Controller
{
    /**
     * Lista as oportunidades publicadas.
     */
    public function index(
        Request $request
    ): View {

        $query = PublicOpportunity::query()
            ->where(
                'review_status',
                'approved'
            );


        /*
         * Pesquisa pelo título,
         * órgão ou município.
         */
        if ($request->filled('search')) {

            $search = trim(
                $request->input('search')
            );


            $query->where(
                function ($query) use ($search) {

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
                        );
                }
            );
        }


        /*
         * Concurso / processo seletivo etc.
         */
        if ($request->filled('type')) {

            $query->where(
                'type',
                $request->input('type')
            );
        }


        /*
         * Estado.
         */
        if ($request->filled('state')) {

            $query->where(
                'state',
                strtoupper(
                    $request->input('state')
                )
            );
        }


        /*
         * Município.
         */
        if ($request->filled('municipality')) {

            $query->where(
                'municipality',
                'ilike',
                '%' .
                trim(
                    $request->input('municipality')
                ) .
                '%'
            );
        }


        /*
         * Primeiro aparecem os concursos
         * cuja inscrição termina mais cedo.
         *
         * Datas nulas ficam depois.
         */
        $query->orderByRaw(
            'registration_end IS NULL'
        );

        $query->orderBy(
            'registration_end'
        );


        $opportunities = $query
            ->paginate(12)
            ->withQueryString();


        return view(
            'public_opportunities.index',
            compact('opportunities')
        );
    }


    /**
     * Página de uma oportunidade específica.
     */
    public function show(
        PublicOpportunity $opportunity
    ): View {

        /*
         * Impede alguém de acessar diretamente
         * uma oportunidade pendente/rejeitada
         * apenas alterando o ID na URL.
         */
        abort_unless(
            $opportunity->review_status
                === 'approved',
            404
        );


        /*
         * Carrega edital, retificações,
         * prorrogações etc.
         */
        $opportunity->load([
            'publications' => function ($query) {

                $query->orderByDesc(
                    'gazette_date'
                );
            }
        ]);


        return view(
            'public_opportunities.show',
            compact('opportunity')
        );
    }
}