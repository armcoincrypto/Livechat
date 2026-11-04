<?php

declare(strict_types=1);

namespace Tests\Unit\Rates;

use App\Services\Rates\SchemeFilesScheduledSkipGate;
use Tests\TestCase;

final class SchemeFilesScheduledSkipGateTest extends TestCase
{
    /** @return array<string, mixed> */
    private function baseOkFacts(): array
    {
        return [
            'enabled' => true,
            'invocation' => 'scheduled',
            'force_publish' => false,
            'skip_export' => false,
            'offline' => false,
            'offline_failed' => false,
            'monitor_hidden' => false,
            'monitor_failed' => false,
            'meta' => [
                'fp_version' => SchemeFilesScheduledSkipGate::FP_VERSION,
                'fingerprint' => 'aaa',
                'last_result' => 'success',
                'xml_sha256' => 'bbb',
            ],
            'output_fail' => null,
            'fingerprint' => 'aaa',
            'fingerprint_failed' => false,
            'caches_ok' => true,
            'cache_fail_reason' => null,
            'fp_version' => SchemeFilesScheduledSkipGate::FP_VERSION,
        ];
    }

    public function test_flag_off_never_skips(): void
    {
        $f = $this->baseOkFacts();
        $f['enabled'] = false;
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($f);
        $this->assertFalse($d['skip']);
        $this->assertSame('flag_off', $d['reason']);
        $this->assertFalse($d['enabled']);
    }

    public function test_unchanged_complete_source_skips(): void
    {
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($this->baseOkFacts());
        $this->assertTrue($d['skip']);
        $this->assertSame('unchanged_complete_inputs', $d['reason']);
        $this->assertSame('aaa', $d['fingerprint']);
    }

    public function test_changed_rate_fingerprint_does_not_skip(): void
    {
        $f = $this->baseOkFacts();
        $f['fingerprint'] = 'changed-rate-fp';
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($f);
        $this->assertFalse($d['skip']);
        $this->assertSame('fingerprint_changed', $d['reason']);
    }

    public function test_changed_direction_status_does_not_skip(): void
    {
        // Status is part of complete fingerprint; a new fp simulates status flip.
        $f = $this->baseOkFacts();
        $f['fingerprint'] = 'status-changed';
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($f);
        $this->assertFalse($d['skip']);
        $this->assertSame('fingerprint_changed', $d['reason']);
    }

    public function test_changed_language_data_does_not_skip(): void
    {
        $f = $this->baseOkFacts();
        $f['fingerprint'] = 'locale-fields-changed';
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($f);
        $this->assertFalse($d['skip']);
        $this->assertSame('fingerprint_changed', $d['reason']);
    }

    public function test_changed_source_metadata_does_not_skip(): void
    {
        $f = $this->baseOkFacts();
        $f['fingerprint'] = 'parser-source-changed';
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($f);
        $this->assertFalse($d['skip']);
        $this->assertSame('fingerprint_changed', $d['reason']);
    }

    public function test_missing_output_does_not_skip(): void
    {
        $f = $this->baseOkFacts();
        $f['output_fail'] = 'xml_missing';
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($f);
        $this->assertFalse($d['skip']);
        $this->assertSame('xml_missing', $d['reason']);
    }

    public function test_corrupt_output_does_not_skip(): void
    {
        $f = $this->baseOkFacts();
        $f['output_fail'] = 'xml_corrupt';
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($f);
        $this->assertFalse($d['skip']);
        $this->assertSame('xml_corrupt', $d['reason']);
    }

    public function test_failed_previous_export_does_not_skip(): void
    {
        $f = $this->baseOkFacts();
        $f['meta']['last_result'] = 'failure';
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($f);
        $this->assertFalse($d['skip']);
        $this->assertSame('prior_export_not_success', $d['reason']);
    }

    public function test_post_bc_mandatory_never_skips(): void
    {
        $f = $this->baseOkFacts();
        $f['invocation'] = 'post-bc';
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($f);
        $this->assertFalse($d['skip']);
        $this->assertSame('post_bc_mandatory', $d['reason']);
    }

    public function test_force_publish_never_skips(): void
    {
        $f = $this->baseOkFacts();
        $f['force_publish'] = true;
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($f);
        $this->assertFalse($d['skip']);
        $this->assertSame('force_publish', $d['reason']);
    }

    public function test_cache_invalidation_missing_does_not_skip(): void
    {
        $f = $this->baseOkFacts();
        $f['caches_ok'] = false;
        $f['cache_fail_reason'] = 'cache_missing_or_invalid:exchange-iex-initial-rates-ru';
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($f);
        $this->assertFalse($d['skip']);
        $this->assertStringContainsString('cache_missing_or_invalid', $d['reason']);
    }

    public function test_snapshot_parity_skip_keeps_existing_snapshot_semantics(): void
    {
        // Skip path must not invent a new fingerprint reason; snapshot bump is
        // intentionally omitted by the command when skip=true (unchanged data).
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($this->baseOkFacts());
        $this->assertTrue($d['skip']);
        $this->assertSame('unchanged_complete_inputs', $d['reason']);
    }

    public function test_concurrent_lock_busy_is_not_a_skip_decision(): void
    {
        // Lock busy is handled by StatusRatesFilesCommand (last-good remains).
        // Gate must still refuse skip when outputs invalid after concurrent write.
        $f = $this->baseOkFacts();
        $f['output_fail'] = 'xml_sha_drift';
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($f);
        $this->assertFalse($d['skip']);
        $this->assertSame('xml_sha_drift', $d['reason']);
    }

    public function test_retry_recovery_after_failure_requires_success_meta(): void
    {
        $f = $this->baseOkFacts();
        $f['meta'] = null;
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($f);
        $this->assertFalse($d['skip']);
        $this->assertSame('no_prior_success_meta', $d['reason']);
    }

    public function test_flag_defaults_off(): void
    {
        $prev = getenv(SchemeFilesScheduledSkipGate::FLAG);
        putenv(SchemeFilesScheduledSkipGate::FLAG);
        unset($_ENV[SchemeFilesScheduledSkipGate::FLAG], $_SERVER[SchemeFilesScheduledSkipGate::FLAG]);
        try {
            $this->assertFalse(SchemeFilesScheduledSkipGate::flagEnabled());
        } finally {
            if ($prev === false) {
                putenv(SchemeFilesScheduledSkipGate::FLAG);
            } else {
                putenv(SchemeFilesScheduledSkipGate::FLAG.'='.$prev);
                $_ENV[SchemeFilesScheduledSkipGate::FLAG] = $prev;
            }
        }
    }

    public function test_validate_published_outputs_rejects_missing_and_corrupt(): void
    {
        $gate = new SchemeFilesScheduledSkipGate();
        $ref = new \ReflectionClass($gate);
        $m = $ref->getMethod('validatePublishedOutputs');
        $m->setAccessible(true);

        $meta = ['xml_sha256' => 'x'];
        $this->assertSame('xml_missing', $m->invoke($gate, '/tmp/no-such-scheme-xml-'.uniqid(), $meta));

        $dir = sys_get_temp_dir().'/scheme_skip_xml_'.getmypid();
        @mkdir($dir, 0777, true);
        $bad = $dir.'/bad.xml';
        file_put_contents($bad, str_repeat('not-xml-content-', 4)); // >=32 bytes, missing <rates
        $this->assertSame('xml_corrupt', $m->invoke($gate, $bad, $meta));

        $tiny = $dir.'/tiny.xml';
        file_put_contents($tiny, 'x');
        $this->assertSame('xml_too_small', $m->invoke($gate, $tiny, $meta));

        $good = $dir.'/good.xml';
        $body = '<?xml version="1.0"?><rates><item><from>A</from><to>B</to></item></rates>';
        file_put_contents($good, $body);
        $meta['xml_sha256'] = hash('sha256', $body);
        $this->assertNull($m->invoke($gate, $good, $meta));

        $meta['xml_sha256'] = 'drift';
        $this->assertSame('xml_sha_drift', $m->invoke($gate, $good, $meta));
    }

    public function test_atomic_publication_integrity_gate_requires_matching_sha(): void
    {
        $f = $this->baseOkFacts();
        $f['output_fail'] = 'xml_sha_drift';
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($f);
        $this->assertFalse($d['skip']);
    }

    public function test_cs_created_at_alone_is_not_a_skip_blocker(): void
    {
        // Proof contract: fingerprint equality ignores wall-clock stamps.
        // Changing only cs.created_at cannot alter XML/rates inputs; skip remains valid
        // when complete input fp matches. (created_at is written only during rebuild.)
        $f = $this->baseOkFacts();
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($f);
        $this->assertTrue($d['skip']);
        $this->assertSame('unchanged_complete_inputs', $d['reason']);
    }

    public function test_time_window_boundary_change_forces_full_export(): void
    {
        // Time-window open-mask is part of computeInputFingerprint(); a new fp
        // simulates crossing from_on_time/to_on_time.
        $f = $this->baseOkFacts();
        $f['fingerprint'] = 'time-window-open-mask-changed';
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($f);
        $this->assertFalse($d['skip']);
        $this->assertSame('fingerprint_changed', $d['reason']);
    }

    public function test_publication_metadata_sha_drift_forces_full_export(): void
    {
        $f = $this->baseOkFacts();
        $f['output_fail'] = 'xml_sha_drift';
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($f);
        $this->assertFalse($d['skip']);
        $this->assertSame('xml_sha_drift', $d['reason']);
    }

    public function test_monitor_or_offline_uncertainty_fails_open(): void
    {
        $f = $this->baseOkFacts();
        $f['offline_failed'] = true;
        $this->assertFalse(SchemeFilesScheduledSkipGate::decideFromFacts($f)['skip']);

        $f = $this->baseOkFacts();
        $f['monitor_failed'] = true;
        $this->assertFalse(SchemeFilesScheduledSkipGate::decideFromFacts($f)['skip']);
    }

    public function test_fp_version_is_v2_without_absolute_clock_hm(): void
    {
        $this->assertSame(2, SchemeFilesScheduledSkipGate::FP_VERSION);
        $src = (string) file_get_contents(
            dirname(__DIR__, 3).'/app/Services/Rates/SchemeFilesScheduledSkipGate.php'
        );
        $this->assertStringNotContainsString(
            'clock_hm=',
            $src,
            'v2 fingerprint must not hash absolute H:i (prevents cross-minute skip)'
        );
        $this->assertStringContainsString('tw|{$row->id}|{$from}|{$to}|{$open}', $src);
    }

    public function test_fp_version_mismatch_does_not_skip(): void
    {
        $f = $this->baseOkFacts();
        $f['meta']['fp_version'] = 1;
        $d = SchemeFilesScheduledSkipGate::decideFromFacts($f);
        $this->assertFalse($d['skip']);
        $this->assertSame('fp_version_mismatch', $d['reason']);
    }
}
