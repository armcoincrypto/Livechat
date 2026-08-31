<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use PHPUnit\Framework\TestCase;

final class CoursesBaseWriteGuardContractTest extends TestCase
{
    public function test_courses_update_strips_manual_rate_for_automatic_owners(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Http/Controllers/Administrator/Basic/DirectionExchangeController.php'
        );

        $this->assertStringContainsString("if (\$pageName === 'courses')", $src);
        $this->assertStringContainsString("unset(\$responseData['manual_rate_value'])", $src);
        $this->assertStringContainsString('поле BASE справочное', $src);
    }
}
