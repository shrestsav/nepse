<?php

namespace App\Http\Controllers\Nepse;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use Inertia\Inertia;
use Inertia\Response;

class SectorController extends Controller
{
    public function index(): Response
    {
        $sectors = Sector::query()
            ->withCount('stocks')
            ->orderBy('name')
            ->get()
            ->map(fn (Sector $sector): array => [
                'id' => $sector->id,
                'name' => $sector->name,
                'stockCount' => $sector->stocks_count,
            ])
            ->all();

        return Inertia::render('nepse/Sectors', [
            'sectors' => $sectors,
        ]);
    }
}
