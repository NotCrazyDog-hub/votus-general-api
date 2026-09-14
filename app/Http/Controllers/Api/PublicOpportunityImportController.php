<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OpportunityPublication;
use App\Models\PublicOpportunity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicOpportunityImportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([

            'source_key' => [
                'required',
                'string',
                'max:100',
            ],

            'publication_key' => [
                'required',
                'string',
                'max:100',
            ],

            'type' => [
                'required',
                'string',
                'max:60',
            ],

            'publication_type' => [
                'nullable',
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

            'positions' => [
                'nullable',
                'array',
            ],

            'education_levels' => [
                'nullable',
                'array',
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
                'string',
            ],

            'summary' => [
                'nullable',
                'string',
            ],

            'territory_id' => [
                'nullable',
                'string',
            ],

            'gazette_date' => [
                'nullable',
                'date',
            ],

            'edition' => [
                'nullable',
                'string',
            ],

            'gazette_url' => [
                'nullable',
                'string',
            ],

            'txt_url' => [
                'nullable',
                'string',
            ],

            'source_excerpt' => [
                'nullable',
                'string',
            ],
        ]);


        return DB::transaction(function () use ($data) {

            /*
             * Procura a oportunidade.
             */
            $opportunity =
                PublicOpportunity::where(
                    'source_key',
                    $data['source_key']
                )->first();


            $isNewOpportunity =
                $opportunity === null;


            /*
             * NOVA oportunidade.
             */
            if ($isNewOpportunity) {

                $opportunity =
                    PublicOpportunity::create([
                        'source_key' =>
                            $data['source_key'],

                        'type' =>
                            $data['type'],

                        'title' =>
                            $data['title'] ?? null,

                        'notice_number' =>
                            $data['notice_number'] ?? null,

                        'agency' =>
                            $data['agency'] ?? null,

                        'municipality' =>
                            $data['municipality'] ?? null,

                        'state' =>
                            $data['state'] ?? null,

                        'positions' =>
                            $data['positions'] ?? [],

                        'education_levels' =>
                            $data['education_levels'] ?? [],

                        'vacancies' =>
                            $data['vacancies'] ?? null,

                        'salary_min' =>
                            $data['salary_min'] ?? null,

                        'salary_max' =>
                            $data['salary_max'] ?? null,

                        'registration_start' =>
                            $data['registration_start'] ?? null,

                        'registration_end' =>
                            $data['registration_end'] ?? null,

                        'exam_date' =>
                            $data['exam_date'] ?? null,

                        'fee_min' =>
                            $data['fee_min'] ?? null,

                        'fee_max' =>
                            $data['fee_max'] ?? null,

                        'registration_url' =>
                            $data['registration_url'] ?? null,

                        'summary' =>
                            $data['summary'] ?? null,

                        'review_status' =>
                            'pending',

                        'first_seen_at' =>
                            now(),

                        'last_seen_at' =>
                            now(),
                    ]);
            }


            /*
             * Registra a publicação.
             */
            $publication =
                OpportunityPublication::firstOrCreate(

                    [
                        'publication_key' =>
                            $data['publication_key'],
                    ],

                    [
                        'public_opportunity_id' =>
                            $opportunity->id,

                        'publication_type' =>
                            $data['publication_type'] ?? null,

                        'territory_id' =>
                            $data['territory_id'] ?? null,

                        'gazette_date' =>
                            $data['gazette_date'] ?? null,

                        'edition' =>
                            $data['edition'] ?? null,

                        'gazette_url' =>
                            $data['gazette_url'] ?? null,

                        'txt_url' =>
                            $data['txt_url'] ?? null,

                        'source_excerpt' =>
                            $data['source_excerpt'] ?? null,
                    ]
                );


            /*
             * Se apareceu uma NOVA publicação para
             * uma oportunidade já existente,
             * atualizamos os dados extraídos.
             */
            if (
                !$isNewOpportunity &&
                $publication->wasRecentlyCreated
            ) {

                $opportunity->update([

                    'type' =>
                        $data['type'],

                    'title' =>
                        $data['title'] ?? $opportunity->title,

                    'notice_number' =>
                        $data['notice_number']
                        ?? $opportunity->notice_number,

                    'agency' =>
                        $data['agency']
                        ?? $opportunity->agency,

                    'positions' =>
                        $data['positions']
                        ?? $opportunity->positions,

                    'education_levels' =>
                        $data['education_levels']
                        ?? $opportunity->education_levels,

                    'vacancies' =>
                        $data['vacancies']
                        ?? $opportunity->vacancies,

                    'salary_min' =>
                        $data['salary_min']
                        ?? $opportunity->salary_min,

                    'salary_max' =>
                        $data['salary_max']
                        ?? $opportunity->salary_max,

                    'registration_start' =>
                        $data['registration_start']
                        ?? $opportunity->registration_start,

                    'registration_end' =>
                        $data['registration_end']
                        ?? $opportunity->registration_end,

                    'exam_date' =>
                        $data['exam_date']
                        ?? $opportunity->exam_date,

                    'fee_min' =>
                        $data['fee_min']
                        ?? $opportunity->fee_min,

                    'fee_max' =>
                        $data['fee_max']
                        ?? $opportunity->fee_max,

                    'registration_url' =>
                        $data['registration_url']
                        ?? $opportunity->registration_url,

                    'summary' =>
                        $data['summary']
                        ?? $opportunity->summary,

                    // Nova publicação merece revisão.
                    'review_status' =>
                        'pending',
                ]);
            }


            $opportunity->update([
                'last_seen_at' => now(),
            ]);


            return response()->json([
                'success' => true,

                'opportunity_id' =>
                    $opportunity->id,

                'created_opportunity' =>
                    $isNewOpportunity,

                'created_publication' =>
                    $publication->wasRecentlyCreated,

                'status' =>
                    $opportunity->status,
            ], $isNewOpportunity ? 201 : 200);
        });
    }
}