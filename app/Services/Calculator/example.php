<?php

// Базовая сумма 1000
use App\Services\Calculator\CalculatorMathService;

$calculator1 = new CalculatorMathService(100);

// Простое добавление 10%
echo $calculator1->calculate('+10%'); // 110

// Простое вычитание числа
echo $calculator1->calculate('-20'); // 90




// Исходная сумма 100
$calculator2 = new CalculatorMathService(100);

// +10%
echo $calculator2->calculate('+10%'); // 110

// -5%
echo $calculator2->calculate('-5%'); // 104.5

// +50%
echo $calculator2->calculate('+50%'); // 165


// Исходная сумма 500
$calculator3 = new CalculatorMathService(500);

// +150
echo $calculator3->calculate('+150'); // 650

// -100
echo $calculator3->calculate('-100'); // 550



$calculator4 = new CalculatorMathService(1000);

// Установили динамически autoSubtractNumbers=true (числа без знака считаются как минус)
echo $calculator4->calculate('50', ['autoSubtractNumbers' => true]); // 90 (вычитается 10)

// Отключаем арифметику (числа всегда вычитаются)
echo $calculator4->calculate('20', ['allowArithmetic' => false]); // 80




// Базовая сумма 1000
$calculator = new CalculatorMathService(1000);

// Добавляем 5% (1050)
echo $calculator->calculate('+5%'); // 1050

// Отнимаем фиксированную сумму 50
echo $calculator->calculate('-50'); // 950

// Сложение числа 10.5 (с динамической конфигурацией: 2 знака после запятой)
echo $calculator->calculate('+10.256', ['decimalPlaces' => 2]); // 1020.26

// Вычитаем 3% (автоматически в минус, опция autoSubtractPercentage включена)
echo $calculator->calculate('3%', ['autoSubtractPercentage' => true]); // 989.6522

// Попытка деления на 0 (не будет ошибки!)
echo $calculator->calculate('/0'); // 1020.26 (ничего не изменится)

// Сбрасываем на новую сумму 500
$calculator->resetSumma(500);

// Умножаем на 2
echo $calculator->calculate('*2'); // 1000
