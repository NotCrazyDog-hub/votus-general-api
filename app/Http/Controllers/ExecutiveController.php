<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\ExecutiveService;
use App\Http\Resources\ExecutivesResource;

class ExecutiveController extends Controller
{
    public function __construct(
        protected ExecutiveService $service
    ) {}

    public function indexForGovernors(Request $request)
    {
        $executives = $this->service->listByLevel(
            'state',
            $request->state
        );
    
        return ExecutivesResource::collection($executives);
    }
    
    public function showGovernor(int $id)
    {
        return new ExecutivesResource(
            $this->service->findStateExecutive($id)
        );
    }
    
    public function indexForPresident()
    {
        $executives = $this->service->listByLevel('federal');
    
        return ExecutivesResource::collection($executives);
    }
    
    public function showPresident(int $id)
    {
        return new ExecutivesResource(
            $this->service->findFederalExecutive($id)
        );
    }

}
