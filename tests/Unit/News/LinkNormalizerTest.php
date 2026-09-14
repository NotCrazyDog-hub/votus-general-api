<?php

namespace Tests\Unit\News;

use App\Services\News\LinkNormalizer;
use Tests\TestCase;

class LinkNormalizerTest extends TestCase
{
    public function test_it_removes_tracking_parameters_and_trailing_slash(): void
    {
        $normalizer = new LinkNormalizer();

        $comTracking = $normalizer->normalizar('https://site.com/noticia/?utm_source=chatgpt.com&fbclid=abc');
        $semTracking = $normalizer->normalizar('https://site.com/noticia');

        $this->assertSame($semTracking, $comTracking);
    }

    public function test_it_treats_www_and_non_www_as_the_same_link(): void
    {
        $normalizer = new LinkNormalizer();

        $comWww = $normalizer->normalizar('https://www.site.com/noticia');
        $semWww = $normalizer->normalizar('https://site.com/noticia');

        $this->assertSame($semWww, $comWww);
    }
}
