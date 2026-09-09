<?php

namespace App\Http\Controllers;

use App\Models\CourseOffering;
use Illuminate\View\View;

class CourseOfferingController extends Controller
{
    public function show(
        CourseOffering $courseOffering
    ): View {
        $courseOffering->load([
            'campus.university.admissionMethods' =>
                fn ($query) => $query
                    ->where('active', true)
                    ->orderBy('name'),

            'curricula.subjects',
        ]);

        return view('course-offerings.show', [
            'offering' => $courseOffering,
        ]);
    }
}