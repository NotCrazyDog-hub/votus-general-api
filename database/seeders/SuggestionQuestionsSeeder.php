<?php

namespace Database\Seeders;

use App\Models\SuggestionQuestion;
use Illuminate\Database\Seeder;

/**
 * Migra pra pergunta dinâmica as 5 perguntas que antes eram fixas no
 * código do SugestoesPage — a partir daqui, novas perguntas são
 * cadastradas pelo admin em /admin/sugestoes, não mais no frontend.
 *
 * As duas caixas de texto condicionais que existiam antes ("Outro: conte
 * mais" e "Sim: qual foi a dificuldade") não têm equivalente aqui: eram um
 * relacionamento entre perguntas (pergunta B só aparece dependendo da
 * resposta da pergunta A), e o pedido era poder adicionar/editar/remover
 * perguntas independentes pelo painel — não montar um construtor de lógica
 * condicional entre perguntas, que é bem mais complexo.
 */
class SuggestionQuestionsSeeder extends Seeder
{
    public function run(): void
    {
        $perguntas = [
            [
                'text' => 'Você gostou do Votus?',
                'type' => 'choice',
                'options' => ['Sim, gostei', 'Mais ou menos', 'Não gostei'],
                'required' => true,
            ],
            [
                'text' => 'O que você mais gostou no Votus?',
                'type' => 'choice',
                'options' => [
                    'Notícias',
                    'Deputados e senadores',
                    'Informações sobre eleições e cargos',
                    'Gerador de santinho',
                    'Propostas',
                    'Outro',
                ],
                'required' => true,
            ],
            [
                'text' => 'Você encontrou alguma dificuldade ao usar o Votus?',
                'type' => 'choice',
                'options' => ['Não', 'Sim'],
                'required' => true,
            ],
            [
                'text' => 'Você tem alguma sugestão para o Votus?',
                'type' => 'text',
                'options' => null,
                'required' => false,
            ],
            [
                'text' => 'Você usaria o Votus novamente?',
                'type' => 'choice',
                'options' => ['Sim', 'Talvez', 'Não'],
                'required' => true,
            ],
        ];

        foreach ($perguntas as $index => $pergunta) {
            SuggestionQuestion::firstOrCreate(
                ['text' => $pergunta['text']],
                [...$pergunta, 'order_index' => $index + 1]
            );
        }
    }
}
