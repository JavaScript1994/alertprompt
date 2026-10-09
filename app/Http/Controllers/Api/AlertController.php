<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AlertResource;
use App\Models\Alert;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Alertas abiertas del propio tenant (se muestran en su dashboard). */
class AlertController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return AlertResource::collection(
            Alert::query()->with('campaign')->whereNull('resolved_at')->latest('updated_at')->limit(20)->get(),
        );
    }
}
