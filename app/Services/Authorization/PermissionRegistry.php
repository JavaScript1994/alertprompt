<?php

declare(strict_types=1);

namespace App\Services\Authorization;

use App\Enums\RoleScope;
use InvalidArgumentException;

/**
 * Lee el árbol de config/permissions.php. Es la única fuente de verdad de
 * qué permisos existen; la tabla `permissions` es un espejo sincronizado.
 */
class PermissionRegistry
{
    /** @param  array<string, array{label: string, scope: string, modules: array<string, array{label: string, actions: array<string, string>}>}>  $sections */
    public function __construct(private readonly array $sections) {}

    public static function fromConfig(): self
    {
        return new self(config('permissions.sections', []));
    }

    /** @return list<string> */
    public function all(): array
    {
        return $this->collect(fn () => true);
    }

    /** @return list<string> */
    public function forScope(RoleScope $scope): array
    {
        return $this->collect(fn (array $section) => $section['scope'] === $scope->value);
    }

    /**
     * Resuelve la notación de config/permissions.php system_roles:
     * '*', 'scope:{scope}' o una lista explícita.
     *
     * @param  string|list<string>  $spec
     * @return list<string>
     */
    public function resolve(string|array $spec): array
    {
        if ($spec === '*') {
            return $this->all();
        }

        if (is_string($spec) && str_starts_with($spec, 'scope:')) {
            return $this->forScope(RoleScope::from(substr($spec, 6)));
        }

        $spec = (array) $spec;
        $unknown = array_diff($spec, $this->all());

        if ($unknown !== []) {
            throw new InvalidArgumentException('Permisos inexistentes en config/permissions.php: '.implode(', ', $unknown));
        }

        return array_values($spec);
    }

    /**
     * Árbol sección → módulo → acción, listo para pintar con checkboxes.
     *
     * @return list<array{key: string, label: string, scope: string, modules: list<array{key: string, label: string, permissions: list<array{name: string, label: string}>}>}>
     */
    public function tree(): array
    {
        $tree = [];

        foreach ($this->sections as $sectionKey => $section) {
            $modules = [];

            foreach ($section['modules'] as $moduleKey => $module) {
                $permissions = [];

                foreach ($module['actions'] as $action => $label) {
                    $permissions[] = ['name' => "{$moduleKey}.{$action}", 'label' => $label];
                }

                $modules[] = ['key' => $moduleKey, 'label' => $module['label'], 'permissions' => $permissions];
            }

            $tree[] = [
                'key' => $sectionKey,
                'label' => $section['label'],
                'scope' => $section['scope'],
                'modules' => $modules,
            ];
        }

        return $tree;
    }

    /**
     * @param  callable(array): bool  $sectionFilter
     * @return list<string>
     */
    private function collect(callable $sectionFilter): array
    {
        $names = [];

        foreach ($this->sections as $section) {
            if (! $sectionFilter($section)) {
                continue;
            }

            foreach ($section['modules'] as $moduleKey => $module) {
                foreach (array_keys($module['actions']) as $action) {
                    $names[] = "{$moduleKey}.{$action}";
                }
            }
        }

        return $names;
    }
}
