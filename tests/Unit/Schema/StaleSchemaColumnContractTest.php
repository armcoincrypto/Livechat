<?php

declare(strict_types=1);

namespace Tests\Unit\Schema;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 2026-09-13 SQLSTATE 42S22 signatures were explicit SELECTs of columns that
 * do not exist on live schema (operator/debug probes). Production Eloquent
 * must not reintroduce those stale columns.
 *
 * currencies has tech_name / tech_currency_name, not name.
 * payments has name (JSON) but no status.
 * currency_fields uses field_type / type_field, not type.
 * extra_fields has scope, not type.
 * merchant_flow_events has task_id, not id_task.
 */
final class StaleSchemaColumnContractTest extends TestCase
{
    public function test_live_schema_does_not_have_probe_columns(): void
    {
        $this->assertFalse($this->hasColumn('currencies', 'name'));
        $this->assertTrue($this->hasColumn('currencies', 'tech_name'));
        $this->assertTrue($this->hasColumn('currencies', 'designation_xml'));
        $this->assertTrue($this->hasColumn('currencies', 'status'));

        $this->assertTrue($this->hasColumn('payments', 'name'));
        $this->assertFalse($this->hasColumn('payments', 'status'));

        $this->assertTrue($this->hasColumn('currency_fields', 'name'));
        $this->assertTrue(
            $this->hasColumn('currency_fields', 'field_type')
            || $this->hasColumn('currency_fields', 'type_field')
        );
        $this->assertFalse($this->hasColumn('currency_fields', 'type'));
        $this->assertFalse($this->hasColumn('currency_fields', 'alias'));

        $this->assertTrue($this->hasColumn('extra_fields', 'scope'));
        $this->assertFalse($this->hasColumn('extra_fields', 'type'));

        $this->assertTrue($this->hasColumn('merchant_flow_events', 'task_id'));
        $this->assertFalse($this->hasColumn('merchant_flow_events', 'id_task'));
    }

    public function test_application_php_does_not_select_stale_currency_name(): void
    {
        $hits = $this->scanPhp([
            "select(['id', 'designation_xml', 'name', 'status']",
            'select(`id`, `designation_xml`, `name`, `status`)',
            "or `name` like %Polygon%",
            "'name' like %USDT%",
        ]);
        $this->assertSame([], $hits, 'stale currencies.name select reintroduced');
    }

    public function test_application_php_does_not_select_payments_status(): void
    {
        $hits = $this->scanPhp([
            "select(['id', 'name', 'logo', 'status']",
            'select(`id`, `name`, `logo`, `status`) from `payments`',
        ]);
        $this->assertSame([], $hits);
    }

    public function test_support_ai_currency_select_uses_tech_name(): void
    {
        $src = (string) file_get_contents(
            dirname(__DIR__, 3).'/packages/SupportChat/Services/SupportAiDirectionContextService.php'
        );
        $this->assertStringContainsString("'tech_name'", $src);
        $this->assertStringNotContainsString("'designation_xml', 'name', 'status'", $src);
    }

    private function hasColumn(string $table, string $column): bool
    {
        $rows = DB::select('SHOW COLUMNS FROM `'.$table.'`');
        foreach ($rows as $row) {
            if (($row->Field ?? '') === $column) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $needles
     * @return list<string>
     */
    private function scanPhp(array $needles): array
    {
        $root = dirname(__DIR__, 3);
        $hits = [];
        foreach (['app', 'packages'] as $dir) {
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root.'/'.$dir, \FilesystemIterator::SKIP_DOTS)
            );
            foreach ($it as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                $path = $file->getPathname();
                if (str_contains($path, '/vendor/')) {
                    continue;
                }
                $src = (string) file_get_contents($path);
                foreach ($needles as $needle) {
                    if (str_contains($src, $needle)) {
                        $hits[] = $path.': '.$needle;
                    }
                }
            }
        }

        return $hits;
    }
}
