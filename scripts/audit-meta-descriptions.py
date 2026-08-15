#!/usr/bin/env python3
"""P1 — Meta description discovery across sitemap URLs."""
from __future__ import annotations

import json
import re
import subprocess
import sys
import xml.etree.ElementTree as ET
from collections import Counter, defaultdict
from concurrent.futures import ThreadPoolExecutor, as_completed
from difflib import SequenceMatcher
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SITEMAP = ROOT / "public/static/seo/sitemap.xml"
EXTRA = [
    "https://exswaping.com/ru/news",
    "https://exswaping.com/en/news",
    "https://exswaping.com/ru/partners",
    "https://exswaping.com/en/partners",
    "https://exswaping.com/en/",
    "https://exswaping.com/ru/guides/obmen-usdt-na-rubli",
    "https://exswaping.com/ru/guides/obmen-usdt-trc20",
    "https://exswaping.com/ru/guides/obmen-usdt-na-kartu",
    "https://exswaping.com/ru/guides/bezopasnyj-kriptoobmen",
    "https://exswaping.com/ru/guides/monitoring-kriptovalyutnyh-obmennikov",
    "https://exswaping.com/ru/guides/seti-usdt",
    "https://exswaping.com/ru/guides/usdt-trc20-i-erc20",
    "https://exswaping.com/en/guides/usdt-exchange",
    "https://exswaping.com/en/guides/usdt-trc20-exchange",
    "https://exswaping.com/en/guides/usdt-to-bank-card",
]
TIMEOUT = "30"
UA = "MetaDescAudit/1.0"


def load_urls() -> list[str]:
    root = ET.parse(SITEMAP).getroot()
    ns = {"sm": "http://www.sitemaps.org/schemas/sitemap/0.9"}
    urls = [e.text.strip() for e in root.findall(".//sm:loc", ns) if e.text]
    seen = set(urls)
    for u in EXTRA:
        if u not in seen:
            urls.append(u)
    return urls


def curl(url: str) -> str:
    cmd = ["curl", "-sS", "--max-time", TIMEOUT, "-A", UA, url]
    return subprocess.check_output(cmd, text=True, stderr=subprocess.DEVNULL)


def curl_status(url: str) -> int:
    cmd = ["curl", "-sSI", "--max-time", TIMEOUT, "-A", UA, url]
    out = subprocess.check_output(cmd, text=True, stderr=subprocess.DEVNULL)
    for line in out.splitlines():
        if line.startswith("HTTP/"):
            parts = line.split()
            if len(parts) >= 2 and parts[1].isdigit():
                return int(parts[1])
    return 0


def meta(html: str, name: str, prop: bool = False) -> str | None:
    if prop:
        m = re.search(rf'<meta[^>]+property=["\']{re.escape(name)}["\'][^>]+content=["\'](.*?)["\']', html, re.I | re.S)
        if not m:
            m = re.search(rf'<meta[^>]+content=["\'](.*?)["\'][^>]+property=["\']{re.escape(name)}["\']', html, re.I | re.S)
    else:
        m = re.search(rf'<meta[^>]+name=["\']{re.escape(name)}["\'][^>]+content=["\'](.*?)["\']', html, re.I | re.S)
        if not m:
            m = re.search(rf'<meta[^>]+content=["\'](.*?)["\'][^>]+name=["\']{re.escape(name)}["\']', html, re.I | re.S)
    return re.sub(r"\s+", " ", m.group(1)).strip() if m else None


def title(html: str) -> str | None:
    m = re.search(r"<title[^>]*>(.*?)</title>", html, re.I | re.S)
    return re.sub(r"\s+", " ", m.group(1)).strip() if m else None


def classify(desc: str | None) -> str:
    if not desc:
        return "META_MISSING"
    n = len(desc)
    if n < 70:
        return "META_TOO_SHORT"
    if n > 170:
        return "META_TOO_LONG"
    return "META_OK"


def audit_url(url: str) -> dict:
    status = curl_status(url)
    row = {"url": url, "status": status}
    if status != 200:
        row["class"] = f"HTTP_{status}"
        return row
    html = curl(url)
    row["title"] = title(html)
    row["description"] = meta(html, "description")
    row["canonical"] = meta(html, "canonical") or _link_canonical(html)
    row["og_title"] = meta(html, "og:title", prop=True)
    row["og_description"] = meta(html, "og:description", prop=True)
    row["class"] = classify(row["description"])
    return row


def _link_canonical(html: str) -> str | None:
    m = re.search(r'<link[^>]+rel=["\']canonical["\'][^>]+href=["\'](.*?)["\']', html, re.I)
    if not m:
        m = re.search(r'<link[^>]+href=["\'](.*?)["\'][^>]+rel=["\']canonical["\']', html, re.I)
    return m.group(1) if m else None


def find_duplicates(rows: list[dict]) -> list[dict]:
    by_desc: dict[str, list[str]] = defaultdict(list)
    for r in rows:
        d = r.get("description")
        if d:
            by_desc[d].append(r["url"])
    dups = []
    for desc, urls in by_desc.items():
        if len(urls) > 1:
            dups.append({"description": desc, "urls": urls, "similarity": 100})
    # near duplicates
    descs = [(r["url"], r["description"]) for r in rows if r.get("description")]
    for i in range(len(descs)):
        for j in range(i + 1, len(descs)):
            a, da = descs[i]
            b, db = descs[j]
            if da == db:
                continue
            ratio = SequenceMatcher(None, da, db).ratio() * 100
            if ratio >= 85:
                dups.append({"description_a": da, "url_a": a, "description_b": db, "url_b": b, "similarity": round(ratio, 1)})
    return dups


def main() -> int:
    urls = load_urls()
    rows: list[dict] = []
    with ThreadPoolExecutor(max_workers=14) as pool:
        futs = {pool.submit(audit_url, u): u for u in urls}
        for fut in as_completed(futs):
            rows.append(fut.result())
    rows.sort(key=lambda r: r["url"])

    counts = Counter(r.get("class", "UNKNOWN") for r in rows)
    missing = [r for r in rows if r.get("class") == "META_MISSING"]
    too_short = [r for r in rows if r.get("class") == "META_TOO_SHORT"]
    too_long = [r for r in rows if r.get("class") == "META_TOO_LONG"]
    og_missing = [r for r in rows if r.get("status") == 200 and not r.get("og_description")]
    dups = find_duplicates([r for r in rows if r.get("class") != "HTTP_404"])

    out = {
        "total": len(rows),
        "counts": dict(counts),
        "missing": missing,
        "too_short": too_short,
        "too_long": too_long,
        "og_missing": og_missing,
        "duplicates": dups,
        "rows": rows,
    }
    report = ROOT / "storage/app/seo/meta-description-audit-before.json"
    report.parent.mkdir(parents=True, exist_ok=True)
    report.write_text(json.dumps(out, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")

    print(f"TOTAL={len(rows)}")
    print(f"COUNTS={json.dumps(dict(counts), ensure_ascii=False)}")
    print(f"MISSING={len(missing)}")
    print(f"TOO_SHORT={len(too_short)}")
    print(f"TOO_LONG={len(too_long)}")
    print(f"OG_MISSING={len(og_missing)}")
    print(f"DUPLICATES={len(dups)}")
    for r in missing[:30]:
        print(f"MISSING {r['url']}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
