<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\PermissionSectionResource;
use App\Services\Authorization\PermissionRegistry;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PermissionTreeController extends Controller
{
    public function __invoke(PermissionRegistry $registry): AnonymousResourceCollection
    {
        return PermissionSectionResource::collection($registry->tree());
    }
}
