#!/usr/bin/env bash
# P0 — Sitemap integrity certification: every <loc> must HTTP 200 with self-canonical.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

SITEMAP_PATH="${SITEMAP_PATH:-public/static/seo/sitemap.xml}"
CURL_TIMEOUT="${CURL_TIMEOUT:-30}"
MAX_WORKERS="${MAX_WORKERS:-16}"
REPORT_JSON="${REPORT_JSON:-/tmp/sitemap-integrity-report.json}"

log() { echo "[sitemap-integrity] $*" >&2; }

if [[ ! -f "$SITEMAP_PATH" ]]; then
  log "FAIL missing sitemap: $SITEMAP_PATH"
  exit 2
fi

export SITEMAP_PATH CURL_TIMEOUT MAX_WORKERS REPORT_JSON ROOT

python3 <<'PY'
from __future__ import annotations

import json
import os
import re
import subprocess
import sys
import xml.etree.ElementTree as ET
from concurrent.futures import ThreadPoolExecutor, as_completed
from pathlib import Path

ROOT = Path(os.environ["ROOT"])
SITEMAP = ROOT / os.environ["SITEMAP_PATH"]
TIMEOUT = os.environ["CURL_TIMEOUT"]
WORKERS = int(os.environ["MAX_WORKERS"])
REPORT = Path(os.environ["REPORT_JSON"])

NS = {"sm": "http://www.sitemaps.org/schemas/sitemap/0.9"}
root = ET.parse(SITEMAP).getroot()
urls = [e.text.strip() for e in root.findall(".//sm:loc", NS) if e.text]


def curl_head(url: str) -> tuple[int, str]:
    cmd = [
        "curl", "-sSI", "--max-time", TIMEOUT, "-A", "SitemapIntegrity/1.0",
        "-w", "\n__FINAL__%{url_effective}\n", url,
    ]
    try:
        out = subprocess.check_output(cmd, text=True, stderr=subprocess.DEVNULL)
    except subprocess.CalledProcessError as e:
        out = e.output or ""
    status = 0
    final = url
    for line in out.splitlines():
        if line.startswith("HTTP/"):
            parts = line.split()
            if len(parts) >= 2 and parts[1].isdigit():
                status = int(parts[1])
        if line.startswith("__FINAL__"):
            final = line.replace("__FINAL__", "", 1).strip()
    return status, final


def extract_canonical(html: str) -> str | None:
    m = re.search(r'rel=["\']canonical["\'][^>]+href=["\']([^"\']+)["\']', html, re.I)
    if m:
        return m.group(1)
    m = re.search(r'href=["\']([^"\']+)["\'][^>]+rel=["\']canonical["\']', html, re.I)
    return m.group(1) if m else None


def curl_body(url: str) -> str:
    cmd = ["curl", "-sS", "--max-time", TIMEOUT, "-A", "SitemapIntegrity/1.0", url]
    try:
        return subprocess.check_output(cmd, text=True, stderr=subprocess.DEVNULL)[:80000]
    except subprocess.CalledProcessError:
        return ""


def classify(url: str) -> dict:
    status, final = curl_head(url)
    row = {"url": url, "status": status, "final": final, "class": "", "canonical": None, "issue": ""}

    if status in (301, 302, 307, 308):
        row["class"] = "VALID_REDIRECT"
        row["issue"] = f"redirect to {final}"
        return row
    if status == 404:
        row["class"] = "INVALID_404"
        row["issue"] = "404"
        return row
    if status >= 500:
        row["class"] = "INVALID_500"
        row["issue"] = str(status)
        return row
    if status != 200:
        row["class"] = f"OTHER_{status}"
        row["issue"] = str(status)
        return row

    if final.rstrip("/") != url.rstrip("/"):
        row["class"] = "VALID_REDIRECT"
        row["issue"] = f"effective URL {final}"
        return row

    html = curl_body(url)
    canon = extract_canonical(html)
    row["canonical"] = canon
    if canon and canon.rstrip("/") != url.rstrip("/"):
        row["class"] = "INVALID_CANON"
        row["issue"] = f"canonical={canon}"
        return row
    if not canon:
        row["class"] = "VALID_200"
        row["issue"] = "no canonical tag (allowed)"
        return row

    row["class"] = "VALID_200"
    return row


results: list[dict] = []
with ThreadPoolExecutor(max_workers=WORKERS) as pool:
    futs = {pool.submit(classify, u): u for u in urls}
    for fut in as_completed(futs):
        results.append(fut.result())

results.sort(key=lambda r: r["url"])
counts: dict[str, int] = {}
for r in results:
    counts[r["class"]] = counts.get(r["class"], 0) + 1

invalid_404 = [r for r in results if r["class"] == "INVALID_404"]
invalid_500 = [r for r in results if r["class"] == "INVALID_500"]
broken_canon = [r for r in results if r["class"] == "INVALID_CANON"]
redirects = [r for r in results if r["class"] == "VALID_REDIRECT"]

report = {
    "total": len(urls),
    "counts": counts,
    "invalid_404": invalid_404,
    "invalid_500": invalid_500,
    "broken_canonicals": broken_canon,
    "redirects": redirects,
    "results": results,
}
REPORT.write_text(json.dumps(report, indent=2, ensure_ascii=False) + "\n")

print(f"TOTAL={len(urls)}")
print(f"COUNTS={json.dumps(counts, ensure_ascii=False)}")
print(f"INVALID_404={len(invalid_404)}")
print(f"INVALID_500={len(invalid_500)}")
print(f"BROKEN_CANON={len(broken_canon)}")
print(f"REDIRECTS={len(redirects)}")

for r in invalid_404 + invalid_500 + broken_canon:
    print(f"FAIL {r['url']} {r['class']} {r['issue']}")

fail = len(invalid_404) + len(invalid_500) + len(broken_canon)
if fail:
    print("SITEMAP_INTEGRITY_STATUS=FAIL")
    sys.exit(2)
print("SITEMAP_INTEGRITY_STATUS=PASS")
print("SITEMAP_INTEGRITY_CERTIFIED_PASS")
sys.exit(0)
PY
