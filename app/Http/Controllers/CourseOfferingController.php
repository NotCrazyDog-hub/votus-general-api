<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseOfferingResource;
use App\Models\CourseOffering;

class CourseOfferingController extends Controller
{
    public function show(CourseOffering $courseOffering): CourseOfferingResource
    {
        $courseOffering->load([
            'campus.university.admissionMethods' =>
                fn ($query) => $query
                    ->where('active', true)
                    ->orderBy('name'),

            'curricula.subjects',
        ]);

        return new CourseOfferingResource($courseOffering);
    }
}