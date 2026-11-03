<?php

namespace Tests\Unit\Services;

use App\Domains\Legislatures\Models\Bill;
use App\Domains\Legislatures\Models\Topic;
use App\Services\VotusTopicClassificationService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VotusTopicClassificationServiceTest extends TestCase
{
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new VotusTopicClassificationService();

        Topic::insert([
            ['external_id' => 'votus_1', 'chamber' => 'votus', 'name' => 'Saúde', 'created_at' => now(), 'updated_at' => now()],
            ['external_id' => 'votus_2', 'chamber' => 'votus', 'name' => 'Educação', 'created_at' => now(), 'updated_at' => now()],
            ['external_id' => 'votus_3', 'chamber' => 'votus', 'name' => 'Meio Ambiente', 'created_at' => now(), 'updated_at' => now()],
            ['external_id' => 'lower_1', 'chamber' => 'lower_house', 'name' => 'Tema Câmara', 'created_at' => now(), 'updated_at' => now()],
        ]);
    }
}