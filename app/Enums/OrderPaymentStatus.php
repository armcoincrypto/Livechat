<?php

namespace App\Enums;

enum OrderPaymentStatus: int
{
    case CREATED_STATUS = 0;
    case WAITING_TRANSACTION = 1;        // ожидает транзакцию
    case REGISTERING_TRANSACTION = 2;    // идёт регистрация транзакции
    case TRANSACTION_REGISTERED = 3;     // транзакция зарегистрирована

    case AML_CHECK_IN_PROGRESS = 4;      // идет проверка AML
    case AML_KYC_REQUIRED = 5;           // требуется AML/KYC

    case TRANSACTION_FOUND = 6;          // транзакция найдена
    case TRANSACTION_PENDING_CONFIRMATION = 7;   // транзакция найдена, ожидается подтверждение от сети
    case CONFIRMATIONS_DONE = 8;         // подтверждения завершены
    case PAYMENT_SUCCESS = 9;            // платёж успешен, ожидает выплаты
    case TRANSACTION_FAILED = 10;         // платёж отменён

    case TRANSACTION_BLOCKED_INSUFFICIENT_AMOUNT = 11;

    case TRANSACTION_BLOCKED_WRONG_CURRENCY = 12;
}
