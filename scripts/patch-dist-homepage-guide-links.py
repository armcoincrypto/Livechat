#!/usr/bin/env python3
"""Homepage Authority Flow — SSR inject guide links section before </body> on /ru/ and /en/."""
from __future__ import annotations

import sys
from pathlib import Path

DIST_ROOT = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/server")
GUIDE_HTML_ROOT = Path("/var/www/app_exswapin_usr/data/www/app.exswaping.com/public/static/seo")
GUIDE_HTML_DEPLOY = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/storage/seo")
MARKER = "EXS_SSR_HOMEPAGE_GUIDE_LINKS_V1"

SSR_OLD = '}}catch(ex){}}if(b.includes("<not-found"))'

SSR_NEW = (
    '}}catch(ex){}}'
    'if(!b.includes("seo-homepage-guide-links")&&/rel=\\"canonical\\" href=\\"[^\\"]+\\/(ru|en)\\/?\\"/.test(b)){'
    'try{'
    'let hloc=b.match(/rel=\\"canonical\\" href=\\"[^\\"]+\\/(ru|en)\\/?\\"/i);'
    'let loc=hloc&&hloc[1]||"ru";'
    'let{readFileSync}=await import("node:fs");'
    'let sp="/var/www/exswaping_co_usr/data/www/exswaping.com/storage/seo/homepage-guide-links."+loc+".html";'
    'let sec=readFileSync(sp,{encoding:"utf8"});'
    'if(sec&&sec.includes("seo-homepage-guide-links"))b=b.replace("</body>",sec+"</body>");'
    '}catch(ex12){}}'
    'if(b.includes("<not-found"))'
)


def patch_server(text: str) -> tuple[str, list[str]]:
    if MARKER in text:
        return text, []
    if SSR_OLD not in text:
        return text, []
    text = text.replace(SSR_OLD, SSR_NEW, 1)
    text = text.rstrip() + f"\n/* {MARKER} */\n"
    return text, ["homepage_guide_links"]


def main() -> int:
    errors: list[str] = []
    for locale in ("ru", "en"):
        path = GUIDE_HTML_ROOT / f"homepage-guide-links.{locale}.html"
        if not path.is_file():
            errors.append(f"missing {path}")
        deploy = GUIDE_HTML_DEPLOY / f"homepage-guide-links.{locale}.html"
        GUIDE_HTML_DEPLOY.mkdir(parents=True, exist_ok=True)
        deploy.write_text(path.read_text(encoding="utf-8"), encoding="utf-8")
        deploy.chmod(0o644)

    server = DIST_ROOT / "server.mjs"
    if not server.exists():
        errors.append("missing server.mjs")

    if errors:
        for e in errors:
            print(f"[ERROR] {e}", file=sys.stderr)
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
