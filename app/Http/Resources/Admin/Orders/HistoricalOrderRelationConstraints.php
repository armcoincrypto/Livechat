<?php

declare(strict_types=1);

namespace App\Http\Resources\Admin\Orders;

/**
 * Nested eager-load closures that preserve HISTORICAL_WITH_TRASHED.
 * A constrained eager load without withTrashed() re-applies SoftDeletes and
 * nulls retired currencies/payments, which then 500s unsafe serializers.
 */
final class HistoricalOrderRelationConstraints
{
    /**
     * @return array<string, callable>
     */
    public static function forLiveOrders(): array
    {
        return [
            'direction_exchange' => static function ($q) {
                $q->withTrashed()->select('id', 'id_currency1', 'id_currency2', 'tech_name', 'deleted_at');
            },
            'direction_exchange.currency1' => static function ($q) {
                $q->withTrashed()->select('id', 'id_code_currency', 'id_payment', 'number_format', 'deleted_at');
            },
            'direction_exchange.currency1.code_currency' => static function ($q) {
                $q->select('id', 'name');
            },
            'direction_exchange.currency1.payment' => static function ($q) {
                $q->withTrashed()->select('id', 'name', 'deleted_at');
            },
            'direction_exchange.currency2' => static function ($q) {
                $q->withTrashed()->select('id', 'id_code_currency', 'id_payment', 'number_format', 'deleted_at');
            },
            'direction_exchange.currency2.code_currency' => static function ($q) {
                $q->select('id', 'name');
            },
            'direction_exchange.currency2.payment' => static function ($q) {
                $q->withTrashed()->select('id', 'name', 'deleted_at');
            },
            'task_info' => static function ($q) {
                $q->select('id', 'is_freeze_scam');
            },
            'task_messages' => static function ($q) {
                $q->select('id', 'is_view')->where('is_view', '=', 0);
            },
            'task_operators' => static function ($q) {
                $q->select('id', 'id_user', 'id_task');
            },
            'task_operators.user' => static function ($q) {
                $q->select('id', 'name');
            },
        ];
    }

    /**
     * @return array<string, callable>
     */
    public static function forOrdersList(): array
    {
        return [
            'direction_exchange' => static function ($q) {
                $q->withTrashed()->select('id', 'id_currency1', 'id_currency2', 'tech_name', 'deleted_at');
            },
            'direction_exchange.currency1' => static function ($q) {
                $q->withTrashed()->select('id', 'id_code_currency', 'id_payment', 'number_format', 'deleted_at');
            },
            'direction_exchange.currency1.code_currency' => static function ($q) {
                $q->select('id', 'name');
            },
            'direction_exchange.currency1.payment' => static function ($q) {
                $q->withTrashed()->select('id', 'name', 'logo', 'deleted_at');
            },
            'direction_exchange.currency2' => static function ($q) {
                $q->withTrashed()->select('id', 'id_code_currency', 'id_payment', 'id_aml_service', 'number_format', 'deleted_at');
            },
            'direction_exchange.currency2.code_currency' => static function ($q) {
                $q->select('id', 'name');
            },
            'direction_exchange.currency2.payment' => static function ($q) {
                $q->withTrashed()->select('id', 'name', 'logo', 'deleted_at');
            },
            'task_operators' => static function ($q) {
                $q->select('id', 'id_user', 'id_task', 'created_at');
            },
            'task_operators.user' => static function ($q) {
                $q->select('id', 'name');
            },
        ];
    }
}
