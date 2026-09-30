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
        return new ExecutivesResource(
            $this->service->findByOffice(
                $id,
                'governor'
            )
        );
    }

    public function indexForPresident()
    {
        $president = $this->service->listByLevel('federal');

        return ExecutivesResource::collection($president);
    }

    public function showPresident(int $id)
    {
        return new ExecutivesResource(
            $this->service->findByOffice(
                $id,
                'president'
            )
        );
    }
}