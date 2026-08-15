#!/usr/bin/env python3
"""
SEO-EN-4 — Dist patches for /en/guides/usdt-to-bank-card

App-repo deploy script (not preservation layer). Extends SEO-EN-3 blocks idempotently.

Marker: EXS_GUIDE_SEO_EN4_V1 / EXS_GUIDE_SSR_CONTENT_SEO_EN4_V1
"""
from __future__ import annotations

import sys
from pathlib import Path

DIST_ROOT = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/server")
LANGS = ["ru", "en", "uk", "ka"]
CHUNK_MARKER = "EXS_GUIDE_SEO_EN4_V1"
SSR_MARKER = "EXS_GUIDE_SSR_CONTENT_SEO_EN4_V1"
BASE_CHUNK_MARKER = "EXS_GUIDE_SEO_EN3_V1"

GUARD_OLD = (
    'loc!=="ru"&&!(loc==="en"&&(/\\/guides\\/usdt-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath))'
    '||/\\/guides\\/usdt-trc20-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath))))'
)
GUARD_NEW = (
    'loc!=="ru"&&!(loc==="en"&&(/\\/guides\\/usdt-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath))'
    '||/\\/guides\\/usdt-trc20-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath))'
    '||/\\/guides\\/usdt-to-bank-card\\/?$/.test(this.exsStripLocalePrefixes(reqPath))))'
)

PAGES_OLD = (
    'if(/\\/pages\\/usdt-trc20-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath))){'
    'let R6=D.querySelector(\'meta[name="robots"]\');'
    'R6||(R6=D.createElement("meta"),R6.setAttribute("name","robots"),D.head.appendChild(R6));'
    'R6.setAttribute("content","noindex,follow");R6.setAttribute("data-exs-seo-v2","1");'
    'let C6=D.querySelector(\'link[rel="canonical"]\');'
    'C6||(C6=D.createElement("link"),C6.setAttribute("rel","canonical"),D.head.appendChild(C6));'
    'C6.setAttribute("href",t+"/en/guides/usdt-trc20-exchange");C6.setAttribute("data-exs-seo-v2","1");return}'
)

PAGES_NEW = PAGES_OLD + (
    'if(/\\/pages\\/usdt-to-bank-card\\/?$/.test(this.exsStripLocalePrefixes(reqPath))){'
    'let R8=D.querySelector(\'meta[name="robots"]\');'
    'R8||(R8=D.createElement("meta"),R8.setAttribute("name","robots"),D.head.appendChild(R8));'
    'R8.setAttribute("content","noindex,follow");R8.setAttribute("data-exs-seo-v2","1");'
    'let C8=D.querySelector(\'link[rel="canonical"]\');'
    'C8||(C8=D.createElement("link"),C8.setAttribute("rel","canonical"),D.head.appendChild(C8));'
    'C8.setAttribute("href",t+"/en/guides/usdt-to-bank-card");C8.setAttribute("data-exs-seo-v2","1");return}'
)

META_OLD = (
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

META_NEW = META_OLD + (
    'else if(/\\/guides\\/usdt-to-bank-card\\/?$/.test(this.exsStripLocalePrefixes(reqPath))&&loc==="en"){'
    'this.exsSetTitle(D,"USDT to Bank Card Guide — How to Withdraw Tether Safely | Exswaping");'
    'this.exsSetMetaDesc(D,"Learn how to exchange USDT to a bank card safely. Understand payout methods, confirmations, fees, processing times, and security considerations.");'
    'let R9=D.querySelector(\'meta[name="robots"]\');'
    'R9||(R9=D.createElement("meta"),R9.setAttribute("name","robots"),D.head.appendChild(R9));'
    'R9.setAttribute("content","index,follow");R9.setAttribute("data-exs-seo-v2","1");'
    'let C9=D.querySelector(\'link[rel="canonical"]\');'
    'C9||(C9=D.createElement("link"),C9.setAttribute("rel","canonical"),D.head.appendChild(C9));'
    'C9.setAttribute("href",t+"/en/guides/usdt-to-bank-card");C9.setAttribute("data-exs-seo-v2","1");'
    'try{let B=D.body,inj=B&&B.querySelector("h1[data-exs-seo-v2]"),all=B&&B.querySelectorAll("h1");'
    'inj&&all&&all.length>1&&inj.remove();all&&all.length===1&&all[0].setAttribute("data-exs-seo-v2","1")}catch{}}'
)

SCHEMA_OLD = (
    '{"@type":"FAQPage","mainEntity":mainEntityT2}]});return}'
    'let faq=/\\/faq\\/?$/.test(this.exsStripLocalePrefixes(reqPath));'
)

SCHEMA_NEW = (
    '{"@type":"FAQPage","mainEntity":mainEntityT2}]});return}'
    'let guideUsdtCardEn=/\\/guides\\/usdt-to-bank-card\\/?$/.test(this.exsStripLocalePrefixes(reqPath));'
    'if(guideUsdtCardEn&&loc==="en"){let pageUrl=t+"/en/guides/usdt-to-bank-card";'
    'let faqItems=[{q:"Can I send USDT directly to my card number on the blockchain?",a:"No. USDT moves to the deposit address in your order; card credit happens through fiat payout after processing."},'
    '{q:"Which USDT network should I use for card payout?",a:"Use the network where your USDT is held and where the calculator lists a matching pair."},'
    '{q:"How long until money appears on my card?",a:"It depends on confirmations, order review, and the payout rail. Pair page estimates are not guaranteed deadlines."},'
    '{q:"Does Exswaping charge a card delivery fee?",a:"The calculator shows the exchange side. Your bank may apply separate incoming transfer fees."},'
    '{q:"Is verification required for USDT to card?",a:"Orders are placed through the website. Additional checks may apply under the AML/KYC policy in some cases."},'
    '{q:"What if I entered the wrong card details?",a:"Contact support immediately with your order ID. Do not send duplicate USDT without instruction."},'
    '{q:"Can any country use every card route?",a:"No. Routes depend on calculator availability and compliance rules."},'
    '{q:"Where do I see the live rate for USDT to card?",a:"In the Exswaping calculator after selecting USDT and an available card or fiat payout pair."}];'
    'let mainEntityC=faqItems.map(it=>({"@type":"Question","name":it.q,"acceptedAnswer":{"@type":"Answer","text":it.a}}));'
    'this.exsInjectJsonLd(D,{"@context":"https://schema.org","@graph":['
    '{"@type":"BreadcrumbList","itemListElement":['
    '{"@type":"ListItem","position":1,"name":"Exswaping","item":t+"/en/"},'
    '{"@type":"ListItem","position":2,"name":"Guides","item":t+"/en/guides/usdt-exchange"},'
    '{"@type":"ListItem","position":3,"name":"USDT to Bank Card Guide","item":pageUrl}'
    "]},"
    '{"@type":"Article","headline":"USDT to Bank Card Guide","description":"Learn how to exchange USDT to a bank card safely: payout methods, confirmations, fees, processing times, and security.",'
    '"author":{"@type":"Organization","name":"Exswaping","url":t+"/"},"publisher":{"@type":"Organization","name":"Exswaping","url":t+"/","logo":{"@type":"ImageObject","url":t+"/images/favicons/favicon-96x96.png"}},'
    '"mainEntityOfPage":{"@type":"WebPage","@id":pageUrl},"inLanguage":"en-US"},'
    '{"@type":"FAQPage","mainEntity":mainEntityC}'
    "]});return}"
    'let faq=/\\/faq\\/?$/.test(this.exsStripLocalePrefixes(reqPath));'
)

SSR_OLD = (
    'if(b.includes("/guides/usdt-trc20-exchange")&&b.includes("page-usdt-trc20-exchange")&&!/<h2/i.test(b)){'
    'try{let js5=b.match(/<script id=\\"iexexchanger-state\\" type=\\"application\\/json\\">([\\s\\S]*?)<\\/script>/);'
    'if(js5){let st5=JSON.parse(js5[1]),pg5=st5["page-usdt-trc20-exchange"];'
    'if(pg5&&pg5.content){b=b.replace(/(<div class=\\"prose my-6 formatting__text[^\\"]*\\"[^>]*>)\\s*<\\/div>/,'
    '"$1"+pg5.content+"</div>")}}}catch(ex5){}}'
)

SSR_NEW = SSR_OLD + (
    'if(b.includes("/guides/usdt-to-bank-card")&&b.includes("page-usdt-to-bank-card")&&!/<h2/i.test(b)){'
    'try{let js6=b.match(/<script id=\\"iexexchanger-state\\" type=\\"application\\/json\\">([\\s\\S]*?)<\\/script>/);'
    'if(js6){let st6=JSON.parse(js6[1]),pg6=st6["page-usdt-to-bank-card"];'
    'if(pg6&&pg6.content){b=b.replace(/(<div class=\\"prose my-6 formatting__text[^\\"]*\\"[^>]*>)\\s*<\\/div>/,'
    '"$1"+pg6.content+"</div>")}}}catch(ex6){}}'
)


def patch_chunk(text: str) -> tuple[str, list[str]]:
    if BASE_CHUNK_MARKER not in text:
        return text, []
    if CHUNK_MARKER in text:
        return text, []

    changes: list[str] = []
    if GUARD_OLD in text and "usdt-to-bank-card" not in text.split("let R=D.querySelector")[0][-400:]:
        text = text.replace(GUARD_OLD, GUARD_NEW, 1)
        changes.append("guard")
    if PAGES_OLD in text and "pages\\/usdt-to-bank-card" not in text:
        text = text.replace(PAGES_OLD, PAGES_NEW, 1)
        changes.append("pages_alias")
    if META_OLD in text and "guides\\/usdt-to-bank-card" not in text.split("{let bp=")[0][-800:]:
        text = text.replace(META_OLD, META_NEW, 1)
        changes.append("metadata")
    if SCHEMA_OLD in text and "let guideUsdtCardEn=" not in text:
        text = text.replace(SCHEMA_OLD, SCHEMA_NEW, 1)
        changes.append("schema")
    if changes:
        text = text.rstrip() + f"\n/* {CHUNK_MARKER} */\n"
    return text, changes


def patch_server(text: str) -> tuple[str, list[str]]:
    if SSR_MARKER in text or "page-usdt-to-bank-card" in text:
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
