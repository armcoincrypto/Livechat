<?php

declare(strict_types=1);

/**
 * Rate / XML / Zelle / BestChange freeze marker.
 * RATE_SYSTEM_FREEZE=ACTIVE means commercial rate changes require the full
 * release policy in docs/release/RATE_SYSTEM_FREEZE_POLICY.md.
 */
return [
    'RATE_SYSTEM_FREEZE' => 'ACTIVE',
    'freeze_id' => 'RATE_SYSTEM_FREEZE_20260923',
    'base_live_sha' => 'a402987f8f447d57c046d98f805cd65d223b44ec',
    'golden_fixture' => 'tests/fixtures/rates/golden_directions_freeze.json',
    'zelle_usdt_direction_ids' => [1525, 1537, 1552, 1556, 1586, 3222],
    'expected_zelle_floating_fee' => '-6',
    'expected_zelle_fix_fee' => '-3',
    'xml_publish_lock' => '/var/lock/exswaping-xml-publish.lock',
    'predeploy_gate' => 'scripts/ops/rate_system_predeploy_gate.sh',
];
