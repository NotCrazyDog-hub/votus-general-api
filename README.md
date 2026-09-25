<p align="center">
    <img width="400" alt="Votus Logo" src="https://github.com/user-attachments/assets/391c325b-9cb0-4998-a657-c7f587cbefa9" />
</p>

<p align="center">
    <em>Seu voto, sua escolha, seu futuro.</em>
</p>

<p align="center"> 
    <img loading="lazy" src="http://img.shields.io/static/v1?label=STATUS&message=EM%20DESENVOLVIMENTO&color=FFDE21&style=for-the-badge"/> 
</p>

## Sobre o projeto

O **Votus** é um sistema de transparência política desenvolvido para o **Ceará Científico 2026**. Ele monitora deputados e senadores do Ceará, cruzando o discurso público deles com suas ações reais por meio de um **Índice de Confiabilidade** (percentual de coerência legislativa) e uma aba de notícias. O sistema também conta com um agente de IA que permite ao usuário fazer perguntas diretamente sobre política, obtendo respostas contextualizadas.

## 🚀 Começando

Essas instruções permitirão que você obtenha uma cópia do projeto em operação na sua máquina local para fins de desenvolvimento e teste.

### 📋 Pré-requisitos

* PHP 8.3+
* Composer 2
* SQLite para execução local ou PostgreSQL/Supabase para um ambiente compartilhado

### 🔧 Instalação
 
1. Clone o repositório e instale as dependências:
 
```bash
git clone https://github.com/NotCrazyDog-hub/votus-general-api.git
```
```bash
cd votus-general-api
```
```bash
composer install
```
 
2. Preencha as credenciais no `.env`:

```bash
copy .env.example .env
```
```bash
php artisan key:generate
```

Crie o link dos arquivos públicos e prepare o banco:

```bash
php artisan storage:link
php artisan migrate
```

Popule os dados iniciais:

```bash
php artisan db:seed --class=FontesSeeder
php artisan db:seed --class=SuggestionQuestionsSeeder
php artisan db:seed --class=LegislaturePeriodSeeder
```

O `ProposalSeeder` é opcional e serve apenas para dados de exemplo:

```bash
php artisan db:seed --class=ProposalSeeder
```

Preencha as variáveis conforme as credenciais do seu ambiente:

```env
DB_CONNECTION=sqlite
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=laravel
# DB_USERNAME=root
# DB_PASSWORD=
```

Para SQLite, mantenha `DB_CONNECTION=sqlite` e verifique se o arquivo `database/database.sqlite` existe. Para PostgreSQL, use `DB_PORT=5432` e preencha `DB_HOST`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD`.

Rode o comando artisan abaixo no terminal para gerar uma string de segurança

```bash
php artisan tinker --execute="echo Str::random(40);"
```

Em seguida, copie o resultado e cole na variável "SCHEDULER_TOKEN" do .env (esse mesmo token também autentica o disparo do pipeline de notícias e o `POST /api/news`)

Preencha também a variável "N8N_WEBHOOK_URL" do .env com a URL do n8n para conectar com seu agente de IA (usada apenas pelo `/api/agente/perguntar`, sem relação com a coleta de notícias)

Para o pipeline de notícias, preencha as variáveis `GROQ_API_KEY_1`, `GROQ_API_KEY_2` e `GROQ_API_KEY_3` com chaves da [Groq](https://console.groq.com) — o resumo de IA alterna entre elas para distribuir o limite de uso

Para importar documentos de propostas de candidatos, configure também o armazenamento S3 do Supabase:

```env
SUPABASE_STORAGE_KEY=
SUPABASE_STORAGE_SECRET=
SUPABASE_STORAGE_BUCKET=votus-documents
SUPABASE_STORAGE_ENDPOINT=https://seu-projeto.storage.supabase.co/storage/v1/s3
SUPABASE_STORAGE_REGION=sa-east-1
```

Para importar vagas da Adzuna:

```env
ADZUNA_APP_ID=
ADZUNA_APP_KEY=
ADZUNA_COUNTRY=br
```

Para o ambiente local com filas, sessão e cache no banco, mantenha:

```env
QUEUE_CONNECTION=database
SESSION_DRIVER=database
CACHE_STORE=database
```

2. Rode as migrations e sincronize os dados:
 
```bash
php artisan migrate
```
```bash
php artisan db:seed --class=FontesSeeder
```
Sincronize os dados legislativos em sequência:

```bash
php artisan sync:legislators-lower-house && php artisan sync:committees-lower-house && php artisan sync:legislators-senate && php artisan sync:committees-senate && php artisan sync:bills-lower-house && php artisan sync:bills-senate && php artisan sync:bill-status && php artisan sync:bill-topics && php artisan sync:legislator-professions
```

Os demais comandos dependem de arquivos de entrada ou de dados legislativos já sincronizados. Execute-os conforme a necessidade:

```bash
# Candidatos do TSE
php artisan sync:candidates-tse storage/app/tse/consulta_cand_2026_CE.csv --uf=CE --year=2026
php artisan candidates:link-running-mates --uf=CE --year=2026

# Universidades e cursos do MEC
php artisan universities:import-mec PDA_Dados_Cursos_Graduacao_Brasil.csv --state=CE

# Métricas legislativas
php artisan metrics:effectiveness
php artisan metrics:productivity
php artisan metrics:thematic-focus

# Vagas da Adzuna, caso as credenciais estejam configuradas no .env
php artisan jobs:import-adzuna --pages=2
```
```bash
php artisan serve
```

A API estará disponível em `http://localhost:8000`
 
## 📡 Endpoints
 
```
GET /api/deputies                       Lista deputados federais
GET /api/deputies/{external_id}         Perfil de um deputado
GET /api/senators                       Lista senadores
GET /api/senators/{external_id}         Perfil de um senador
GET /api/schedule/status                Healthcheck do Scheduler via HTTP
POST /api/schedule/coletar-noticias     Dispara a coleta de notícias (token no header X-Scheduler-Token)
POST /api/schedule/processar-fila-noticias  Drena a fila de resumo pendente (token no header X-Scheduler-Token)
POST /api/news                          Cadastro manual de notícias (token no header X-Scheduler-Token)
GET /api/news                           Listagem de notícias publicadas
GET /api/news/{news}                    Exibição detalhada de uma notícia
POST /api/agente/perguntar              Envio de perguntas para o agente de IA
GET /api/governor-candidates             Lista candidatos a governador
GET /api/senate-candidates               Lista candidatos ao Senado
GET /api/federal-deputy-candidates       Lista candidatos a deputado federal
GET /api/state-deputy-candidates         Lista candidatos a deputado estadual
GET /api/proposals                        Lista proposições
GET /api/explanations                     Lista explicações publicadas
GET /api/course-offerings                 Lista ofertas de cursos
GET /api/public-opportunities             Lista oportunidades públicas
GET /api/opportunities                    Lista vagas profissionais
POST /api/suggestions                     Envia sugestões
GET /api/suggestion-questions             Lista perguntas de sugestões
POST /api/admin/login                     Autentica no painel administrativo
```

## 🔄 Sincronização de dados
 
A sincronização semanal via Laravel Scheduler está em fases de testes, a partir das APIs públicas:
 
* **Câmara dos Deputados:** `dadosabertos.camara.leg.br/api/v2`
* **Senado Federal:** `legis.senado.leg.br/dadosabertos`

Para sincronizar manualmente:
 
```bash
php artisan sync:legislators-lower-house
```
```bash
php artisan sync:committees-lower-house
```
```bash
php artisan sync:bills-lower-house
```
```bash
php artisan sync:legislators-senate
```
```bash
php artisan sync:committees-senate
```
```bash
php artisan sync:bills-senate
```
```bash
php artisan sync:bill-status
```
```bash
php artisan sync:bill-topics
```
```bash
php artisan sync:legislator-professions
```

### Candidatos do TSE

Baixe o CSV de candidaturas do TSE e execute:

```bash
php artisan sync:candidates-tse storage/app/tse/consulta_cand_2026_CE.csv --uf=CE --year=2026
php artisan candidates:link-running-mates --uf=CE --year=2026
```

Para fotos e propostas, informe as pastas locais correspondentes:

```bash
php artisan import:candidate-photos storage/app/tse/foto_cand2026_CE_div
php artisan import:candidate-proposals storage/app/tse/propostas --disk=supabase --max-size=20480
```

### Universidades, cursos e oportunidades

Coloque o CSV do MEC em `storage/app/imports/` e importe-o. É possível filtrar por UF ou limitar o volume durante um teste:

```bash
php artisan universities:import-mec PDA_Dados_Cursos_Graduacao_Brasil.csv --state=CE
php artisan universities:import-mec PDA_Dados_Cursos_Graduacao_Brasil.csv --state=CE --limit=100
```

Para importar vagas da Adzuna, configure `ADZUNA_APP_ID` e `ADZUNA_APP_KEY` no `.env`:

```bash
php artisan jobs:import-adzuna --pages=2
```

### Métricas

```bash
php artisan metrics:effectiveness
php artisan metrics:productivity
php artisan metrics:thematic-focus
```

### Usuário administrador

Não há cadastro público de administradores. Crie ou promova um usuário pelo comando interativo:

```bash
php artisan admin:create-user
```

## 📰 Pipeline de notícias

A coleta e o resumo de notícias rodam nativamente em Laravel (Jobs + Filas), sem depender de automação externa. Fontes implementadas: **Agência Brasil** (RSS oficial, 9 categorias) e **Poder360** (RSS oficial). Cada fonte tem seu próprio Job de coleta isolado — uma falha numa fonte não afeta a outra.

* **Fontes** ficam cadastradas na tabela `fontes` (uma fonte pode ter várias categorias/feeds em `feeds`, como a Agência Brasil).
* **Coleta** (`fila coleta`): um Job isolado por fonte busca os feeds, normaliza os links, deduplica por `link_normalizado` e persiste a notícia original.
* **Resumo** (`fila resumo`): um Job isolado por notícia chama a API da Groq (alternando entre até 3 chaves) para gerar o resumo, a relevância e as palavras-chave, respeitando rate limiting.
* **Circuit breaker**: uma fonte é desativada automaticamente após atingir `limite_falhas` falhas consecutivas de coleta.

```bash
php artisan noticias:coletar
```
Despacha os Jobs de coleta para as fontes ativas e elegíveis (respeita `offset_minutos` entre execuções).

```bash
php artisan queue:work --queue=coleta,resumo --stop-when-empty
```
Processa a fila localmente. Como o plano atual de hospedagem (Render free) não mantém um worker de fila em segundo plano, em produção isso é feito por dois agendamentos separados no cron-job.org (ambos com o header `X-Scheduler-Token: <SCHEDULER_TOKEN>`):

| Frequência | Endpoint | Função |
|---|---|---|
| Diariamente às 06:00 e às 18:00 (12 em 12h, a partir das 6 da manhã) | `POST /api/schedule/coletar-noticias` | Despacha a coleta das fontes ativas e já começa a drenar a fila |
| A cada 5-10 min | `POST /api/schedule/processar-fila-noticias` | Só drena o que ainda está pendente na fila de resumo |

Os dois agendamentos são necessários porque o resumo respeita rate limiting (10 chamadas de IA por minuto, para não estourar a cota das chaves da Groq) — se só a coleta de 12 em 12 horas drenasse a fila, o volume de notícias coletado de uma vez nunca terminaria de ser resumido antes da próxima leva chegar. O agendamento mais frequente garante que o backlog é sempre consumido entre uma coleta e outra.

```bash
php artisan fontes:reativar {slug}
```
Reativa manualmente uma fonte desativada pelo circuit breaker, zerando o contador de falhas.

## 🛠️ Stack
 
* [Laravel](https://laravel.com) - API Backend
* [Supabase](https://supabase.com) - Banco de dados PostgreSQL
* [Docker](https://www.docker.com/) - Containerização e ambiente de execução da API no Render
* [Render](https://render.com/) - Plataforma de hospedagem
* [cron-job.org](https://cron-job.org/en/) - Disparo das tarefas agendadas com Cron
* [Groq](https://groq.com/) - Geração dos resumos de notícias por IA
* [n8n](https://n8n.io/) - Automação do agente de perguntas (`/api/agente/perguntar`)

 ## 📁 Estrutura do projeto
 
```
app/
  Console/Commands/
    SyncBillStatus.php                Sincroniza situação das proposições
    SyncBillTopics.php                Sincroniza temas das proposições
    SyncLegislatorProfessions.php     Sincroniza profissões
    SyncLowerHouseBills.php           Sincroniza proposições de deputados
    SyncLowerHouseCommittees.php      Sincroniza comissões de deputados
    SyncLowerHouseLegislators.php     Sincroniza deputados federais
    SyncSenateBills.php               Sincroniza proposições de senadores
    SyncSenateCommittees.php          Sincroniza comissões de senadores
    SyncSenateLegislators.php         Sincroniza senadores
    ColetarNoticias.php               Despacha os Jobs de coleta de notícias
    FontesReativar.php                Reativa uma fonte desativada pelo circuit breaker
    CalculateLegislativeEffectiveness.php  Calcula efetividade legislativa
    CalculateLegislativeProductivity.php   Calcula produtividade legislativa
    CalculateThematicFocus.php             Calcula foco temático
    CreateAdminUser.php                    Cria ou promove administrador
    ImportAdzunaJobs.php                   Importa vagas da Adzuna
    ImportCandidatePhotos.php              Importa fotos de candidatos
    ImportCandidateProposalDocuments.php   Importa propostas em PDF
    ImportMecCourses.php                   Importa cursos do MEC
    LinkCandidateRunningMates.php          Vincula titulares e suplentes
    LimparNoticiasPendentesAntigas.php     Limpa notícias pendentes antigas
    SyncCandidatesTse.php                  Importa candidatos do TSE
  Enums/
    CandidateOffice.php               Cargos eleitorais
    ElectoralStatus.php               Status eleitoral
    LegislatorStatus.php              Status do mandato
    ProposalStatus.php                Status da proposição
    ProposalVoteType.php              Tipo de voto na proposição
  Http/Controllers/
    AgenteController.php              Interação com agente de IA
    CandidateController.php            Listagem e exibição de candidatos
    CategoryController.php             Listagem de categorias
    CommitteeTopicMatchController.php Revisão de correspondências de temas
    CourseOfferingController.php      Listagem de ofertas de cursos
    ExplanationController.php          Listagem de explicações
    LegislatorController.php          Listagem e exibição do perfil de parlamentares
    NewsController.php                Cadastro e listagem de notícias com filtro de relevância
    OpportunityController.php         Listagem de oportunidades profissionais
    ProposalCommentController.php     Comentários de proposições
    ProposalController.php             Proposições e votos
    PublicOpportunityController.php   Oportunidades públicas
    PublicOpportunityImportController.php Importação de oportunidades públicas
    SantinhoController.php             Geração de santinhos
    SchedulerController.php           Execução de tarefas agendadas e do pipeline de notícias via HTTP
    SiteVisitController.php            Registro de visitas
    SuggestionController.php           Envio de sugestões
    SuggestionQuestionController.php   Perguntas de sugestões
    UniversityController.php           Consulta de universidades
    Admin/                             Controllers do painel administrativo
  Http/Resources/
    AdmissionMethodResource.php        Formata modalidades de ingresso
    BillResource.php                   Formata proposições
    CandidateResource.php              Formata candidatos
    CampusResource.php                 Formata campi
    CourseCurriculumResource.php       Formata currículos
    CourseOfferingResource.php         Formata ofertas de cursos
    CurriculumSubjectResource.php      Formata disciplinas
    ExplanationResource.php            Formata explicações
    LegislatorResource.php            Formata os campos expostos na API
    LegislatorsResource.php            Formata listas de parlamentares
    LegislatorSummaryResource.php      Formata resumos de parlamentares
    NewsResource.php                   Formata notícias
    OpportunityResource.php            Formata oportunidades profissionais
    OpportunityPublicationResource.php Formata publicações de oportunidades
    ProposalCommentResource.php        Formata comentários
    ProposalResource.php               Formata proposições
    PublicOpportunityResource.php      Formata oportunidades públicas
    TopicResource.php                  Formata temas
    UniversityResource.php             Formata universidades
  Http/Middleware/
    CacheHeaders.php                  Adiciona cache às respostas públicas
    EnsureIsAdmin.php                 Autoriza administradores
    EnsureN8nToken.php                Valida o token do n8n
    VerificaTokenInterno.php          Valida o token interno
  Jobs/Explanations/
    GerarConteudoExplicacaoJob.php    Gera conteúdo de explicações
  Jobs/News/
    ColetarAgenciaBrasilNoticiasJob.php  Coleta isolada por fonte (fila "coleta")
    ColetarPoder360NoticiasJob.php        Coleta isolada do Poder360 (fila "coleta")
    ResumirNoticiaJob.php                Resumo isolado por notícia (fila "resumo")
Models/
  AdmissionMethod.php                Model de modalidades de ingresso
  Bill.php                          Model de proposições
  BillTramitation.php               Model de tramitações
  Campus.php                        Model de campi
  Committee.php                     Model de comissões parlamentares
  CommitteeTopic.php                Model de temas de comissões
  CourseCurriculum.php              Model de currículos
  CourseOffering.php                Model de ofertas de cursos
  CurriculumSubject.php             Model de disciplinas
  Explanation.php                   Model de explicações
  ExplanationSource.php             Model de fontes das explicações
  Fonte.php                         Model das fontes de notícias e seu circuit breaker
  LegislaturePeriod.php             Model de períodos legislativos
  News.php                          Model de notícias
  Opportunity.php                   Model de oportunidades profissionais
  OpportunityPublication.php        Model de publicações de oportunidades
  Profession.php                    Model de profições
  Proposal.php                      Model de proposições
  ProposalComment.php               Model de comentários
  ProposalVote.php                  Model de votos
  PublicOpportunity.php             Model de oportunidades públicas
  QuizOption.php                    Model de opções de quiz
  QuizQuestion.php                  Model de perguntas de quiz
  SantinhoGeneration.php             Model de gerações de santinho
  SiteVisit.php                     Model de visitas
  Suggestion.php                    Model de sugestões
  SuggestionAnswer.php              Model de respostas de sugestões
  SuggestionQuestion.php            Model de perguntas de sugestões
  Topic.php                         Model de temas
  TrustedSource.php                 Model de fontes confiáveis
  University.php                    Model de universidades
  Legislator.php                    Model da tabela legislators
Services/
  Concerns/
    NormalizesProfessionNames.php     Padronização dos nomes das profissões
  CandidateService.php              Consultas de candidatos
  Explanations/
    ExplanationSourceFetcher.php     Busca fontes para explicações
    GroqExplanationService.php       Gera conteúdo de explicações com IA
  Jobs/
    AdzunaProvider.php               Integração com a Adzuna
  Metrics/
    LegislativeEffectivenessService.php  Métrica de efetividade legislativa
    LegislativeProductivityService.php   Métrica de produtividade legislativa
    ThematicConsistencyService.php       Consistência temática
    ThematicFocusService.php             Foco temático
  News/
    AgenciaBrasilCollector.php        Coleta e interpreta os feeds RSS da Agência Brasil
    GroqSummarizerService.php         Gera o resumo de IA alternando entre as chaves da Groq
    LinkNormalizer.php                Normaliza links para deduplicação
    NewsCategoryPriority.php           Prioridade das categorias de notícias
    Poder360Collector.php              Coleta e interpreta os feeds do Poder360
  LowerHouseApiService.php          Comunicação com API da Câmara
  SenateApiService.php              Comunicação com API do Senado
  ProposalService.php               Consultas de proposições
  LegislatorService.php             Queries no banco de dados
  TseCandidatesCsvService.php       Leitura do CSV de candidatos do TSE
database/seeders/
  FontesSeeder.php                  Fontes de notícias
  LegislaturePeriodSeeder.php       Períodos legislativos
  ProposalSeeder.php                Dados de exemplo de propostas
  SuggestionQuestionsSeeder.php     Perguntas de sugestões
```

---
~ Equipe de desenvolvimento do Votus
