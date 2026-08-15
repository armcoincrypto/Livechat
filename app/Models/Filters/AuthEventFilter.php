<?php
declare(strict_types=1);

namespace App\Models\Filters;

use EloquentFilter\ModelFilter;

final class AuthEventFilter extends ModelFilter
{
    /**
     * Поиск по ID пользователя.
     * Поддерживает параметры:
     * - user_id (рекомендуется)
     * - id_user (для совместимости со старым стилем)
     */
    public function userId(mixed $id): self
    {
        if ($id === null || $id === '') {
            return $this;
        }

        return $this->where('user_id', '=', $id);
    }

    /**
     * Совместимость со старым названием параметра id_user.
     */
    public function idUser(mixed $id): self
    {
        return $this->userId($id);
    }

    /**
     * Фильтр по IP адресу.
     * Параметр: ip
     */
    public function ip(mixed $value): self
    {
        if ($value === null || $value === '') {
            return $this;
        }

        return $this->where('ip', '=', $value);
    }

    /**
     * Фильтр по дате создания (От).
     * Параметр: from_created_at
     */
    public function fromCreatedAt(mixed $value): self
    {
        if ($value === null || $value === '') {
            return $this;
        }

        return $this->where('created_at', '>=', $value);
    }

    /**
     * Фильтр по дате создания (До).
     * Параметр: to_created_at
     */
    public function toCreatedAt(mixed $value): self
    {
        if ($value === null || $value === '') {
            return $this;
        }

        return $this->where('created_at', '<=', $value);
    }
}
