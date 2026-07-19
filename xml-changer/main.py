#!/usr/bin/env python3
"""
BestChange XML post-processor.

Canonical input:  public/static/exports/currencies.xml
Writable output:  public/static/exports/changed-currencies.xml

SAFETY (2026-07-19 rate-pipeline remediation):
- Rate mutation (<in>/<out> bump/divisor) is DISABLED by default.
- This script may only add/refresh <url> tags and copy the feed atomically.
- Re-enabling mutation requires explicit EXSWAPING_XML_CHANGER_MUTATE_RATES=1
  and is strongly discouraged — it systematically inflates exported rates.
"""
from __future__ import annotations

import os
import sys
import tempfile
from decimal import Decimal, ROUND_HALF_UP, getcontext
from io import BytesIO
from pathlib import Path
import xml.etree.ElementTree as ET

XML_PATH = Path(
    "/var/www/app_exswapin_usr/data/www/app.exswaping.com/public/static/exports/currencies.xml"
)
XML_PATH_WRITE = Path(
    "/var/www/app_exswapin_usr/data/www/app.exswaping.com/public/static/exports/changed-currencies.xml"
)

# Hard default: never mutate rates unless explicitly enabled.
MUTATE_RATES = os.environ.get("EXSWAPING_XML_CHANGER_MUTATE_RATES", "0").strip() == "1"


def localname(tag: str) -> str:
    return tag.split("}", 1)[1] if "}" in tag else tag


def add_url_tags(
    root: ET.Element,
    base_url: str = "https://exswaping.com/ru/exchange/",
    city: str = "LA",
) -> None:
    """Add <url> element to each <item> using its <from> and <to>."""
    for item in root.findall(".//item"):
        f = item.find("from")
        t = item.find("to")
        if f is None or t is None:
            continue

        full_url = f"{base_url}{f.text}/{t.text}?city={city}"
        existing = item.find("url")
        if existing is not None:
            existing.text = full_url
        else:
            ET.SubElement(item, "url").text = full_url


def bump_out_values(root: ET.Element, pct: Decimal = Decimal("0.03")) -> int:
    """LEGACY — increase every non-cash <out> by pct. Disabled by default."""
    getcontext().prec = 50
    changed = 0
    for item in root.findall(".//item"):
        item_has_cash = any(
            (el.text is not None and "CASH" in el.text) for el in item.iter()
        )
        if item_has_cash:
            continue
        for el in item.iter():
            if localname(el.tag) != "out" or el.text is None:
                continue
            raw = el.text.strip()
            if not raw:
                continue
            try:
                val = Decimal(raw)
            except Exception:
                continue
            new_val = val * (Decimal("1") + pct)
            if "." in raw:
                places = len(raw.split(".", 1)[1])
                quant = Decimal(1).scaleb(-places)
                new_val = new_val.quantize(quant, rounding=ROUND_HALF_UP)
            else:
                new_val = new_val.quantize(Decimal("1"), rounding=ROUND_HALF_UP)
            el.text = format(new_val, "f")
            changed += 1
    return changed


def adjust_in_values(root: ET.Element, divisor: Decimal = Decimal("1.03")) -> int:
    """LEGACY — divide every non-1 <in> by divisor. Disabled by default."""
    getcontext().prec = 50
    one = Decimal("1")
    changed = 0
    for item in root.findall(".//item"):
        item_has_cash = any(
            (el.text is not None and "CASH" in el.text) for el in item.iter()
        )
        if item_has_cash:
            continue
        for el in item.iter():
            if localname(el.tag) != "in" or el.text is None:
                continue
            raw = el.text.strip()
            if not raw:
                continue
            try:
                val = Decimal(raw)
            except Exception:
                continue
            if val == one:
                continue
            try:
                new_val = val / divisor
            except Exception:
                continue
            if "." in raw:
                places = len(raw.split(".", 1)[1])
                quant = Decimal(1).scaleb(-places)
                new_val = new_val.quantize(quant, rounding=ROUND_HALF_UP)
            else:
                new_val = new_val.quantize(Decimal("1"), rounding=ROUND_HALF_UP)
            el.text = format(new_val, "f")
            changed += 1
    return changed


def serialize_xml(root: ET.Element) -> bytes:
    buf = BytesIO()
    ET.ElementTree(root).write(
        buf,
        encoding="utf-8",
        xml_declaration=True,
        short_empty_elements=True,
    )
    return buf.getvalue()


def main() -> int:
    if not XML_PATH.exists() or not XML_PATH.is_file():
        print(f"Error: file not found: {XML_PATH}", file=sys.stderr)
        return 1

    data = XML_PATH.read_bytes()
    if not data.strip():
        print(f"Error: file empty: {XML_PATH}", file=sys.stderr)
        return 1

    try:
        root = ET.fromstring(data)
    except ET.ParseError as e:
        print(f"Error parsing XML: {e}", file=sys.stderr)
        return 1

    mutated_out = 0
    mutated_in = 0
    if MUTATE_RATES:
        # Explicit opt-in only — do not use in production.
        mutated_out = bump_out_values(root, Decimal("0.03"))
        mutated_in = adjust_in_values(root, Decimal("1.03"))
        print(
            f"WARNING: rate mutation ENABLED (out={mutated_out}, in={mutated_in})",
            file=sys.stderr,
        )
    else:
        print("rate mutation disabled (pass-through + url tags only)")

    add_url_tags(root, base_url="https://exswaping.com/ru/exchange/", city="LA")

    updated = serialize_xml(root)
    tmp_name = None
    try:
        with tempfile.NamedTemporaryFile(
            "wb", delete=False, dir=str(XML_PATH_WRITE.parent)
        ) as tmp:
            tmp.write(updated)
            tmp.flush()
            os.fsync(tmp.fileno())
            tmp_name = tmp.name
        os.replace(tmp_name, XML_PATH_WRITE)
        tmp_name = None
    except Exception as e:
        print(f"Error writing XML: {e}", file=sys.stderr)
        if tmp_name and os.path.exists(tmp_name):
            os.remove(tmp_name)
        return 1

    print(
        "✅ changed-currencies.xml updated — URLs added; "
        f"rate_mutation={'on' if MUTATE_RATES else 'off'}"
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
