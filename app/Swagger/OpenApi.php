<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'Votus API',
    description: 'Documentação da API do Votus'
)]
#[OA\Server(
    url: 'https://votus-core.onrender.com/api',
    description: 'Votus API'
)]
class OpenApi
{
}
