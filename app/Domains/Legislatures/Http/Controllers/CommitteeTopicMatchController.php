<?php

namespace App\Domains\Legislatures\Http\Controllers;

use App\Domains\Legislatures\Models\CommitteeTopic;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class CommitteeTopicMatchController extends Controller
{
    /** Lista pares comissão/tema ainda não revisados e retorna seus nomes para a fila de revisão. */
    public function pending()
    {
        return CommitteeTopic::where('reviewed', false)
            ->whereNull('reviewed_source')
            ->with(['committee:id,name,acronym', 'topic:id,name'])
            ->get()
            ->map(fn ($ct) => [
                'id' => $ct->id,
                'committee_name' => $ct->committee->name,
                'committee_acronym' => $ct->committee->acronym,
                'topic_name' => $ct->topic->name,
                'text_similarity_score' => $ct->match_score,
            ]);
    }

    /**
     * Registra a decisão da revisão. Rejeições removem o par sugerido; aprovações
     * ficam marcadas como concluídas apenas com confiança igual ou superior a 0,8.
     */
    public function review(Request $request, CommitteeTopic $committeeTopic)
    {
        $validated = $request->validate([
            'approved' => 'required|boolean',
            'confidence' => 'required|numeric|min:0|max:1',
            'reasoning' => 'nullable|string',
        ]);

        if (!$validated['approved']) {
            $committeeTopic->delete(); // rejeitado pela IA, remove o par candidato
            return response()->json(['status' => 'rejected']);
        }

        $committeeTopic->update([
            'reviewed' => $validated['confidence'] >= 0.8, // só marca como "revisado" de vez se a IA tiver alta confiança
            'reviewed_source' => 'ai_review',
            'reviewed_at' => $validated['confidence'] >= 0.8 ? now() : null,
            'ai_confidence' => $validated['confidence'],
            'ai_reasoning' => $validated['reasoning'] ?? null,
        ]);

        return response()->json(['status' => 'reviewed']);
    }
}