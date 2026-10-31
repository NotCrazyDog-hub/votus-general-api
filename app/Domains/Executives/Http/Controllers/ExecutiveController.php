<?php

namespace App\Domains\Executives\Http\Controllers;

use Illuminate\Http\Request;
use App\Domains\Executives\Services\ExecutiveService;
use App\Domains\Executives\Http\Resources\ExecutivesResource;
use App\Http\Controllers\Controller;

class ExecutiveController extends Controller
{
    public function __construct(
        protected ExecutiveService $service
    ) {}

    /** Lista executivos estaduais e restringe a consulta à UF informada, quando houver. */
    public function indexForGovernors(Request $request)
    {
        $executives = $this->service->listByLevel(
            'state',
            $request->state
        );
    
        return ExecutivesResource::collection($executives);
    }
    
    /** Busca o perfil de um governador pelo ID interno do executivo. */
    public function showGovernor(int $id)
    {
        return new ExecutivesResource(
            $this->service->findStateExecutive($id)
        );
    }
    
    /** Lista executivos do nível federal, incluindo o presidente vigente conforme o serviço. */
    public function indexForPresident()
    {
        $executives = $this->service->listByLevel('federal');
    
        return ExecutivesResource::collection($executives);
    }
    
    /** Busca o perfil de um executivo federal pelo ID interno. */
    public function showPresident(int $id)
    {
        return new ExecutivesResource(
            $this->service->findFederalExecutive($id)
        );
    }

}
