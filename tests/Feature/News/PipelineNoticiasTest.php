<?php

namespace Tests\Feature\News;

use App\Jobs\News\ColetarAgenciaBrasilNoticiasJob;
use App\Jobs\News\ColetarPoder360NoticiasJob;
use App\Jobs\News\ResumirNoticiaJob;
use App\Models\Fonte;
use App\Models\News;
use App\Models\User;
use App\Services\News\AgenciaBrasilCollector;
use App\Services\News\LinkNormalizer;
use App\Services\News\Poder360Collector;
use App\Services\News\SelecionadorDestaqueNoticia;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

/**
 * Pipeline de notícias: filtro de imagem antes de gravar, deduplicação,
 * novas notícias a cada ciclo, independência cron x admin, notícia principal
 * e listagem pública. Nenhuma requisição sai pra internet
 * (Http::preventStrayRequests): feeds, páginas e imagens são simulados.
 */
class PipelineNoticiasTest extends NewsTestCase
{
    private const FEED = 'https://exemplo.com/feed.xml';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.scheduler.token' => 'token-de-teste']);

        // Caches locais (store "file") usados pelo ciclo automático e pelo
        // destaque — limpa pra um teste não herdar estado de outro.
        Cache::store('file')->forget('noticias:ciclo-vencido');
        Cache::store('file')->forget('noticias:destaque-atual');

        Http::preventStrayRequests();
    }

    /** @param array<int, array{titulo:string, link:string, imagem?:?string, data?:string}> $itens */
    private function feedAgenciaBrasil(array $itens): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><title>Feed</title>';

        foreach ($itens as $item) {
            $xml .= '<item>'
                . '<title>' . htmlspecialchars($item['titulo']) . '</title>'
                . '<link>' . htmlspecialchars($item['link']) . '</link>'
                . '<description>Conteúdo da matéria.</description>'
                . '<category>Política</category>'
                . '<pubDate>' . ($item['data'] ?? 'Fri, 25 Sep 2026 13:06:00 -0300') . '</pubDate>'
                . (isset($item['imagem']) ? '<imagem-destaque>' . htmlspecialchars($item['imagem']) . '</imagem-destaque>' : '')
                . '</item>';
        }

        return $xml . '</channel></rss>';
    }

    private function item(int $n, bool $comImagem = true): array
    {
        return [
            'titulo' => "Notícia número {$n}",
            'link' => "https://agenciabrasil.ebc.com.br/politica/noticia/2026-09/noticia-{$n}",
            'imagem' => $comImagem ? "https://imagens.ebc.com.br/fotos/foto-{$n}.jpg" : null,
        ];
    }

    /**
     * Simula a internet: feed, imagens (HEAD 200 image/jpeg), páginas de
     * artigo (sem og:image, salvo quando indicado) e imagens quebradas.
     */
    private string $feedAtual = '';

    private bool $httpFalsificado = false;

    private function fakeHttp(string $feedXml, array $extra = []): void
    {
        // O Laravel usa o PRIMEIRO stub registrado pra uma URL, então um
        // segundo Http::fake() não trocaria o feed. O feed fica numa
        // propriedade e o stub o lê a cada requisição (simula o feed mudando
        // entre um ciclo e outro).
        $this->feedAtual = $feedXml;

        if ($this->httpFalsificado) {
            return;
        }

        $this->httpFalsificado = true;

        Http::fake(array_merge($extra, [
            self::FEED => fn () => Http::response($this->feedAtual, 200),
            'https://imagens.ebc.com.br/*' => Http::response('', 200, ['Content-Type' => 'image/jpeg']),
            'https://static.poder360.com.br/*' => Http::response('', 200, ['Content-Type' => 'image/jpeg']),
            'https://agenciabrasil.ebc.com.br/*' => Http::response('<html><head><title>x</title></head></html>', 200),
            'https://www.poder360.com.br/*' => Http::response('<html><head><title>x</title></head></html>', 200),
        ]));
    }

    private function coletar(Fonte $fonte, ?int $limite = null): void
    {
        (new ColetarAgenciaBrasilNoticiasJob($fonte->id, $limite))->handle(
            app(AgenciaBrasilCollector::class),
            app(LinkNormalizer::class),
        );
    }

    // ---------------------------------------------------------------------
    // Teste 1 — notícia sem imagem não persiste
    // ---------------------------------------------------------------------
    public function test_noticia_sem_imagem_nao_e_persistida(): void
    {
        Queue::fake();
        $this->fakeHttp($this->feedAgenciaBrasil([$this->item(1, comImagem: false)]));

        $this->coletar(Fonte::factory()->create());

        $this->assertSame(0, News::count());
        Queue::assertNotPushed(ResumirNoticiaJob::class);
    }

    public function test_imagem_generica_ou_quebrada_tambem_nao_persiste(): void
    {
        Queue::fake();
        $this->fakeHttp(
            $this->feedAgenciaBrasil([
                ['titulo' => 'Com banner', 'link' => 'https://agenciabrasil.ebc.com.br/a/1', 'imagem' => 'https://imagens.ebc.com.br/eleicoes-2026-banner.png'],
                ['titulo' => 'Imagem 404', 'link' => 'https://agenciabrasil.ebc.com.br/a/2', 'imagem' => 'https://cdn.quebrado.com/x.jpg'],
                ['titulo' => 'URL inválida', 'link' => 'https://agenciabrasil.ebc.com.br/a/3', 'imagem' => 'nao-e-url'],
                ['titulo' => 'Não é imagem', 'link' => 'https://agenciabrasil.ebc.com.br/a/4', 'imagem' => 'https://cdn.html.com/pagina'],
            ]),
            [
                'https://cdn.quebrado.com/*' => Http::response('', 404),
                'https://cdn.html.com/*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html']),
            ],
        );

        $this->coletar(Fonte::factory()->create());

        $this->assertSame(0, News::count());
    }

    // ---------------------------------------------------------------------
    // Teste 2 — notícia com imagem válida persiste normalmente
    // ---------------------------------------------------------------------
    public function test_noticia_com_imagem_valida_e_persistida_e_vai_para_o_resumo(): void
    {
        Queue::fake();
        $this->fakeHttp($this->feedAgenciaBrasil([$this->item(1)]));

        $fonte = Fonte::factory()->create();
        $this->coletar($fonte);

        $this->assertSame(1, News::count());
        $noticia = News::first();
        $this->assertSame('https://imagens.ebc.com.br/fotos/foto-1.jpg', $noticia->image_url);
        $this->assertSame('pendente', $noticia->status_resumo);
        Queue::assertPushed(ResumirNoticiaJob::class, 1);
        $this->assertNotNull($fonte->refresh()->ultima_coleta_em);
    }

    public function test_sem_imagem_no_feed_usa_a_og_image_real_da_materia(): void
    {
        Queue::fake();
        $feed = file_get_contents(base_path('tests/Fixtures/poder360-feed.xml'));
        $fonte = Fonte::factory()->create([
            'nome' => 'Poder360',
            'slug' => 'poder360',
            'feeds' => ['geral' => self::FEED],
        ]);

        $this->fakeHttp($feed, [
            // 2º item do fixture não tem <img>: a página do artigo declara og:image.
            'https://www.poder360.com.br/economia/governo-anuncia-medida*' => Http::response(
                '<html><head><meta property="og:image" content="https://static.poder360.com.br/uploads/2026/09/medida-1200x675.jpg"></head></html>',
                200,
            ),
        ]);

        (new ColetarPoder360NoticiasJob($fonte->id))->handle(app(Poder360Collector::class), app(LinkNormalizer::class));

        $this->assertSame(2, News::count());
        $this->assertTrue(
            News::where('image_url', 'https://static.poder360.com.br/uploads/2026/09/medida-1200x675.jpg')->exists()
        );
        $this->assertSame(0, News::whereNull('image_url')->count());
    }

    // ---------------------------------------------------------------------
    // Teste 3 — duplicata não é criada
    // ---------------------------------------------------------------------
    public function test_duplicata_nao_e_criada(): void
    {
        Queue::fake();
        $mesmaMateria = $this->item(1);
        $mesmaMateriaComUtm = $mesmaMateria;
        $mesmaMateriaComUtm['link'] .= '?utm_source=rss';
        $this->fakeHttp($this->feedAgenciaBrasil([$mesmaMateria, $mesmaMateriaComUtm]));

        $fonte = Fonte::factory()->create();
        $this->coletar($fonte);
        $this->coletar($fonte);

        $this->assertSame(1, News::count());
    }

    // ---------------------------------------------------------------------
    // Teste 4 — cada ciclo busca notícias NOVAS (não as mesmas de sempre)
    // ---------------------------------------------------------------------
    public function test_ciclo_encontra_novas_mesmo_com_o_topo_do_feed_ja_no_banco(): void
    {
        Queue::fake();
        $fonte = Fonte::factory()->create();

        // Ciclo 1: 10 notícias.
        $this->fakeHttp($this->feedAgenciaBrasil(array_map(fn ($n) => $this->item($n), range(1, 10))));
        $this->coletar($fonte, 10);
        $this->assertSame(10, News::count());

        // Ciclo 2: as mesmas 10 continuam no topo do feed e chegaram 3 novas
        // embaixo. Antes, o corte dos N primeiros acontecia ANTES da checagem
        // de duplicata e o ciclo terminava sem nada novo.
        $this->fakeHttp($this->feedAgenciaBrasil(array_map(fn ($n) => $this->item($n), range(1, 13))));
        $this->coletar($fonte, 8);

        $this->assertSame(13, News::count());
        $this->assertTrue(News::where('title', 'Notícia número 13')->exists());
    }

    public function test_respeita_o_limite_de_novas_por_execucao(): void
    {
        Queue::fake();
        $this->fakeHttp($this->feedAgenciaBrasil(array_map(fn ($n) => $this->item($n), range(1, 20))));

        $this->coletar(Fonte::factory()->create(), 8);

        $this->assertSame(8, News::count());
    }

    public function test_ciclo_automatico_reparte_meta_de_15_entre_as_fontes(): void
    {
        Queue::fake();
        Fonte::factory()->create();
        Fonte::factory()->create(['nome' => 'Poder360', 'slug' => 'poder360']);

        $this->artisan('noticias:coletar')->assertSuccessful();

        Queue::assertPushed(ColetarAgenciaBrasilNoticiasJob::class, fn ($job) => $job->limite === 8);
        Queue::assertPushed(ColetarPoder360NoticiasJob::class, fn ($job) => $job->limite === 8);
    }

    // ---------------------------------------------------------------------
    // Testes 5 e 6 — botão do admin imediato e independente do cron
    // ---------------------------------------------------------------------
    public function test_botao_do_admin_roda_na_hora_mesmo_logo_apos_o_automatico_e_com_pendentes(): void
    {
        Bus::fake([ResumirNoticiaJob::class]);
        $this->fakeHttp($this->feedAgenciaBrasil([$this->item(1), $this->item(2)]));

        // Automático acabou de rodar (janela da fonte ainda não venceu) e
        // deixou resumo pendente — antes, o botão pulava a fonte ou devolvia 409.
        $fonte = Fonte::factory()->create(['ultima_coleta_em' => now()]);
        News::factory()->create(['status_resumo' => 'pendente', 'published' => false, 'imported_at' => now()]);

        Sanctum::actingAs(User::factory()->create(['is_admin' => true]));

        $this->postJson('/api/admin/news/collect')
            ->assertOk()
            ->assertJsonPath('status', 'executado');

        $this->assertTrue(News::where('title', 'Notícia número 1')->exists());
        $this->assertTrue($fonte->refresh()->ultima_coleta_em->isToday());
    }

    public function test_cron_continua_funcionando_depois_de_uma_atualizacao_manual(): void
    {
        Bus::fake([ResumirNoticiaJob::class]);
        $fonte = Fonte::factory()->create();

        // Manual primeiro.
        $this->fakeHttp($this->feedAgenciaBrasil([$this->item(1)]));
        $this->artisan('noticias:coletar', ['--limite' => 5, '--forcar' => true])->assertSuccessful();
        $this->assertSame(1, News::count());

        // 12h depois, o cron externo chama o endpoint de sempre.
        $this->travel(12)->hours();
        $this->fakeHttp($this->feedAgenciaBrasil([$this->item(1), $this->item(2)]));

        $this->postJson('/api/schedule/coletar-noticias', [], ['X-Scheduler-Token' => 'token-de-teste'])
            ->assertOk();

        $this->assertSame(2, News::count());
        $this->assertTrue($fonte->refresh()->ultima_coleta_em->greaterThan(now()->subMinute()));
    }

    public function test_janela_da_fonte_so_barra_o_automatico_nao_o_manual(): void
    {
        Queue::fake();
        Fonte::factory()->create(['ultima_coleta_em' => now()]);

        $this->artisan('noticias:coletar')->assertSuccessful();
        Queue::assertNothingPushed();

        $this->artisan('noticias:coletar', ['--forcar' => true])->assertSuccessful();
        Queue::assertPushed(ColetarAgenciaBrasilNoticiasJob::class, 1);
    }

    public function test_listagem_publica_dispara_o_ciclo_quando_passou_de_12h_sem_coleta(): void
    {
        Queue::fake();
        Fonte::factory()->create(['ultima_coleta_em' => now()->subHours(13)]);

        $this->getJson('/api/news')->assertOk();

        Queue::assertPushed(ColetarAgenciaBrasilNoticiasJob::class, 1);
    }

    public function test_listagem_publica_nao_dispara_ciclo_dentro_das_12h(): void
    {
        Queue::fake();
        Fonte::factory()->create(['ultima_coleta_em' => now()->subHours(2)]);

        $this->getJson('/api/news')->assertOk();

        Queue::assertNothingPushed();
    }

    // ---------------------------------------------------------------------
    // Teste 7 — notícia principal não fica presa no mesmo registro
    // ---------------------------------------------------------------------
    public function test_destaque_prioriza_recentes_e_alterna_ao_longo_do_dia(): void
    {
        // Manhã (09h em Fortaleza) de um dia fixo; as notícias são criadas
        // relativas a esse "agora" simulado.
        $manhaFortaleza = now('America/Fortaleza')->addDay()->setTime(9, 0);
        $this->travelTo($manhaFortaleza);

        $antigaNota10 = News::factory()->create(['relevance_score' => 10, 'published_at' => now()->subDays(7)]);
        $recente9 = News::factory()->create(['relevance_score' => 9, 'published_at' => now()->subHours(3)]);
        $recente8 = News::factory()->create(['relevance_score' => 8, 'published_at' => now()->subHours(5)]);
        News::factory()->create(['relevance_score' => 10, 'published_at' => now()->subHour(), 'image_url' => null]);

        $selecionador = app(SelecionadorDestaqueNoticia::class);

        $manha = $selecionador->selecionar();

        $this->travelTo($manhaFortaleza->copy()->setTime(15, 0));
        $tarde = $selecionador->selecionar();

        $this->assertSame($recente9->id, $manha->id);
        $this->assertSame($recente8->id, $tarde->id);
        $this->assertNotSame($antigaNota10->id, $manha->id);
    }

    public function test_destaque_nunca_fica_vazio_se_nao_houver_recentes(): void
    {
        $unica = News::factory()->create(['published_at' => now()->subDays(10)]);

        $this->assertSame($unica->id, app(SelecionadorDestaqueNoticia::class)->selecionar()->id);
    }

    // ---------------------------------------------------------------------
    // Teste 8 — API pública: novos registros, sem notícia sem imagem
    // ---------------------------------------------------------------------
    public function test_listagem_publica_traz_novas_sem_notícias_sem_imagem_e_com_destaque(): void
    {
        $comImagem = News::factory()->create(['published_at' => now()->subHour()]);
        $semImagem = News::factory()->create(['published_at' => now(), 'image_url' => null]);
        $banner = News::factory()->create(['published_at' => now(), 'image_url' => 'https://imagens.ebc.com.br/eleicoes-2026-banner.png']);
        News::factory()->create(['published' => false]);

        $resposta = $this->getJson('/api/news')->assertOk();

        $ids = collect($resposta->json('data'))->pluck('id');
        $this->assertTrue($ids->contains($comImagem->id));
        $this->assertFalse($ids->contains($semImagem->id));
        $this->assertFalse($ids->contains($banner->id));
        $this->assertSame($comImagem->id, $resposta->json('destaque.id'));

        // Nada foi apagado: a notícia sem imagem continua no banco.
        $this->assertTrue(News::whereKey($semImagem->id)->exists());
    }

    public function test_datas_do_feed_sao_gravadas_em_utc(): void
    {
        Queue::fake();
        $item = $this->item(1);
        $item['data'] = 'Fri, 25 Sep 2026 13:06:00 -0300';
        $this->fakeHttp($this->feedAgenciaBrasil([$item]));

        $this->coletar(Fonte::factory()->create());

        $this->assertSame('2026-09-25 16:06:00', News::first()->published_at->format('Y-m-d H:i:s'));
    }

    public function test_entrada_interna_de_noticia_tambem_exige_imagem(): void
    {
        $this->fakeHttp('');

        $payload = [
            'title' => 'Sem imagem',
            'ai_summary' => 'Resumo',
            'url' => 'https://exemplo.com/sem-imagem',
            'published_at' => now()->toDateTimeString(),
            'relevance_score' => 5,
            'keywords' => ['x'],
        ];

        $this->postJson('/api/news', $payload, $this->cabecalhoInterno())->assertStatus(422);
        $this->assertSame(0, News::count());
    }

    private function cabecalhoInterno(): array
    {
        // O middleware internal.token reaproveita o token do scheduler.
        return ['X-Scheduler-Token' => 'token-de-teste'];
    }
}
