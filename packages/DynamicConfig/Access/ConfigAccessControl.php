<?php

declare(strict_types=1);

namespace iEXPackages\DynamicConfig\Access;

use iEXPackages\DynamicConfig\Schema\ConfigSchemaRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

/**
 * Класс ConfigAccessControl
 *
 * Отвечает за проверку прав доступа к настройкам:
 *  - canRead(key, user)
 *  - canWrite(key, user)
 *
 * Источники правил:
 *  - описания доступа в схеме (раздел 'access'):
 *      [
 *          'access' => [
 *              'permissions' => [
 *                  'read'  => ['view_settings'],
 *                  'write' => ['edit_settings'],
 *              ],
 *              'roles' => [
 *                  'read'  => ['admin','manager'],
 *                  'write' => ['admin'],
 *              ],
 *              'guests' => 'none'|'read',
 *          ],
 *      ]
 *
 *  - spatie/laravel-permission (методы hasAnyPermission(), hasAnyRole());
 *  - текущий пользователь через Auth::user(), если явно не передан.
 */
final class ConfigAccessControl
{
    public function __construct(
        private readonly ConfigSchemaRegistry $schemaRegistry
    ) {
    }

    /**
     * Можно ли читать настройку с заданным ключом.
     *
     * Логика:
     *  1) Если в схеме нет раздела 'access' → чтение разрешено всем (по умолчанию).
     *  2) Если есть 'access.guests' и пользователь = null:
     *     - 'read' → гость может читать;
     *     - 'none' (или отсутствует) → чтение запрещено.
     *  3) Для авторизованных:
     *     - проверяем permissions.read;
     *     - затем roles.read;
     *     - если явно read не определён, но задан write — считаем, что те же права и на чтение.
     *
     * @param string               $key
     * @param Authenticatable|null $user
     * @param string|null          $schemaProfile
     *
     * @return bool
     */
    public function canRead(
        string $key,
        ?Authenticatable $user = null,
        ?string $schemaProfile = null
    ): bool {
        $definition = $this->schemaRegistry->findDefinition($key, $schemaProfile);

        $user ??= Auth::user();

        // Нет схемы или нет access — по умолчанию разрешаем чтение всем (в т.ч. гостям)
        if ($definition === null || empty($definition['access'])) {
            return true;
        }

        $access = $definition['access'];

        // Гость
        if ($user === null) {
            $guestMode = $access['guests'] ?? 'none';

            return $guestMode === 'read';
        }

        // Сначала permissions.read
        if (!empty($access['permissions']['read'] ?? [])) {
            if ($this->userHasAnyPermission($user, (array) $access['permissions']['read'])) {
                return true;
            }
        }

        // Затем roles.read
        if (!empty($access['roles']['read'] ?? [])) {
            if ($this->userHasAnyRole($user, (array) $access['roles']['read'])) {
                return true;
            }
        }

        // Если явно не описали read, но есть правила на write — считаем их и для read
        if (empty($access['permissions']['read'] ?? [])
            && empty($access['roles']['read'] ?? [])
        ) {
            return $this->canWrite($key, $user, $schemaProfile);
        }

        return false;
    }

    /**
     * Можно ли записывать/изменять настройку.
     *
     * Логика:
     *  1) Если в схеме нет раздела 'access' — по умолчанию запись разрешена
     *     только авторизованным пользователям.
     *  2) Гость никогда не может записывать (даже если guests='read').
     *  3) Для авторизованных:
     *     - проверяем permissions.write;
     *     - затем roles.write.
     *
     * @param string               $key
     * @param Authenticatable|null $user
     * @param string|null          $schemaProfile
     *
     * @return bool
     */
    public function canWrite(
        string $key,
        ?Authenticatable $user = null,
        ?string $schemaProfile = null
    ): bool {
        $definition = $this->schemaRegistry->findDefinition($key, $schemaProfile);

        $user ??= Auth::user();

        // Нет схемы — по умолчанию запись разрешена всем авторизованным
        if ($definition === null || empty($definition['access'])) {
            return $user !== null;
        }

        $access = $definition['access'];

        // Гость никогда не может записывать
        if ($user === null) {
            return false;
        }

        // permissions.write
        if (!empty($access['permissions']['write'] ?? [])) {
            if ($this->userHasAnyPermission($user, (array) $access['permissions']['write'])) {
                return true;
            }
        }

        // roles.write
        if (!empty($access['roles']['write'] ?? [])) {
            if ($this->userHasAnyRole($user, (array) $access['roles']['write'])) {
                return true;
            }
        }

        return false;
    }

    // ---------------------------------------------------------
    // Вспомогательные методы для интеграции со spatie/permission
    // ---------------------------------------------------------

    /**
     * Проверка, есть ли у пользователя хоть одно из указанных прав.
     *
     * @param Authenticatable $user
     * @param string[]        $permissions
     */
    private function userHasAnyPermission(Authenticatable $user, array $permissions): bool
    {
        if (method_exists($user, 'hasAnyPermission')) {
            return $user->hasAnyPermission($permissions);
        }

        return false;
    }

    /**
     * Проверка, есть ли у пользователя хоть одна из указанных ролей.
     *
     * @param Authenticatable $user
     * @param string[]        $roles
     */
    private function userHasAnyRole(Authenticatable $user, array $roles): bool
    {
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($roles);
        }

        return false;
    }
}
