<?php

namespace App\Domains\Legislatures\Http\Controllers;

use Illuminate\Http\Request;
use App\Domains\Legislatures\Services\LegislatorCatalogService;
use App\Domains\Legislatures\Models\Legislator;
use App\Domains\Legislatures\Http\Resources\LegislatorsResource;
use App\Http\Controllers\Controller;

class LegislatorController extends Controller
{
    public function __construct(protected LegislatorCatalogService $service) {}
    
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