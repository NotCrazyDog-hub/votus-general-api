<?php

namespace App\Services\News\Concerns;

/**
 * Compartilhado entre os coletores: o corpo de uma notícia vem em HTML
 * (parágrafos, pixels de rastreio, etc.), e aqui extraímos o texto puro e
 * legível, preservando quebras de parágrafo, pra usar como conteúdo
 * original exibido na API — o HTML bruto fica só em conteudo_original,
 * pra auditoria.
 */
trait ExtraiTextoDeHtml
{
    public function textoLimpo(string $html): string
    {
        $html = preg_replace('/<(p|div|h[1-6]|li)[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<\/(p|div|h[1-6]|li)>/i', "\n\n", $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;

        $texto = strip_tags($html);
        $texto = html_entity_decode($texto, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $texto = preg_replace('/[ \t]+/', ' ', $texto) ?? $texto;

        $linhas = array_filter(array_map('trim', explode("\n", $texto)), fn ($linha) => $linha !== '');

        return trim(implode("\n\n", $linhas));
    }
}
