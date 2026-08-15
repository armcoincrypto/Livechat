#!/usr/bin/env python3
"""SEO-POLISH-1 — Demote duplicate contacts H1 to div (visual unchanged)."""
from __future__ import annotations

import sys
from pathlib import Path

DIST_ROOT = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/server")
MARKER = "EXS_SSR_CONTACTS_H1_POLISH1_V1"

SSR_OLD = 'catch(ex10){}}if(!/<h1/i.test(b)&&/\\/blog\\//.test(b)){'

SSR_NEW = (
    'catch(ex10){}}'
    'if(/rel=\\"canonical\\" href=\\"[^\\"]+\\/(ru|en)\\/contacts\\/?\\"/.test(b)){'
    'try{'
    'b=b.replace(/<h1 class=\\"mb-8 lg:mb-10 text-xl lg:text-2xl font-semibold\\">([\\s\\S]*?)<\\/h1>/,'
    '"<div class=\\"mb-8 lg:mb-10 text-xl lg:text-2xl font-semibold\\">$1</div>");'
    '}catch(ex11){}}'
    'if(!/<h1/i.test(b)&&/\\/blog\\//.test(b)){'
)


def patch_server(text: str) -> tuple[str, list[str]]:
    if MARKER in text:
        return text, []
    if SSR_OLD not in text:
        return text, []
    text = text.replace(SSR_OLD, SSR_NEW, 1)
    text = text.rstrip() + f"\n/* {MARKER} */\n"
    return text, ["contacts_h1"]


def main() -> int:
    server = DIST_ROOT / "server.mjs"
    if not server.exists():
        print("[ERROR] missing server.mjs", file=sys.stderr)
        return 1

    text = server.read_text(encoding="utf-8")
    if MARKER in text:
        print("[INFO] server.mjs already patched")
        return 0

    new_text, changes = patch_server(text)
    if not changes:
        print("[ERROR] server.mjs anchor not matched", file=sys.stderr)
        return 1

    server.write_text(new_text, encoding="utf-8")
    print(f"[OK] server.mjs patched ({', '.join(changes)})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
