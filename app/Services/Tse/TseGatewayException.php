<?php

namespace App\Services\Tse;

use RuntimeException;
use Throwable;

/**
 * Falha estruturada na ponte Laravel <-> Chromium/Playwright do TSE.
 *
 * Carrega um código de erro estável (`TSE_ACCESS_DENIED`, `TSE_HTTP_500`,
 * `INVALID_JSON`, `NODE_FAILED`...) para que o chamador possa classificar a
 * falha sem analisar a mensagem — que é humana e pode mudar.
 */
class TseGatewayException extends RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        private readonly ?int $status = null,
        string $message = '',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message !== '' ? $message : $errorCode, 0, $previous);
    }

    /**
     * Código estável da falha (ex.: `TSE_ACCESS_DENIED`).
     */
    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * HTTP status respondido pelo TSE, quando houve um.
     */
    public function status(): ?int
    {
        return $this->status;
    }

    /**
     * Reanota a exceção com o caminho do endpoint que estava sendo consultado.
     *
     * A montagem da URL vive no serviço (que sabe o formato de `/candidatura/...`);
     * o gateway só sabe executar o processo Node. Esta função costura as duas
     * visões: `TSE DivulgaCandContas respondeu 403` + ` em /candidatura/listar/...`
     * reproduz a mesma mensagem que a versão em `Http::get()` gerava.
     */
    public function inContext(string $path): self
    {
        return new self($this->errorCode, $this->status, $this->getMessage().' em '.$path, $this);
    }
}
