#!/usr/bin/env python3
"""P1 v4 — Fix SSR meta injector scope (move out of seti-usdt block) + remove debug markers."""
from __future__ import annotations

import sys
from pathlib import Path

SERVER = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/server/server.mjs")
MARKER = "EXS_SSR_P1_META_COVERAGE_V4"

BROKEN = (
    '}catch(ex10){}b=b.replace("</head>","<!--P1OUTSIDE--></head>");'
    'try{b=b.replace("</title>","</title><!--P1DEBUGMARKER-->");let p1m=(u)=>{'
)

FIXED = '}catch(ex10){}}try{let p1m=(u)=>{'

DEBUG_REMOVALS = [
    ('console.error("EXS_P1_ENTER",!!(t&&t.body&&t.body.getReader));', ''),
    ('b=b.replace("</head>","<!--P1EARLY--></head>");', ''),
    ('console.error("EXS_BEFORE_SOFT404",!!i);', ''),
]


def patch_server(text: str) -> tuple[str, list[str]]:
    if MARKER in text:
        return text, []
    changes: list[str] = []
    if BROKEN not in text:
        return text, []
    text = text.replace(BROKEN, FIXED, 1)
    changes.append("unblock_p1_injector")
    for old, new in DEBUG_REMOVALS:
        if old in text:
            text = text.replace(old, new, 1)
            changes.append("remove_debug")
    text = text.rstrip() + f"\n/* {MARKER} */\n"
    return text, changes


def main() -> int:
    if not SERVER.exists():
        print(f"[ERROR] missing {SERVER}", file=sys.stderr)
        return 1
    text = SERVER.read_text(encoding="utf-8")
    if MARKER in text:
        print("[INFO] server.mjs already v4")
        return 0
    new_text, changes = patch_server(text)
    if not changes:
        print("[ERROR] v4 anchors not matched", file=sys.stderr)
        return 1
    SERVER.write_text(new_text, encoding="utf-8")
    print(f"[OK] server.mjs: {', '.join(changes)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
