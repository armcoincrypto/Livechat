#!/usr/bin/env python3
"""
SEO-EN-3 — Dist patches for /en/guides/usdt-trc20-exchange

Applies directly to live dist (app-repo deploy script; not preservation layer).
Extends SEO-EN-2 blocks idempotently.

Marker: EXS_GUIDE_SEO_EN3_V1 (chunk) / EXS_GUIDE_SSR_CONTENT_SEO_EN3_V1 (server.mjs)
"""
from __future__ import annotations

import sys
from pathlib import Path

DIST_ROOT = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/server")
LANGS = ["ru", "en", "uk", "ka"]
CHUNK_MARKER = "EXS_GUIDE_SEO_EN3_V1"
SSR_MARKER = "EXS_GUIDE_SSR_CONTENT_SEO_EN3_V1"
BASE_CHUNK_MARKER = "EXS_GUIDE_SEO_EN2_V1"

DONE = "usdt-trc20-exchange"

GUARD_OLD = (
    'loc!=="ru"&&!(/\\/guides\\/usdt-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath))&&loc==="en")'
)
GUARD_NEW = (
    'loc!=="ru"&&!(loc==="en"&&(/\\/guides\\/usdt-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath))'
    '||/\\/guides\\/usdt-trc20-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath))))'
)

PAGES_OLD = (
    'if(/\\/pages\\/usdt-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath))){'
    'let R5=D.querySelector(\'meta[name="robots"]\');'
    'R5||(R5=D.createElement("meta"),R5.setAttribute("name","robots"),D.head.appendChild(R5));'
    'R5.setAttribute("content","noindex,follow");R5.setAttribute("data-exs-seo-v2","1");'
    'let C5=D.querySelector(\'link[rel="canonical"]\');'
    'C5||(C5=D.createElement("link"),C5.setAttribute("rel","canonical"),D.head.appendChild(C5));'
    'C5.setAttribute("href",t+"/en/guides/usdt-exchange");C5.setAttribute("data-exs-seo-v2","1");return}'
)

PAGES_NEW = PAGES_OLD + (
    'if(/\\/pages\\/usdt-trc20-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath))){'
    'let R6=D.querySelector(\'meta[name="robots"]\');'
    'R6||(R6=D.createElement("meta"),R6.setAttribute("name","robots"),D.head.appendChild(R6));'
    'R6.setAttribute("content","noindex,follow");R6.setAttribute("data-exs-seo-v2","1");'
    'let C6=D.querySelector(\'link[rel="canonical"]\');'
    'C6||(C6=D.createElement("link"),C6.setAttribute("rel","canonical"),D.head.appendChild(C6));'
    'C6.setAttribute("href",t+"/en/guides/usdt-trc20-exchange");C6.setAttribute("data-exs-seo-v2","1");return}'
)

META_OLD = (
    'else if(/\\/guides\\/usdt-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath))&&loc==="en"){'
    'this.exsSetTitle(D,"USDT Exchange Guide — How to Exchange Tether Safely | Exswaping");'
    'this.exsSetMetaDesc(D,"Learn how to exchange USDT safely using Exswaping. Understand networks, fees, confirmations, exchange directions, and security best practices.");'
    'let R4=D.querySelector(\'meta[name="robots"]\');'
    'R4||(R4=D.createElement("meta"),R4.setAttribute("name","robots"),D.head.appendChild(R4));'
    'R4.setAttribute("content","index,follow");R4.setAttribute("data-exs-seo-v2","1");'
    'let C4=D.querySelector(\'link[rel="canonical"]\');'
    'C4||(C4=D.createElement("link"),C4.setAttribute("rel","canonical"),D.head.appendChild(C4));'
    'C4.setAttribute("href",t+"/en/guides/usdt-exchange");C4.setAttribute("data-exs-seo-v2","1");'
    'try{let B=D.body,inj=B&&B.querySelector("h1[data-exs-seo-v2]"),all=B&&B.querySelectorAll("h1");'
    'inj&&all&&all.length>1&&inj.remove();all&&all.length===1&&all[0].setAttribute("data-exs-seo-v2","1")}catch{}}'
)

META_NEW = META_OLD + (
    'else if(/\\/guides\\/usdt-trc20-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath))&&loc==="en"){'
    'this.exsSetTitle(D,"USDT TRC20 Exchange Guide — How to Exchange Tether on TRON Safely | Exswaping");'
    'this.exsSetMetaDesc(D,"Learn how to exchange USDT TRC20 safely. Understand TRON confirmations, fees, exchange directions, wallet compatibility, and security best practices.");'
    'let R7=D.querySelector(\'meta[name="robots"]\');'
    'R7||(R7=D.createElement("meta"),R7.setAttribute("name","robots"),D.head.appendChild(R7));'
    'R7.setAttribute("content","index,follow");R7.setAttribute("data-exs-seo-v2","1");'
    'let C7=D.querySelector(\'link[rel="canonical"]\');'
    'C7||(C7=D.createElement("link"),C7.setAttribute("rel","canonical"),D.head.appendChild(C7));'
    'C7.setAttribute("href",t+"/en/guides/usdt-trc20-exchange");C7.setAttribute("data-exs-seo-v2","1");'
    'try{let B=D.body,inj=B&&B.querySelector("h1[data-exs-seo-v2]"),all=B&&B.querySelectorAll("h1");'
    'inj&&all&&all.length>1&&inj.remove();all&&all.length===1&&all[0].setAttribute("data-exs-seo-v2","1")}catch{}}'
)

SCHEMA_OLD = (
    '{"@type":"FAQPage","mainEntity":mainEntityE}]});return}'
    'let faq=/\\/faq\\/?$/.test(this.exsStripLocalePrefixes(reqPath));'
)

SCHEMA_NEW = (
    '{"@type":"FAQPage","mainEntity":mainEntityE}]});return}'
    'let guideUsdtTrc20En=/\\/guides\\/usdt-trc20-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath));'
    'if(guideUsdtTrc20En&&loc==="en"){let pageUrl=t+"/en/guides/usdt-trc20-exchange";'
    'let faqItems=[{q:"What is USDT TRC20 in simple terms?",a:"It is Tether (USDT) on the TRON blockchain. TRC20 describes the token standard and network."},'
    '{q:"Is USDT TRC20 the same as USDT on Ethereum?",a:"No. Same brand name, different blockchains and addresses. Use the network your order specifies."},'
    '{q:"How many TRON confirmations does Exswaping require?",a:"It depends on the pair and current processing rules. Check the pair page and order status."},'
    '{q:"Who pays the TRON transfer fee?",a:"You pay TRON network costs when sending from your wallet. The exchange quote is separate."},'
    '{q:"Can I exchange USDT TRC20 to local fiat?",a:"Only where a fiat route is listed in the calculator when available."},'
    '{q:"What if I sent USDT TRC20 to the wrong address?",a:"Contact support immediately with order ID and TXID. Recovery is not guaranteed."},'
    '{q:"Do I need verification to exchange USDT TRC20?",a:"Orders are placed through the website. Additional checks may apply under the AML/KYC policy."},'
    '{q:"Where should I start if I am new to USDT?",a:"Read the USDT Exchange Guide, then return here if your tokens are on TRON."}];'
    'let mainEntityT2=faqItems.map(it=>({"@type":"Question","name":it.q,"acceptedAnswer":{"@type":"Answer","text":it.a}}));'
    'this.exsInjectJsonLd(D,{"@context":"https://schema.org","@graph":['
    '{"@type":"BreadcrumbList","itemListElement":['
    '{"@type":"ListItem","position":1,"name":"Exswaping","item":t+"/en/"},'
    '{"@type":"ListItem","position":2,"name":"Guides","item":t+"/en/guides/usdt-exchange"},'
    '{"@type":"ListItem","position":3,"name":"USDT TRC20 Exchange Guide","item":pageUrl}'
    "]},"
    '{"@type":"Article","headline":"USDT TRC20 Exchange Guide","description":"Learn how to exchange USDT TRC20 safely: TRON confirmations, fees, directions, wallet compatibility, and security.",'
    '"author":{"@type":"Organization","name":"Exswaping","url":t+"/"},"publisher":{"@type":"Organization","name":"Exswaping","url":t+"/","logo":{"@type":"ImageObject","url":t+"/images/favicons/favicon-96x96.png"}},'
    '"mainEntityOfPage":{"@type":"WebPage","@id":pageUrl},"inLanguage":"en-US"},'
    '{"@type":"FAQPage","mainEntity":mainEntityT2}'
    "]});return}"
    'let faq=/\\/faq\\/?$/.test(this.exsStripLocalePrefixes(reqPath));'
)

SSR_OLD = (
    'if(b.includes("/guides/usdt-exchange")&&b.includes("page-usdt-exchange")&&!/<h2/i.test(b)){'
    'try{let js4=b.match(/<script id=\\"iexexchanger-state\\" type=\\"application\\/json\\">([\\s\\S]*?)<\\/script>/);'
    'if(js4){let st4=JSON.parse(js4[1]),pg4=st4["page-usdt-exchange"];'
    'if(pg4&&pg4.content){b=b.replace(/(<div class=\\"prose my-6 formatting__text[^\\"]*\\"[^>]*>)\\s*<\\/div>/,'
    '"$1"+pg4.content+"</div>")}}}catch(ex4){}}'
)

SSR_NEW = SSR_OLD + (
    'if(b.includes("/guides/usdt-trc20-exchange")&&b.includes("page-usdt-trc20-exchange")&&!/<h2/i.test(b)){'
    'try{let js5=b.match(/<script id=\\"iexexchanger-state\\" type=\\"application\\/json\\">([\\s\\S]*?)<\\/script>/);'
    'if(js5){let st5=JSON.parse(js5[1]),pg5=st5["page-usdt-trc20-exchange"];'
    'if(pg5&&pg5.content){b=b.replace(/(<div class=\\"prose my-6 formatting__text[^\\"]*\\"[^>]*>)\\s*<\\/div>/,'
    '"$1"+pg5.content+"</div>")}}}catch(ex5){}}'
)


def patch_chunk(text: str) -> tuple[str, list[str]]:
    if BASE_CHUNK_MARKER not in text:
        return text, []
    if CHUNK_MARKER in text and DONE in text.split("let faq=")[0][-500:]:
        return text, []

    changes: list[str] = []
    if GUARD_OLD in text and GUARD_NEW not in text:
        text = text.replace(GUARD_OLD, GUARD_NEW, 1)
        changes.append("guard")
    if PAGES_OLD in text and "pages\\/usdt-trc20-exchange" not in text:
        text = text.replace(PAGES_OLD, PAGES_NEW, 1)
        changes.append("pages_alias")
    if META_OLD in text and "guides\\/usdt-trc20-exchange" not in text.split("exsApplySeo6Schema")[0][-1200:]:
        text = text.replace(META_OLD, META_NEW, 1)
        changes.append("metadata")
    if SCHEMA_OLD in text and "let guideUsdtTrc20En=" not in text:
        text = text.replace(SCHEMA_OLD, SCHEMA_NEW, 1)
        changes.append("schema")
    if changes and CHUNK_MARKER not in text:
        text = text.rstrip() + f"\n/* {CHUNK_MARKER} */\n"
    return text, changes


def patch_server(text: str) -> tuple[str, list[str]]:
    if SSR_MARKER in text or "page-usdt-trc20-exchange" in text:
        return text, []
    if SSR_OLD not in text:
        return text, []
    text = text.replace(SSR_OLD, SSR_NEW, 1)
    text = text.rstrip() + f"\n/* {SSR_MARKER} */\n"
    return text, ["ssr"]


def main() -> int:
    errors: list[str] = []
    chunk_patched = 0
    chunk_skipped = 0

    for lang in LANGS:
        path = DIST_ROOT / lang / "chunk-3RSX4ZSH.mjs"
        if not path.exists():
            errors.append(f"{lang}: missing chunk")
            continue
        text = path.read_text(encoding="utf-8")
        if CHUNK_MARKER in text:
            chunk_skipped += 1
            continue
        new_text, changes = patch_chunk(text)
        if not changes:
            errors.append(f"{lang}: chunk anchors not matched")
            continue
        path.write_text(new_text, encoding="utf-8")
        print(f"[OK] {lang}: chunk patched ({', '.join(changes)})")
        chunk_patched += 1

    server = DIST_ROOT / "server.mjs"
    if not server.exists():
        errors.append("missing server.mjs")
    else:
        stext = server.read_text(encoding="utf-8")
        if SSR_MARKER in stext:
            print("[INFO] server.mjs already patched")
        else:
            new_stext, schanges = patch_server(stext)
            if not schanges:
                errors.append("server.mjs anchor not matched")
            else:
                server.write_text(new_stext, encoding="utf-8")
                print(f"[OK] server.mjs patched ({', '.join(schanges)})")

    print(f"[SUMMARY] chunk_patched={chunk_patched}, chunk_skipped={chunk_skipped}, errors={len(errors)}")
    if errors:
        for e in errors:
            print(f"[ERROR] {e}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
