<?php

namespace Database\Seeders;

use App\Enums\ProposalStatus;
use App\Models\Category;
use App\Models\Proposal;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProposalSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Wipe existing proposals (and, via cascade, their votes/comments/category
        // links) before reseeding with fresh sample data.
        Schema::disableForeignKeyConstraints();
        DB::table('proposals')->truncate();
        Schema::enableForeignKeyConstraints();

        $proposals = [
            [
                'title' => 'Ampliação do transporte público noturno',
                'content' => 'Proposta para estender o horário de funcionamento das linhas de ônibus municipais até as 2h, atendendo trabalhadores do turno da noite e reduzindo a dependência de transporte particular.',
                'categories' => ['Mobilidade urbana'],
                'author' => 'Guilherme Rodrigues',
            ],
            [
                'title' => 'Programa de reforço escolar em tempo integral',
                'content' => 'Criação de um programa de reforço escolar gratuito em turno complementar para estudantes da rede pública, com foco em língua portuguesa e matemática.',
                'categories' => ['Educação'],
                'author' => 'Marianne Moreira',
            ],
            [
                'title' => 'Incentivo fiscal para pequenas startups locais',
                'content' => 'Redução de alíquotas municipais para empresas de tecnologia com menos de 10 funcionários nos primeiros três anos de operação, estimulando o empreendedorismo local.',
                'categories' => ['Economia', 'Tecnologia'],
                'author' => 'Israely',
            ],
            [
                'title' => 'Expansão da coleta seletiva de lixo',
                'content' => 'Ampliação da coleta seletiva para todos os bairros da capital, com instalação de pontos de descarte de recicláveis e campanhas de conscientização ambiental.',
                'categories' => ['Meio ambiente'],
                'author' => 'Guilherme Rodrigues',
            ],
            [
                'title' => 'Wi-Fi público gratuito em praças e terminais',
                'content' => 'Instalação de internet sem fio gratuita em praças públicas e terminais de ônibus, ampliando o acesso digital para a população de baixa renda.',
                'categories' => ['Infraestrutura', 'Tecnologia'],
                'author' => 'Marianne Moreira',
            ],
            [
                'title' => 'Atendimento psicológico gratuito na rede pública de saúde',
                'content' => 'Ampliação do número de psicólogos nas unidades básicas de saúde, reduzindo o tempo de espera por atendimento em saúde mental.',
                'categories' => ['Saúde'],
                'author' => 'Israely',
            ],
        ];

        foreach ($proposals as $proposal) {
            $categories = $proposal['categories'];
            unset($proposal['categories']);

            $created = Proposal::create([...$proposal, 'status' => ProposalStatus::Published]);

            $categoryIds = collect($categories)
                ->map(fn ($name) => Category::firstOrCreate(['name' => $name])->id);
            $created->categories()->sync($categoryIds);

            $created->comments()->create([
                'author_name' => 'Visitante',
                'content' => 'Achei essa proposta muito relevante pra nossa cidade.',
            ]);
        }
    }
}
