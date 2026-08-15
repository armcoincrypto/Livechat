#!/usr/bin/env python3
"""Guide Directory — SSR inject static directory at /ru/guides and /en/guides."""
from __future__ import annotations

import sys
from pathlib import Path

DIST_ROOT = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/server")
HTML_ROOT = Path("/var/www/app_exswapin_usr/data/www/app.exswaping.com/public/static/seo")
HTML_DEPLOY = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/storage/seo")
MARKER = "EXS_SSR_GUIDE_DIRECTORY_V1"

SSR_OLD = '}catch(ex13){}}if(b.includes("<not-found"))'

INJECT_RE_OLD = (
    'b=b.replace(/(<div class=\\"my-5 flex-1 mx-2[^\\"]*\\"[^>]*>\\s*<router-outlet[^>]*><\\/router-outlet>)/,'
    '"$1"+sec);'
)
INJECT_RE_NEW = (
    'b=b.replace(/(<div[^>]*class=\\"my-5 flex-1 mx-2[^\\"]*\\"[^>]*>\\s*<router-outlet[^>]*><\\/router-outlet>)/,'
    '"$1"+sec);'
)

SSR_NEW = (
    '}catch(ex13){}}'
    'if(!b.includes("seo-guide-directory")&&/rel=\\"canonical\\" href=\\"[^\\"]+\\/(ru|en)\\/guides\\/?\\"/.test(b)){'
    'try{'
    'let gloc=b.match(/rel=\\"canonical\\" href=\\"[^\\"]+\\/(ru|en)\\/guides\\/?\\"/i);'
    'let loc=gloc&&gloc[1]||"ru";'
    'let{readFileSync}=await import("node:fs");'
    'let sp="/var/www/exswaping_co_usr/data/www/exswaping.com/storage/seo/guide-directory."+loc+".html";'
    'let sec=readFileSync(sp,{encoding:"utf8"});'
    'if(sec&&sec.includes("seo-guide-directory")){'
    + INJECT_RE_NEW +
    'if(loc==="ru"){'
    'b=b.replace("<title></title>","<title>Руководства по обмену криптовалют | Exswaping</title>");'
    'b=b.replace(/<meta name=\\"description\\" content=\\"\\">/,"<meta name=\\"description\\" content=\\"Официальные руководства Exswaping: обмен USDT, безопасность, мониторинги и сети USDT.\\">");'
    '}else{'
    'b=b.replace("<title></title>","<title>Cryptocurrency Exchange Guides | Exswaping</title>");'
    'b=b.replace(/<meta name=\\"description\\" content=\\"\\">/,"<meta name=\\"description\\" content=\\"Official Exswaping guides for USDT exchange, TRC20, and bank card payouts.\\">");'
    '}}'
    '}catch(ex14){}}'
    'if(b.includes("<not-found"))'
)


def fix_inject_regex(text: str) -> tuple[str, list[str]]:
    if INJECT_RE_OLD not in text:
        return text, []
    return text.replace(INJECT_RE_OLD, INJECT_RE_NEW, 1), ["guide_directory_inject_fix"]


def patch_server(text: str) -> tuple[str, list[str]]:
    if MARKER in text:
        text, fixes = fix_inject_regex(text)
        return text, fixes
    if SSR_OLD not in text:
        return text, []
    text = text.replace(SSR_OLD, SSR_NEW, 1)
    text = text.rstrip() + f"\n/* {MARKER} */\n"
    return text, ["guide_directory"]


def main() -> int:
    errors: list[str] = []
    for locale in ("ru", "en"):
        src = HTML_ROOT / f"guide-directory.{locale}.html"
        if not src.is_file():
            errors.append(f"missing {src}")
            continue
        HTML_DEPLOY.mkdir(parents=True, exist_ok=True)
        dst = HTML_DEPLOY / f"guide-directory.{locale}.html"
        dst.write_text(src.read_text(encoding="utf-8"), encoding="utf-8")
        dst.chmod(0o644)

    server = DIST_ROOT / "server.mjs"
    if not server.exists():
        errors.append("missing server.mjs")

    if errors:
        for e in errors:
            print(f"[ERROR] {e}", file=sys.stderr)
        return 1

    text = server.read_text(encoding="utf-8")
    new_text, changes = patch_server(text)
    if not changes:
        if MARKER in text:
            print("[INFO] server.mjs already patched")
            return 0
        print("[ERROR] server.mjs anchor not matched", file=sys.stderr)
        return 1

    server.write_text(new_text, encoding="utf-8")
    print(f"[OK] server.mjs patched ({', '.join(changes)})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
