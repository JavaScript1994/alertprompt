<?php

declare(strict_types=1);

namespace App\Services\Branding;

use App\Models\Tenant;
use App\Services\AuditLogger;
use App\Services\Modules\TenantModules;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Marca del login de un cliente: logo, color y título. El logo es público
 * (se ve antes de iniciar sesión) pero vive en el disco privado y se sirve
 * por /api/branding/logo solo si el cliente tiene el módulo `branding`.
 */
class BrandingService
{
    private const DISK = 'local';

    public function __construct(
        private readonly TenantModules $modules,
        private readonly AuditLogger $audit,
    ) {}

    public function isActive(Tenant $tenant): bool
    {
        return ! $tenant->is_platform && $this->modules->isEnabled((string) config('branding.module'), $tenant);
    }

    /** @param  array{brand_color?: ?string, brand_title?: ?string}  $data */
    public function update(Tenant $tenant, array $data): Tenant
    {
        $tenant->forceFill([
            'brand_color' => isset($data['brand_color']) ? Str::lower($data['brand_color']) : null,
            'brand_title' => $data['brand_title'] ?? null,
        ]);
        $changed = array_keys($tenant->getDirty());
        $tenant->save();

        if ($changed !== []) {
            $this->audit->record('branding.updated', $tenant->id, $tenant, ['fields' => $changed]);
        }

        return $tenant;
    }

    public function setLogo(Tenant $tenant, UploadedFile $logo): Tenant
    {
        $previous = $tenant->brand_logo_path;
        $path = $logo->storeAs("brand-logos/{$tenant->id}", Str::uuid()->toString().'.'.$logo->extension(), self::DISK);

        if ($path === false) {
            throw ValidationException::withMessages(['logo' => 'No se pudo guardar el logo.']);
        }

        $tenant->forceFill(['brand_logo_path' => $path])->save();
        if ($previous !== null) {
            Storage::disk(self::DISK)->delete($previous);
        }

        $this->audit->record('branding.logo_updated', $tenant->id, $tenant);

        return $tenant;
    }

    public function removeLogo(Tenant $tenant): Tenant
    {
        if ($tenant->brand_logo_path !== null) {
            Storage::disk(self::DISK)->delete($tenant->brand_logo_path);
            $tenant->forceFill(['brand_logo_path' => null])->save();
            $this->audit->record('branding.logo_removed', $tenant->id, $tenant);
        }

        return $tenant;
    }

    public function logoResponse(Tenant $tenant): StreamedResponse
    {
        $disk = Storage::disk(self::DISK);
        abort_if(! $this->isActive($tenant) || $tenant->brand_logo_path === null || ! $disk->exists($tenant->brand_logo_path), 404);

        return $disk->response($tenant->brand_logo_path, null, [
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function logoUrl(Tenant $tenant, bool $public): ?string
    {
        if ($tenant->brand_logo_path === null) {
            return null;
        }

        $version = substr(sha1($tenant->brand_logo_path), 0, 8);

        return ($public ? '/api/branding/logo' : '/api/settings/branding/logo')."?v={$version}";
    }
}
