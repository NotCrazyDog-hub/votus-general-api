<?php

namespace App\Domains\News\Services\Concerns;

/**
 * Compartilhado entre os coletores: o corpo de uma notícia vem em HTML
 * (parágrafos, pixels de rastreio, etc.), e aqui extraímos o texto puro e
 * legível, preservando quebras de parágrafo, pra usar como conteúdo
 * original exibido na API — o HTML bruto fica só em conteudo_original,
 * pra auditoria.
 */
trait ExtractsTextFromHtml
{
    public function cleanText(string $html): string
    {
        $html = preg_replace('/<(p|div|h[1-6]|li)[^>]*>/i', '', $html) ?? $html;
        $html = preg_replace('/<\/(p|div|h[1-6]|li)>/i', "\n\n", $html) ?? $html;
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;

        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;

        $lines = array_filter(array_map('trim', explode("\n", $text)), fn ($line) => $line !== '');

        return trim(implode("\n\n", $lines));
    }
}
