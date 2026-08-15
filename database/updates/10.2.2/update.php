<?php


return function () {
    $decimalPlaces = (int) iEXSetting('readmore_lines_threshold') ?: 4;
    iEXSetting(['readmore_lines_threshold' => $decimalPlaces]);
};
