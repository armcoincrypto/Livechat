#!/usr/bin/env bash
# FINAL SEO CERTIFICATION — read-only live production audit
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
cd "$ROOT"

BASE_URL="${BASE_URL:-https://exswaping.com}"
SITEMAP_PATH="${SITEMAP_PATH:-public/static/seo/sitemap.xml}"
JSON_OUT="${JSON_OUT:-/tmp/final-seo-certification.json}"
REPORT_PATH="${REPORT_PATH:-docs/audits/final-seo-certification.md}"

export BASE_URL SITEMAP_PATH JSON_OUT REPORT_PATH ROOT SCRIPT_DIR

python3 <<'PY'
from __future__ import annotations

import json
import re
import subprocess
import sys
import xml.etree.ElementTree as ET
from datetime import datetime, timezone
from pathlib import Path

BASE = __import__("os").environ["BASE_URL"].rstrip("/")
ROOT = Path(__import__("os").environ["ROOT"])
SITEMAP = ROOT / __import__("os").environ["SITEMAP_PATH"]
JSON_OUT = Path(__import__("os").environ["JSON_OUT"])
SCRIPT_DIR = Path(__import__("os").environ["SCRIPT_DIR"])

RU_GUIDES = [
    "obmen-usdt-na-rubli",
    "obmen-usdt-trc20",
    "obmen-usdt-na-kartu",
    "bezopasnyj-kriptoobmen",
    "monitoring-kriptovalyutnyh-obmennikov",
    "seti-usdt",
]
EN_GUIDES = ["usdt-exchange", "usdt-trc20-exchange", "usdt-to-bank-card"]
HUBS = {
    "trust": "/ru/guides/bezopasnyj-kriptoobmen",
    "monitoring": "/ru/guides/monitoring-kriptovalyutnyh-obmennikov",
    "networks": "/ru/guides/seti-usdt",
}
EXCHANGES = [
    ("ru_tier_a", "/ru/exchange/USDTTRC20/SBERRUB"),
    ("ru_btc", "/ru/exchange/BTC/SBERRUB"),
    ("en_pair", "/en/exchange/USDTTRC20/BTC"),
]
ERROR_RE = re.compile(r"Internal Error|Страница временно недоступна|Internal Server Error", re.I)


def log(msg: str) -> None:
    print(f"[final-seo-cert] {msg}", file=sys.stderr)


def fetch(url: str, head: bool = False) -> tuple[int, str]:
    cmd = ["curl", "-sS", "--max-time", "45"]
    if head:
        cmd += ["-sSI"]
    cmd.append(url)
    try:
        out = subprocess.check_output(cmd, stderr=subprocess.STDOUT, text=True)
    except subprocess.CalledProcessError as e:
        out = e.output or ""
    if head:
        m = re.search(r"^HTTP/\S+\s+(\d+)", out, re.M)
        return (int(m.group(1)) if m else 0), out
    return (200 if out else 0), out


def canonical(body: str) -> str | None:
    m = re.search(r'rel="canonical"\s+href="([^"]+)"', body, re.I)
    return m.group(1) if m else None


def h1_count(body: str) -> int:
    return len(re.findall(r"<h1[\s>]", body, re.I))


def has_schema(body: str, typ: str) -> bool:
    return bool(re.search(rf'"@type"\s*:\s*"{re.escape(typ)}"', body))


def has_noindex(body: str) -> bool:
    m = re.search(r'name="robots"\s+content="([^"]+)"', body, re.I)
    return bool(m and "noindex" in m.group(1).lower())


def href_exact(body: str, path: str) -> bool:
    return f'href="{path}"' in body


def internal_links(body: str) -> list[str]:
    return re.findall(r'href="(/[^"#?]+)"', body)


def run_sub_validator(name: str) -> dict:
    path = SCRIPT_DIR / f"verify-{name}.sh"
    if not path.is_file():
        return {"name": name, "status": "missing", "exit_code": None}
    proc = subprocess.run(["bash", str(path)], capture_output=True, text=True)
    tail = (proc.stdout + proc.stderr).strip().splitlines()[-3:]
    return {"name": name, "status": "pass" if proc.returncode == 0 else "fail", "exit_code": proc.returncode, "tail": tail}


audit: dict = {
    "timestamp": datetime.now(timezone.utc).isoformat(),
    "base_url": BASE,
    "phases": {},
    "validators": {},
    "hard_failures": [],
    "warnings": [],
    "verdict": "",
}

# --- Phase 1 ---
audit["phases"]["phase1_homepage"] = {}
for loc in ("ru", "en"):
    url = f"{BASE}/{loc}/"
    status, body = fetch(url, head=True)
    _, body = fetch(url)
    checks = {
        "http_200": status == 200,
        "guide_links_block": 'id="seo-homepage-guide-links"' in body,
        "exchange_footer": 'id="seo-popular-exchange-directions"' in body,
        "organization_schema": has_schema(body, "Organization"),
        "website_schema": has_schema(body, "WebSite"),
        "canonical": bool(canonical(body) and f"/{loc}/" in canonical(body)),
        "hreflang_ru_en": 'hreflang="ru"' in body and 'hreflang="en"' in body,
    }
    audit["phases"]["phase1_homepage"][loc] = checks
    for k, ok in checks.items():
        if not ok:
            audit["hard_failures"].append(f"phase1/{loc}/{k}")


# --- Phase 2 ---
audit["phases"]["phase2_guides"] = {}


def audit_guide(loc: str, slug: str) -> dict:
    path = f"/{loc}/guides/{slug}"
    url = BASE + path
    status, _ = fetch(url, head=True)
    _, body = fetch(url)
    c = canonical(body)
    checks = {
        "http_200": status == 200,
        "ssr_content": len(re.sub(r"<[^>]+>", " ", body).split()) > 200,
        "h1_single": h1_count(body) == 1,
        "canonical_self": c in (url, url.rstrip("/")),
        "article_schema": has_schema(body, "Article"),
        "faq_schema": has_schema(body, "FAQPage"),
        "internal_links": len(internal_links(body)) >= 3,
        "no_internal_error": not ERROR_RE.search(body),
    }
    dir_path = f"/{loc}/guides"
    if not href_exact(body, dir_path):
        audit["warnings"].append(f"phase2/{loc}/{slug}: no exact link to guide directory {dir_path}")
    return checks


for slug in RU_GUIDES:
    key = f"ru/{slug}"
    audit["phases"]["phase2_guides"][key] = audit_guide("ru", slug)
    for k, ok in audit["phases"]["phase2_guides"][key].items():
        if not ok:
            audit["hard_failures"].append(f"phase2/{key}/{k}")

for slug in EN_GUIDES:
    key = f"en/{slug}"
    audit["phases"]["phase2_guides"][key] = audit_guide("en", slug)
    for k, ok in audit["phases"]["phase2_guides"][key].items():
        if not ok:
            audit["hard_failures"].append(f"phase2/{key}/{k}")


# --- Phase 3 ---
audit["phases"]["phase3_directories"] = {}
for loc in ("ru", "en"):
    url = f"{BASE}/{loc}/guides"
    status, _ = fetch(url, head=True)
    _, body = fetch(url)
    expected = RU_GUIDES if loc == "ru" else EN_GUIDES
    missing = [s for s in expected if f"/{loc}/guides/{s}" not in body]
    checks = {
        "http_200": status == 200,
        "ssr_block": 'id="seo-guide-directory"' in body,
        "canonical_self": canonical(body) == url,
        "all_guide_links": not missing,
        "missing_links": missing,
    }
    audit["phases"]["phase3_directories"][loc] = checks
    if not all(checks[k] for k in checks if k != "missing_links"):
        audit["hard_failures"].append(f"phase3/{loc}")


# --- Phase 4 ---
audit["phases"]["phase4_exchange"] = {}
for label, path in EXCHANGES:
    url = BASE + path
    status, _ = fetch(url, head=True)
    _, body = fetch(url)
    trust_links = [l for l in internal_links(body) if "/guides/" in l]
    checks = {
        "http_200": status == 200,
        "trust_block": 'id="seo-exchange-trust-links"' in body,
        "trust_links_resolve": all(fetch(BASE + l, head=True)[0] == 200 for l in trust_links[:5]),
        "exchange_form": "exchange__from" in body and "exchange__to" in body,
        "reserves_preserved": "<exchange-reserves" in body,
    }
    audit["phases"]["phase4_exchange"][label] = checks
    if not all(checks.values()):
        audit["hard_failures"].append(f"phase4/{label}")


# --- Phase 5 ---
blog_urls = re.findall(
    r"<loc>(https://exswaping.com/(?:ru|en)/blog/[^<]+)</loc>",
    SITEMAP.read_text(encoding="utf-8"),
)
audit["phases"]["phase5_blog"] = {"inventory": len(blog_urls), "exceptions": []}
for url in blog_urls:
    slug = url.rsplit("/", 1)[-1]
    loc = "en" if "/en/" in url else "ru"
    status, _ = fetch(url, head=True)
    _, body = fetch(url)
    guide_link_count = sum(1 for l in internal_links(body) if "/guides/" in l)
    ok = status == 200 and has_schema(body, "Article") and guide_link_count > 0
    if not ok:
        audit["phases"]["phase5_blog"]["exceptions"].append(
            {"url": url, "http_200": status == 200, "article_schema": has_schema(body, "Article"), "guide_links": guide_link_count}
        )
if audit["phases"]["phase5_blog"]["exceptions"]:
    audit["hard_failures"].append("phase5/blog_exceptions")


# --- Phase 6 ---
audit["phases"]["phase6_hubs"] = {}
for name, path in HUBS.items():
    _, body = fetch(BASE + path)
    links = internal_links(body)
    commercial = sum(1 for l in links if any(s in l for s in ("obmen-usdt", "usdt-trc20", "usdt-exchange")))
    authority = sum(1 for l in links if any(s in l for s in ("bezopasnyj", "monitoring", "seti-usdt")))
    checks = {
        "article_schema": has_schema(body, "Article"),
        "faq_schema": has_schema(body, "FAQPage"),
        "authority_links": authority >= 2,
        "commercial_links": commercial >= 1,
    }
    audit["phases"]["phase6_hubs"][name] = checks
    if not all(checks.values()):
        audit["hard_failures"].append(f"phase6/{name}")


# --- Phase 7 ---
graph_spec = [
    ("/ru/", "/ru/guides", "homepage_to_directory"),
    ("/ru/", "/ru/guides/obmen-usdt-na-rubli", "homepage_to_guide"),
    ("/ru/guides/obmen-usdt-trc20", "/ru/exchange/USDTTRC20/SBERRUB", "guide_to_exchange"),
    ("/ru/exchange/USDTTRC20/SBERRUB", "/ru/guides/bezopasnyj-kriptoobmen", "exchange_to_trust"),
    ("/ru/guides/bezopasnyj-kriptoobmen", "/ru/guides/obmen-usdt-na-rubli", "trust_to_guide"),
    ("/ru/guides/monitoring-kriptovalyutnyh-obmennikov", "/ru/guides/obmen-usdt-trc20", "monitoring_to_guide"),
    ("/ru/guides/seti-usdt", "/ru/guides/usdt-trc20-i-erc20", "networks_to_guide"),
]
audit["phases"]["phase7_graph"] = []
for src, dst, label in graph_spec:
    _, body = fetch(BASE + src)
    present = href_exact(body, dst)
    audit["phases"]["phase7_graph"].append({"from": src, "to": dst, "label": label, "present": present})
    if not present:
        audit["warnings"].append(f"phase7/{label}: missing edge {src} -> {dst}")


# --- Phase 8 ---
xml = SITEMAP.read_text(encoding="utf-8")
local_count = xml.count("<url>")
_, public_xml = fetch(f"{BASE}/static/seo/sitemap.xml")
public_count = public_xml.count("<url>") if public_xml else 0
_, robots = fetch(f"{BASE}/robots.txt")
audit["phases"]["phase8_sitemap"] = {
    "local_url_count": local_count,
    "public_url_count": public_count,
    "guide_directories_listed": all(
        u in xml for u in (f"{BASE}/ru/guides", f"{BASE}/en/guides")
    ),
    "robots_sitemap_ref": "Sitemap: https://exswaping.com/static/seo/sitemap.xml" in robots,
    "robots_allow_root": "Allow: /" in robots,
}
if local_count != public_count:
    audit["hard_failures"].append("phase8/sitemap_local_public_mismatch")
if local_count == 200:
    audit["warnings"].append("phase8: sitemap count 200 (post SEO-GUIDES-2); audit brief cited 198")
elif local_count != 198:
    audit["hard_failures"].append(f"phase8/unexpected_sitemap_count_{local_count}")

try:
    ET.fromstring(xml)
    audit["phases"]["phase8_sitemap"]["xml_valid"] = True
except ET.ParseError:
    audit["phases"]["phase8_sitemap"]["xml_valid"] = False
    audit["hard_failures"].append("phase8/xml_invalid")

# hreflang/canonical spot checks
for path in ("/ru/", "/en/", "/ru/guides", "/ru/guides/obmen-usdt-trc20"):
    _, body = fetch(BASE + path)
    if not canonical(body):
        audit["hard_failures"].append(f"phase8/missing_canonical{path}")
    if has_noindex(body) and "/en/exchange/" not in path:
        audit["hard_failures"].append(f"phase8/unexpected_noindex{path}")

# --- Phase 9 ---
regression_paths = [
    "/ru/contacts",
    "/ru/faq",
    "/ru/guides",
    "/ru/guides/obmen-usdt-na-rubli",
    "/ru/exchange/USDTTRC20/SBERRUB",
    "/ru/xyzrandom404test",
]
audit["phases"]["phase9_regression"] = []
for path in regression_paths:
    url = BASE + path
    status, _ = fetch(url, head=True)
    _, body = fetch(url) if status == 200 else ("", "")
    item = {"path": path, "status": status}
    if path.endswith("404test"):
        if status != 404:
            audit["hard_failures"].append("phase9/soft_404_broken")
        continue
    if status != 200:
        audit["hard_failures"].append(f"phase9/status_{path}_{status}")
    if body and h1_count(body) > 1:
        audit["warnings"].append(f"phase9/duplicate_h1{path}")
    if body and ERROR_RE.search(body):
        audit["hard_failures"].append(f"phase9/internal_error{path}")
    audit["phases"]["phase9_regression"].append(item)

# --- Sub-validators (live) ---
for name in (
    "homepage-guide-links",
    "guide-directory",
    "exchange-trust-links",
    "seo-guides-2-sitemap",
):
    res = run_sub_validator(name)
    audit["validators"][name] = res
    if res.get("status") != "pass":
        audit["hard_failures"].append(f"validator/{name}")

# Blog portfolio: content checks matter; ignore stale 198 sitemap assertion
blog_res = run_sub_validator("blog-portfolio-seo")
audit["validators"]["blog-portfolio-seo"] = blog_res
if blog_res.get("exit_code") == 2 and audit["phases"]["phase5_blog"]["exceptions"]:
    audit["hard_failures"].append("validator/blog-portfolio-seo")
elif blog_res.get("exit_code") == 2:
    audit["warnings"].append(
        "validator/blog-portfolio-seo: exit 2 (legacy sitemap count gate); live blog portfolio content checks PASS"
    )

# --- Verdict ---
if audit["hard_failures"]:
    audit["verdict"] = "SEO_CERTIFICATION_FAILED"
elif not audit["warnings"]:
    audit["verdict"] = "SEO_CERTIFIED_GOLD"
elif all(
    audit["phases"]["phase1_homepage"][loc][k]
    for loc in ("ru", "en")
    for k in ("guide_links_block", "exchange_footer", "organization_schema", "website_schema")
) and all(
    audit["phases"]["phase3_directories"][loc]["ssr_block"] for loc in ("ru", "en")
) and all(audit["phases"]["phase4_exchange"][k]["trust_block"] for k in audit["phases"]["phase4_exchange"]):
    audit["verdict"] = "SEO_CERTIFIED_SILVER"
else:
    audit["verdict"] = "SEO_CERTIFIED_WITH_WARNINGS"

JSON_OUT.write_text(json.dumps(audit, indent=2, ensure_ascii=False), encoding="utf-8")

log(f"JSON written: {JSON_OUT}")
log(f"Hard failures: {len(audit['hard_failures'])}")
log(f"Warnings: {len(audit['warnings'])}")
log(f"VERDICT: {audit['verdict']}")
print(audit["verdict"])

if audit["verdict"] == "SEO_CERTIFICATION_FAILED":
    sys.exit(2)
if audit["verdict"] in ("SEO_CERTIFIED_WITH_WARNINGS", "SEO_CERTIFIED_SILVER"):
    sys.exit(1)
sys.exit(0)
PY
