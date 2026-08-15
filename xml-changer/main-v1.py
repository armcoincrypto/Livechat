#!/usr/bin/env python3
import sys
import os
from pathlib import Path
import xml.etree.ElementTree as ET
from decimal import Decimal, ROUND_HALF_UP, getcontext
from io import BytesIO
import tempfile

XML_PATH = Path("/var/www/app_exswapin_usr/data/www/app.exswaping.com/public/static/exports/currencies.xml")
XML_PATH_WRITE = Path("/var/www/app_exswapin_usr/data/www/app.exswaping.com/public/static/exports/changed-currencies.xml")

def localname(tag: str) -> str:
    return tag.split("}", 1)[1] if "}" in tag else tag

def add_url_tags(root: ET.Element, base_url: str = "https://exswaping.com/ru/exchange/", city: str = "LA") -> None:
    """Add <url> element to each <item> using its <from> and <to>."""
    for item in root.findall(".//item"):
        f = item.find("from")
        t = item.find("to")
        if f is None or t is None:
            continue

        # Build URL: https://exswaping.com/ru/exchange/{FROM}/{TO}?city=LA
        full_url = f"{base_url}{f.text}/{t.text}?city={city}"

        # Check if <url> already exists
        existing = item.find("url")
        if existing is not None:
            existing.text = full_url
        else:
            ET.SubElement(item, "url").text = full_url

def bump_out_values(root: ET.Element, pct: Decimal = Decimal("0.3")) -> None:
    """Increase every <out> value by pct."""
    getcontext().prec = 50
    for el in root.iter():
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

def serialize_xml(root: ET.Element) -> bytes:
    buf = BytesIO()
    ET.ElementTree(root).write(
        buf,
        encoding="utf-8",
        xml_declaration=True,
        short_empty_elements=True
    )
    return buf.getvalue()

def main():
    if not XML_PATH.exists() or not XML_PATH.is_file():
        print(f"Error: file not found: {XML_PATH}", file=sys.stderr)
        sys.exit(1)

    data = XML_PATH.read_bytes()
    if not data.strip():
        print(f"Error: file empty: {XML_PATH}", file=sys.stderr)
        sys.exit(1)

    try:
        root = ET.fromstring(data)
    except ET.ParseError as e:
        print(f"Error parsing XML: {e}", file=sys.stderr)
        sys.exit(1)

    # 🔧 1) Adjust out values (optional)
    bump_out_values(root, Decimal("0.03"))

    # 🔗 2) Add <url> for every pair
    add_url_tags(root, base_url="https://exswaping.com/ru/exchange/", city="LA")

    # 💾 3) Save atomically
    updated = serialize_xml(root)
    try:
        with tempfile.NamedTemporaryFile("wb", delete=False, dir=str(XML_PATH.parent)) as tmp:
            tmp.write(updated)
            tmp.flush()
            os.fsync(tmp.fileno())
            tmp_name = tmp.name
        os.replace(tmp_name, XML_PATH_WRITE)
    except Exception as e:
        print(f"Error writing XML: {e}", file=sys.stderr)
        if 'tmp_name' in locals() and os.path.exists(tmp_name):
            os.remove(tmp_name)
        sys.exit(1)

    print("✅ currencies.xml updated successfully — URLs added!")

if __name__ == "__main__":
    main()
