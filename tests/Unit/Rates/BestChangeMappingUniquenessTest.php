<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Http\Controllers\Administrator\Crypto\BestChangeController;
use App\Models\BestChangeDirection;
use App\Services\Rates\BestChangeMappingVerifier;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class BestChangeMappingUniquenessTest extends TestCase
{
    /** @var list<string> */
    private const USDT_NETWORKS = [
        'USDTTRC20',
        'USDTERC20',
        'USDTBEP20',
        'USDTPOLYGON',
        'USDTSOL',
        'USDTTON',
    ];

    private const STORAGE_APP = '/var/www/app_exswapin_usr/data/www/exswaping-backend-shared/storage/app';

    public function test_usdt_networks_map_to_distinct_bestchange_ids(): void
    {
        $v = BestChangeMappingVerifier::fromStorageApp(self::STORAGE_APP);
        $ids = [];
        foreach (self::USDT_NETWORKS as $code) {
            $row = $v->verifyCode($code);
            $status = strtoupper((string) ($row['status'] ?? ''));
            $this->assertSame('VERIFIED', $status, $code . ' must be a unique catalog match');
            $id = (int) ($row['bestchange_currency_id'] ?? 0);
            $this->assertGreaterThan(0, $id, $code);
            $this->assertArrayNotHasKey($id, $ids, $code . ' collapsed onto ' . ($ids[$id] ?? ''));
            $ids[$id] = $code;
        }
        $this->assertCount(6, $ids);
    }

    public function test_payeer_has_no_bestchange_market(): void
    {
        $catalog = json_decode((string) file_get_contents(self::STORAGE_APP . '/bestchange/currencies.json'), true);
        $this->assertIsArray($catalog);
        $haystack = strtoupper(json_encode($catalog, JSON_UNESCAPED_UNICODE) ?: '');
        foreach (['[PRUSD]', '[PREUR]', '[PRRUB]'] as $code) {
            $this->assertStringNotContainsString($code, $haystack, $code . ' must not exist in live catalog');
        }
    }

    public function test_admin_store_uses_single_row_per_direction(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3) . '/app/Http/Controllers/Administrator/Crypto/BestChangeController.php'
        );
        $this->assertStringContainsString("firstOrCreate([", $src);
        $this->assertStringContainsString("'id_direction_exchange'", $src);
        $this->assertStringNotContainsString('BestChangeDirection::query()->create', $src);

        $ref = new ReflectionClass(BestChangeController::class);
        $this->assertTrue($ref->hasMethod('store'));
        $this->assertTrue($ref->hasMethod('update'));
    }

    public function test_fillable_does_not_include_direction_profit_or_course_value(): void
    {
        $fillable = (new BestChangeDirection())->getFillable();
        $this->assertNotContains('profit', $fillable);
        $this->assertNotContains('course_value', $fillable);
        $this->assertContains('id_direction_exchange', $fillable);
        $this->assertContains('id_currency_in', $fillable);
        $this->assertContains('position_num', $fillable);
        $this->assertContains('status', $fillable);
    }
}
