<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\LegislatorService;
use App\Models\Legislator;
use App\Http\Resources\LegislatorsResource;

class LegislatorController extends Controller
{
    public function __construct(protected LegislatorService $service) {}
    
    public function indexForDeputies(Request $request)
    {
        $deputies = $this->service->listByChamber('lower_house', $request->state);
        return LegislatorsResource::collection($deputies);
    }

    public function indexForSenators(Request $request)
    {
        $senators = $this->service->listByChamber('senate', $request->state);
        return LegislatorsResource::collection($senators);
    }

    public function indexForStateDeputies(Request $request) 
    { 
        $deputies = $this->service->listByChamber( 'state_house', $request->state ); 
        return LegislatorsResource::collection($deputies); 
    }

    public function showDeputy(int $external_id)
    {
        return response()->json(
            $this->service->findByChamber($external_id, 'lower_house')
        );
    }

    public function showSenator(int $external_id)
    {
        return response()->json(
            $this->service->findByChamber($external_id, 'senate')
        );
    }

    public function showStateDeputy(string $source_slug) 
    { 
        return response()->json( 
            $this->service->findByChamberSlug( $source_slug, 'state_house' ) 
        ); 
    }
}