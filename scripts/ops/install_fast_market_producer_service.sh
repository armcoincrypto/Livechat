#!/usr/bin/env bash
# Install/enable systemd unit for the ~30s fast-market producer.
# Does NOT enable FAST_MARKET_PLANE_ENABLED (producer idles until plane on).
set -euo pipefail

ROOT="${1:-}"
if [[ -z "${ROOT}" ]]; then
  ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
fi
UNIT_SRC="${ROOT}/deploy/systemd/exswaping-fast-market-producer.service"
UNIT_DST=/etc/systemd/system/exswaping-fast-market-producer.service

[[ -f "${UNIT_SRC}" ]] || { echo "FAIL missing ${UNIT_SRC}" >&2; exit 2; }
install -m 0644 "${UNIT_SRC}" "${UNIT_DST}"
systemctl daemon-reload
systemctl enable exswaping-fast-market-producer.service
systemctl restart exswaping-fast-market-producer.service
systemctl --no-pager --full status exswaping-fast-market-producer.service | head -20
echo "PRODUCER_SERVICE_INSTALLED_ENABLED"
echo "NOTE: producer idles until FAST_MARKET_PLANE_ENABLED=true"
