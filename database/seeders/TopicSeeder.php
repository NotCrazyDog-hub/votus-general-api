<?php

namespace Database\Seeders;

use App\Domains\Legislatures\Models\Topic;
use Illuminate\Database\Seeder;

class TopicSeeder extends Seeder
{
    public function run(): void
    {
        $topics = [
            'Administração Pública e Gestão Governamental',
            'Transparência e Controle Público',
            'Orçamento e Finanças Públicas',
            'Tributação e Política Fiscal',
            'Economia e Desenvolvimento',
            'Trabalho e Relações Trabalhistas',
            'Previdência Social',
            'Assistência Social e Combate à Pobreza',
            'Saúde Pública',
            'Educação',
            'Ciência, Tecnologia e Inovação',
            'Meio Ambiente e Sustentabilidade',
            'Agricultura e Desenvolvimento Rural',
            'Água, Energia e Recursos Naturais',
            'Infraestrutura e Mobilidade',
            'Habitação e Desenvolvimento Urbano',
            'Segurança Pública',
            'Justiça e Sistema Penal',
            'Direitos Fundamentais e Cidadania',
            'Direitos Humanos e Proteção Social',
            'Infância, Adolescência e Juventude',
            'Mulheres e Igualdade de Gênero',
            'Inclusão e Acessibilidade',
            'Família e Proteção Familiar',
            'Cultura, Patrimônio e Identidade',
            'Esporte e Lazer',
            'Turismo e Desenvolvimento Regional',
            'Comunicação e Sociedade Digital',
            'Democracia e Sistema Político',
            'Relações Internacionais e Defesa Nacional',
            'Organização do Estado e Federalismo',
            'Homenagens e Reconhecimentos',
        ];

        foreach ($topics as $name) {
            Topic::updateOrCreate(
                [
                    'name' => $name,
                    'chamber' => 'votus',
                ],
                [
                    'external_id' => null,
                ]
            );
        }
    }
}