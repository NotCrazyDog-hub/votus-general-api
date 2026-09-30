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
        $governors = $this->service->listByLevel(
            'state',
            $request->state
        );

        return ExecutivesResource::collection($governors);
    }

    public function showGovernor(int $id)
    {
        $data = $this->service->findGovernorWithVice($id);
    
        return response()->json([
            'governor' => new ExecutivesResource($data['governor']),
            'vice_governor' => $data['vice_governor']
                ? new ExecutivesResource($data['vice_governor'])
                : null,
        ]);
    }
    
    public function indexForPresident()
    {
        $president = $this->service->listByLevel('federal');

        return ExecutivesResource::collection($president);
    }

    public function showPresident(int $id)
    {
        $data = $this->service->findPresidentWithVice($id);
    
        return response()->json([
            'president' => new ExecutivesResource($data['president']),
            'vice_president' => $data['vice_president']
                ? new ExecutivesResource($data['vice_president'])
                : null,
        ]);
    }
}
