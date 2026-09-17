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
* Composer

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

Descomente as linhas de código abaixo e preencha as variáveis conforme às credenciais do seu ambiente

```env
DB_CONNECTION=sqlite
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=laravel
# DB_USERNAME=root
# DB_PASSWORD=
```

Rode o comando artisan abaixo no terminal para gerar uma string de segurança

```bash
php artisan tinker --execute="echo Str::random(40);"
```

Em seguida, copie o resultado e cole na variável "SCHEDULER_TOKEN" do .env (esse mesmo token também autentica o disparo do pipeline de notícias e o `POST /api/news`)

Preencha também a variável "N8N_WEBHOOK_URL" do .env com a URL do n8n para conectar com seu agente de IA (usada apenas pelo `/api/agente/perguntar`, sem relação com a coleta de notícias)

Para o pipeline de notícias, preencha as variáveis `GROQ_API_KEY_1`, `GROQ_API_KEY_2` e `GROQ_API_KEY_3` com chaves da [Groq](https://console.groq.com) — o resumo de IA alterna entre elas para distribuir o limite de uso

2. Rode as migrations e sincronize os dados:
 
```bash
php artisan migrate
```
```bash
php artisan db:seed --class=FontesSeeder
```
```bash
php artisan sync:legislators-lower-house && php artisan sync:committees-lower-house && php artisan sync:legislators-senate && php artisan sync:committees-senate && php artisan sync:bills-lower-house && php artisan sync:bills-senate && php artisan sync:bill-status && php artisan sync:bill-topics && php artisan sync:legislator-professions
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
  Enums/
    ElectoralStatus.php               Status eleitoral
    LegislatorStatus.php              Status do mandato
  Http/Controllers/
    AgenteController.php              Interação com agente de IA
    LegislatorController.php          Listagem e exibição do perfil de parlamentares
    NewsController.php                Cadastro e listagem de notícias com filtro de relevância
    SchedulerController.php           Execução de tarefas agendadas e do pipeline de notícias via HTTP
  Http/Resources/
    LegislatorResource.php            Formata os campos expostos na API
  Jobs/News/
    ColetarAgenciaBrasilNoticiasJob.php  Coleta isolada por fonte (fila "coleta")
    ResumirNoticiaJob.php                Resumo isolado por notícia (fila "resumo")
Models/
  Bill.php                          Model de proposições
  Committee.php                     Model de comissões parlamentares
  Fonte.php                         Model das fontes de notícias e seu circuit breaker
  News.php                          Model de notícias
  Profession.php                    Model de profições
  Topic.php                         Model de temas
  Legislator.php                    Model da tabela legislators
Services/
  Concerns/
    NormalizesProfessionNames.php     Padronização dos nomes das profissões
  News/
    AgenciaBrasilCollector.php        Coleta e interpreta os feeds RSS da Agência Brasil
    GroqSummarizerService.php         Gera o resumo de IA alternando entre as chaves da Groq
    LinkNormalizer.php                Normaliza links para deduplicação
  LowerHouseApiService.php          Comunicação com API da Câmara
  SenateApiService.php              Comunicação com API do Senado
  LegislatorService.php             Queries no banco de dados
```

---

~ Equipe de desenvolvimento do Votus
