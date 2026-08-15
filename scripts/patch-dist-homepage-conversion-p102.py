#!/usr/bin/env python3
"""P10.2 — Homepage conversion quick wins SSR patch."""
from __future__ import annotations

import sys
from pathlib import Path

DIST_ROOT = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/server")
SEO_ROOT = Path("/var/www/app_exswapin_usr/data/www/app.exswaping.com/public/static/seo")
SEO_DEPLOY = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/storage/seo")
MARKER = "EXS_SSR_HOMEPAGE_CONVERSION_P102_V1"

FILES = [
    "p102-hero-subhead",
    "p102-bestchange",
    "p102-trust-strip",
    "p102-reviews",
    "p102-telegram-chip",
]

SSR_OLD = '}catch(ex14){}}if(b.includes("<not-found"))'

SSR_NEW = (
    '}catch(ex14){}}'
    'if(!b.includes("exs-p102-trust-strip")&&/rel=\\"canonical\\" href=\\"[^\\"]+\\/(ru|en)\\/?\\"/.test(b)&&b.includes("exchange__from")){'
    'try{'
    'let hloc=b.match(/rel=\\"canonical\\" href=\\"[^\\"]+\\/(ru|en)\\/?\\"/i);'
    'let loc=hloc&&hloc[1]||"ru";'
    'let base="/var/www/exswaping_co_usr/data/www/exswaping.com/storage/seo/p102-";'
    'let{readFileSync}=await import("node:fs");'
    'let sub=readFileSync(base+"hero-subhead."+loc+".html",{encoding:"utf8"});'
    'let bc=readFileSync(base+"bestchange."+loc+".html",{encoding:"utf8"});'
    'let ts=readFileSync(base+"trust-strip."+loc+".html",{encoding:"utf8"});'
    'let rv=readFileSync(base+"reviews."+loc+".html",{encoding:"utf8"});'
    'let tg=readFileSync(base+"telegram-chip."+loc+".html",{encoding:"utf8"});'
    'if(sub&&!b.includes("exs-p102-hero-subhead"))b=b.replace(/(<h1[^>]*data-exs-seo-v2[^>]*>[\\s\\S]*?<\\/h1>)/i,"$1"+sub);'
    'if(bc&&!b.includes("exs-p102-bestchange"))b=b.replace(\'<form novalidate class="exchange__finally\',bc+\'<form novalidate class="exchange__finally\');'
    'if(ts&&!b.includes("exs-p102-trust-strip"))b=b.replace(\'<formly-form class="ng-tns\',ts+\'<formly-form class="ng-tns\');'
    'if(rv&&!b.includes("exs-p102-reviews"))b=b.replace("<exchange-advantages",rv+"<exchange-advantages");'
    'if(tg&&!b.includes("exs-p102-support-chip"))b=b.replace("</app-header>",tg+"</app-header>");'
    '}catch(ex15){}}'
    'if(b.includes("<not-found"))'
)


def deploy_html() -> list[str]:
    SEO_DEPLOY.mkdir(parents=True, exist_ok=True)
    deployed = []
    for stem in FILES:
        for locale in ("ru", "en"):
            src = SEO_ROOT / f"{stem}.{locale}.html"
            dst = SEO_DEPLOY / f"{stem}.{locale}.html"
            if not src.is_file():
                raise FileNotFoundError(src)
            dst.write_text(src.read_text(encoding="utf-8"), encoding="utf-8")
            dst.chmod(0o644)
            deployed.append(str(dst))
    return deployed


def patch_server(text: str) -> tuple[str, list[str]]:
    if MARKER in text:
        return text, []
    if SSR_OLD not in text:
        return text, []
    text = text.replace(SSR_OLD, SSR_NEW, 1)
    text = text.rstrip() + f"\n/* {MARKER} */\n"
    return text, ["homepage_conversion_p102"]


def main() -> int:
    try:
        deployed = deploy_html()
    except FileNotFoundError as exc:
        print(f"[ERROR] {exc}", file=sys.stderr)
        return 1

    server = DIST_ROOT / "server.mjs"
    if not server.is_file():
        print("[ERROR] missing server.mjs", file=sys.stderr)
        return 1

    text = server.read_text(encoding="utf-8")
    if MARKER in text:
        print("[INFO] server.mjs already patched")
        print(f"[OK] deployed {len(deployed)} html files")
        return 0

    new_text, changes = patch_server(text)
    if not changes:
        print("[ERROR] server.mjs anchor not matched", file=sys.stderr)
        return 1

    server.write_text(new_text, encoding="utf-8")
    print(f"[OK] server.mjs patched ({', '.join(changes)})")
    print(f"[OK] deployed {len(deployed)} html files")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
