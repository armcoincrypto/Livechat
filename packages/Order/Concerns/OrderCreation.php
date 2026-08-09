<?php

namespace iEXPackages\Order\Concerns;

use Illuminate\Support\Facades\Log;

trait OrderCreation
{
    protected function prepareOrderData(array $responseFee): array
    {

        $incomeDecimal = $this->getInCurrency()->number_format;
        $outcomeDecimal = $this->getOutCurrency()->number_format;

        [$referralHash, $referralLinkId] = $this->resolveReferralData();


        $direction = $this->directionId;
        $currency  = $direction?->currency1;

        // Do not reference undefined $requisites — request-payment is determined by
        // direction/currency method_request_payment flags only.
        $is_request_payment_type = (int) (
            (int)($direction?->method_request_payment ?? 0) === 1
            || (int)($currency?->method_request_payment ?? 0) === 1
        );


        $promoId = (int) ($responseFee['id_promo_code'] ?? 0);


        return [
            'type_rate' => $this->resolveOrderType(),
            'is_type_rate' => $this->directionId->is_type_rate,
            'public_id' => generateUniqueNumericId(),
            'id_user' => $this->authInfo->id,
            'id_direction_exchange' => $this->directionId->id,
            'is_new_user' => isset($this->options['email']) ? 1 : 0,
            'id_payment_requisites' => 0,

            'from_shot' => security_xss($this->getInComeAccount()),
            'to_shot' => security_xss($this->getOutComeAccount()),
            'markup1' => $this->directionId->add_course1 ?? null,
            'markup2' => $this->directionId->add_course2 ?? null,
            'email' => $this->getClientEmail(),
            'ip' => $this->clientIp(),
            'unique_security_code' => generate_security_code(51),
            'telegram_id' => $this->options['telegram_id'] ?? null,
            // Промокод: код сохраняем только если промокод реально применился
            'promo_code_code' => $promoId > 0
                ? ($responseFee['promo_code_code'] ?? ($this->options['promo_code'] ?? null))
                : null,
            'referral_hash' => $referralHash,
            'id_referral_link' => $referralLinkId,
            'method_request_payment' => (int) $is_request_payment_type,
            'is_request_payment_type' => (int) $is_request_payment_type,

            'course_float' => $responseFee['course_float'],
            'course_display' => $responseFee['course_display'],
            'course_float_fixed' => $responseFee['course_float'],
            'course_display_fixed' => $responseFee['course_display'],
            'give_price' => $this->formatAmount($responseFee['give_price'], $incomeDecimal),
            'give_price_default' => $this->formatAmount($responseFee['give_price_default'], $incomeDecimal),
            'give_price_with_comm' => $this->formatAmount($responseFee['give_price_with_comm'], $incomeDecimal),
            'give_price_with_comm_pay' => $this->formatAmount($responseFee['give_price_with_comm_pay'], $incomeDecimal),
            'give_price_fee_comm' => $this->formatAmount($responseFee['give_price_fee_comm'], $incomeDecimal),
            'give_price_fee_pay' => $this->formatAmount($responseFee['give_price_fee_pay'], $incomeDecimal),

            'receiving_price' => $this->formatAmount($responseFee['receiving_price'], $outcomeDecimal),
            'receiving_price_default' => $this->formatAmount($responseFee['receiving_price_default'], $outcomeDecimal),
            'receiving_price_with_comm' => $this->formatAmount($responseFee['receiving_price_with_comm'], $outcomeDecimal),
            'receiving_price_with_comm_pay' => $this->formatAmount($responseFee['receiving_price_with_comm_pay'], $outcomeDecimal),
            'receiving_price_fee_comm' => $this->formatAmount($responseFee['receiving_price_fee_comm'], $outcomeDecimal),
            'receiving_price_fee_pay' => $this->formatAmount($responseFee['receiving_price_fee_pay'], $outcomeDecimal),
            'receiving_price_with_promocode' => $this->formatAmount($responseFee['receiving_price_with_promocode'] ?? 0, $outcomeDecimal),

            'receiving_price_with_user_discount' => $this->formatAmount($responseFee['receiving_price_with_user_discount'], $outcomeDecimal),
            'receiving_price_user_discount' => $this->formatAmount($responseFee['receiving_price_user_discount'], $outcomeDecimal),
            'user_discount' => $responseFee['user_discount'],

            // PROMO (ВАЖНО: receiving_price_with_promocode — это BONUS)
            'id_promo_code' => $promoId,
            'promo_code_discount_type' => $promoId > 0 ? ($responseFee['promo_code_discount_type'] ?? null) : null, // percent|fixed
            'promo_code_value' => $promoId > 0 ? ($responseFee['promo_code_value'] ?? null) : null, // discount_value
        ];
    }

    protected function formatAmount($amount, int $decimal): string
    {
        return iex_number_format($amount, $decimal);
    }
}
