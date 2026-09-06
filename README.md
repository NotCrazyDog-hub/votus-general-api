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

O **Votus** é um sistema de transparência política desenvolvido para o **Ceará Científico 2026**. Ele monitora deputados e senadores do Ceará, cruzando o discurso público deles com suas ações reais por meio de um **Índice de Confiabilidade** (percentual de coerência legislativa) e uma aba de notícias.

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

Em seguida, copie o resultado e cole na variável "SCHEDULER_TOKEN" do .env

Preencha também a variável "N8N_WEBHOOK_URL" do .env com a URL do n8n para conectar com seu agente de IA

2. Rode as migrations e sincronize os dados:
 
```bash
php artisan migrate
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
GET /api/deputies                    Lista deputados federais
GET /api/deputies/{external_id}      Perfil de um deputado
GET /api/senators                    Lista senadores
GET /api/senators/{external_id}      Perfil de um senador
GET /api/schedule/status             Dispara o Laravel Scheduler via HTTP
POST /api/news                       Cadastro de notícias
GET /api/news                        Listagem de notícias
GET /api/news/{news}                 Exibição detalhada de uma notícia
POST /api/agente/perguntar           Envio de perguntas para o agente de IA
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

## 🛠️ Stack
 
* [Laravel](https://laravel.com) - API Backend
* [Supabase](https://supabase.com) - Banco de dados PostgreSQL
* [Docker](https://www.docker.com/) - Containerização e ambiente de execução da API no Render
* [Render](https://render.com/) - Plataforma de hospedagem
* [cron-job.org](https://cron-job.org/en/) - Disparo das tarefas agendadas com Cron
* [n8n](https://n8n.io/) - Automação de tarefas

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
  Enums/
    ElectoralStatus.php               Status eleitoral
    LegislatorStatus.php              Status do mandato
  Http/Controllers/
    AgenteController.php              Interação com agente de IA
    LegislatorController.php          Listagem e exibição do perfil de parlamentares
    NewsController.php                Cadastro e listagem de notícias com filtro de relevância
    SchedulerController.php           Execução de tarefas agendadas via HTTP
  Http/Resources/
    LegislatorResource.php            Formata os campos expostos na API
Models/
  Bill.php                          Model de proposições
  Committee.php                     Model de comissões parlamentares
  News.php                          Model de notícias
  Profession.php                    Model de profições
  Topic.php                         Model de temas
  Legislator.php                    Model da tabela legislators
Services/
  Concerns/
    NormalizesProfessionNames.php     Padronização dos nomes das profissões
  LowerHouseApiService.php          Comunicação com API da Câmara
  SenateApiService.php              Comunicação com API do Senado
  LegislatorService.php             Queries no banco de dados
```

---

~ Equipe de desenvolvimento do Votus
