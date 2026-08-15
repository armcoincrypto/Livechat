#!/usr/bin/env python3
"""P1 — Sequential meta description certification crawl."""
from __future__ import annotations

import json
import re
import subprocess
import sys
import time
import xml.etree.ElementTree as ET
from collections import Counter, defaultdict
from difflib import SequenceMatcher
from pathlib import Path

ROOT = Path("/var/www/app_exswapin_usr/data/www/app.exswaping.com")
SITEMAP = ROOT / "public/static/seo/sitemap.xml"
OUT = ROOT / "storage/app/seo/meta-description-audit-after.json"
UA = "MetaDescCert/1.0"
DELAY = 0.35


def urls() -> list[str]:
    root = ET.parse(SITEMAP).getroot()
    ns = {"sm": "http://www.sitemaps.org/schemas/sitemap/0.9"}
    u = [e.text.strip() for e in root.findall(".//sm:loc", ns) if e.text]
    for x in [
        "https://exswaping.com/ru/partners",
        "https://exswaping.com/en/partners",
        "https://exswaping.com/ru/news",
        "https://exswaping.com/en/news",
    ]:
        if x not in u:
            u.append(x)
    return u


def curl(url: str) -> tuple[int, str]:
    st = subprocess.check_output(
        ["curl", "-sSI", "--max-time", "25", "-A", UA, url],
        text=True,
        stderr=subprocess.DEVNULL,
    )
    status = 0
    for line in st.splitlines():
        if line.startswith("HTTP/") and len(line.split()) >= 2 and line.split()[1].isdigit():
            status = int(line.split()[1])
    html = ""
    if status == 200:
        html = subprocess.check_output(
            ["curl", "-sS", "--max-time", "25", "-A", UA, url],
            text=True,
            stderr=subprocess.DEVNULL,
        )
    return status, html


def meta(html: str, name: str, prop: bool = False) -> str | None:
    if prop:
        m = re.search(
            rf'<meta[^>]+property=["\']{re.escape(name)}["\'][^>]+content=["\'](.*?)["\']',
            html,
            re.I | re.S,
        )
        if not m:
            m = re.search(
                rf'<meta[^>]+content=["\'](.*?)["\'][^>]+property=["\']{re.escape(name)}["\']',
                html,
                re.I | re.S,
            )
    else:
        m = re.search(
            rf'<meta[^>]+name=["\']{re.escape(name)}["\'][^>]+content=["\'](.*?)["\']',
            html,
            re.I | re.S,
        )
        if not m:
            m = re.search(
                rf'<meta[^>]+content=["\'](.*?)["\'][^>]+name=["\']{re.escape(name)}["\']',
                html,
                re.I | re.S,
            )
    return re.sub(r"\s+", " ", m.group(1)).strip() if m else None


def classify(desc: str | None) -> str:
    if not desc:
        return "META_MISSING"
    n = len(desc)
    if n < 70:
        return "META_TOO_SHORT"
    if n > 170:
        return "META_TOO_LONG"
    if "500+ supported directions" in desc or "500+ Направлений" in desc:
        return "META_BOILERPLATE"
    return "META_OK"


def dupes(rows: list[dict]) -> list[dict]:
    out: list[dict] = []
    by: dict[str, list[str]] = defaultdict(list)
    for r in rows:
        d = r.get("description")
        if d:
            by[d].append(r["url"])
    for d, us in by.items():
        if len(us) > 1:
            out.append({"description": d, "urls": us, "similarity": 100})
    items = [(r["url"], r["description"]) for r in rows if r.get("description")]
    for i in range(len(items)):
        for j in range(i + 1, len(items)):
            a, da = items[i]
            b, db = items[j]
            if da == db:
                continue
            ratio = SequenceMatcher(None, da, db).ratio() * 100
            if ratio >= 85:
                out.append(
                    {
                        "url_a": a,
                        "description_a": da,
                        "url_b": b,
                        "description_b": db,
                        "similarity": round(ratio, 1),
                    }
                )
    return out


def main() -> int:
    rows: list[dict] = []
    for url in urls():
        status, html = curl(url)
        row: dict = {"url": url, "status": status}
        if status == 200:
            tm = re.search(r"<title[^>]*>(.*?)</title>", html, re.I | re.S)
            row["title"] = tm.group(1).strip() if tm else None
            row["description"] = meta(html, "description")
            row["og_description"] = meta(html, "og:description", prop=True)
            row["canonical"] = meta(html, "canonical")
            row["class"] = classify(row["description"])
        else:
            row["class"] = f"HTTP_{status}"
        rows.append(row)
        time.sleep(DELAY)

    counts = Counter(r.get("class", "?") for r in rows)
    dups = dupes([r for r in rows if r.get("status") == 200])
    payload = {
        "total": len(rows),
        "counts": dict(counts),
        "duplicates": dups,
        "rows": rows,
    }
    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(json.dumps(payload, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")

    missing = counts.get("META_MISSING", 0)
    boiler = counts.get("META_BOILERPLATE", 0)
    dup_n = len([d for d in dups if d.get("similarity") == 100])
    http_bad = sum(v for k, v in counts.items() if k.startswith("HTTP_") and k != "HTTP_200")

    print(json.dumps({"counts": dict(counts), "missing": missing, "boilerplate": boiler, "dup_exact": dup_n, "http_errors": http_bad}, ensure_ascii=False))

    if missing == 0 and boiler == 0 and dup_n == 0 and http_bad == 0:
        print("META_DESCRIPTION_COVERAGE_CERTIFIED_PASS")
        return 0
    print("META_DESCRIPTION_COVERAGE_BLOCKED")
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
