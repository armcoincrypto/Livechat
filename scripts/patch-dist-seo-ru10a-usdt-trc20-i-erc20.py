#!/usr/bin/env python3
"""
SEO-RU-10A — Dist patches for /ru/guides/usdt-trc20-i-erc20

App-repo deploy script (not preservation layer).
"""
from __future__ import annotations

import sys
from pathlib import Path

DIST_ROOT = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/server")
LANGS = ["ru", "en", "uk", "ka"]
CHUNK_MARKER = "EXS_GUIDE_SEO_RU10A_V1"
SSR_MARKER = "EXS_GUIDE_SSR_CONTENT_RU10A_V1"
BASE_CHUNK_MARKER = "EXS_GUIDE_SEO9F_V1"

META_OLD = (
    'else if(/\\/guides\\/obmen-usdt-na-kartu\\/?$/.test(this.exsStripLocalePrefixes(reqPath))&&loc==="ru"){'
    'this.exsSetTitle(D,"Обмен USDT на карту — вывод Tether на банковскую карту | Exswaping");'
    'this.exsSetMetaDesc(D,"Как обменять USDT на банковскую карту через Exswaping: CARDRUB, СБП, банки RUB, комиссии, сроки и безопасность выплаты.");'
    'try{let B=D.body,inj=B&&B.querySelector("h1[data-exs-seo-v2]"),all=B&&B.querySelectorAll("h1");'
    'inj&&all&&all.length>1&&inj.remove();all&&all.length===1&&all[0].setAttribute("data-exs-seo-v2","1")}catch{}}'
)

META_NEW = META_OLD + (
    'else if(/\\/guides\\/usdt-trc20-i-erc20\\/?$/.test(this.exsStripLocalePrefixes(reqPath))&&loc==="ru"){'
    'this.exsSetTitle(D,"USDT TRC20 и ERC20 — В чем разница и что выбрать | Exswaping");'
    'this.exsSetMetaDesc(D,"Сравнение USDT TRC20 и ERC20. Узнайте различия в комиссиях, скорости подтверждений, совместимости кошельков и сценариях использования.");'
    'try{let B=D.body,inj=B&&B.querySelector("h1[data-exs-seo-v2]"),all=B&&B.querySelectorAll("h1");'
    'inj&&all&&all.length>1&&inj.remove();all&&all.length===1&&all[0].setAttribute("data-exs-seo-v2","1")}catch{}}'
)

PAGES_OLD = (
    'if(/\\/pages\\/obmen-usdt-na-kartu\\/?$/.test(this.exsStripLocalePrefixes(reqPath))){'
    'let R3=D.querySelector(\'meta[name="robots"]\');'
    'R3||(R3=D.createElement("meta"),R3.setAttribute("name","robots"),D.head.appendChild(R3));'
    'R3.setAttribute("content","noindex,follow");R3.setAttribute("data-exs-seo-v2","1");'
    'let C3=D.querySelector(\'link[rel="canonical"]\');'
    'C3||(C3=D.createElement("link"),C3.setAttribute("rel","canonical"),D.head.appendChild(C3));'
    'C3.setAttribute("href",t+"/ru/guides/obmen-usdt-na-kartu");C3.setAttribute("data-exs-seo-v2","1");return}'
)

PAGES_NEW = PAGES_OLD + (
    'if(/\\/pages\\/usdt-trc20-i-erc20\\/?$/.test(this.exsStripLocalePrefixes(reqPath))){'
    'let R10=D.querySelector(\'meta[name="robots"]\');'
    'R10||(R10=D.createElement("meta"),R10.setAttribute("name","robots"),D.head.appendChild(R10));'
    'R10.setAttribute("content","noindex,follow");R10.setAttribute("data-exs-seo-v2","1");'
    'let C10=D.querySelector(\'link[rel="canonical"]\');'
    'C10||(C10=D.createElement("link"),C10.setAttribute("rel","canonical"),D.head.appendChild(C10));'
    'C10.setAttribute("href",t+"/ru/guides/usdt-trc20-i-erc20");C10.setAttribute("data-exs-seo-v2","1");return}'
)

SCHEMA_OLD = (
    '"mainEntity":mainEntityK}]});return}'
    'let guideUsdtEn=/\\/guides\\/usdt-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath));'
)

SCHEMA_NEW = (
    '"mainEntity":mainEntityK}]});return}'
    'let guideTrc20Erc20=/\\/guides\\/usdt-trc20-i-erc20\\/?$/.test(this.exsStripLocalePrefixes(reqPath));'
    'if(guideTrc20Erc20&&loc==="ru"){let pageUrl=t+"/ru/guides/usdt-trc20-i-erc20";'
    'let faqItems=[{q:"Можно ли отправить USDT TRC20 на ERC20-адрес?",a:"Нет. Это разные сети. Всегда сверяйте network в кошельке с заявкой."},'
    '{q:"Какая сеть дешевле — TRC20 или ERC20?",a:"Часто TRC20 дешевле при отправке, но gas Ethereum меняется. Сравните комиссию в кошельке перед переводом."},'
    '{q:"Какую сеть выбрать для обмена USDT на рубли?",a:"Ту, где у вас лежит USDT и которая доступна в калькуляторе для нужного RUB-направления."},'
    '{q:"Нужно ли менять сеть перед обменом на Exswaping?",a:"Нет, если вы выбираете пару под текущую сеть."},'
    '{q:"Сколько подтверждений ждать?",a:"Зависит от направления. Ориентиры на странице пары после создания заявки."},'
    '{q:"Нужна ли верификация?",a:"Заявка через сайт. В отдельных случаях — проверка по политике AML/KYC."},'
    '{q:"Где обменять USDT TRC20 после выбора сети?",a:"В калькуляторе Exswaping или по ссылкам из гида USDT TRC20."},'
    '{q:"Что делать, если отправил не в той сети?",a:"Сразу напишите в поддержку с номером заявки и хешем транзакции."}];'
    'let mainEntityTE=faqItems.map(it=>({"@type":"Question","name":it.q,"acceptedAnswer":{"@type":"Answer","text":it.a}}));'
    'this.exsInjectJsonLd(D,{"@context":"https://schema.org","@graph":['
    '{"@type":"BreadcrumbList","itemListElement":['
    '{"@type":"ListItem","position":1,"name":"Exswaping","item":t+"/ru/"},'
    '{"@type":"ListItem","position":2,"name":"Guides","item":t+"/ru/guides/obmen-usdt-trc20"},'
    '{"@type":"ListItem","position":3,"name":"USDT TRC20 и ERC20","item":pageUrl}'
    "]},"
    '{"@type":"Article","headline":"USDT TRC20 и ERC20: в чём разница","description":"Сравнение USDT TRC20 и ERC20: комиссии, скорость, кошельки и сценарии обмена через Exswaping.",'
    '"author":{"@type":"Organization","name":"Exswaping","url":t+"/"},"publisher":{"@type":"Organization","name":"Exswaping","url":t+"/","logo":{"@type":"ImageObject","url":t+"/images/favicons/favicon-96x96.png"}},'
    '"mainEntityOfPage":{"@type":"WebPage","@id":pageUrl},"inLanguage":"ru-RU"},'
    '{"@type":"FAQPage","mainEntity":mainEntityTE}'
    "]});return}"
    'let guideUsdtEn=/\\/guides\\/usdt-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath));'
)

SSR_OLD = (
    'if(b.includes("/guides/usdt-to-bank-card")&&b.includes("page-usdt-to-bank-card")&&!/<h2/i.test(b)){'
    'try{let js6=b.match(/<script id=\\"iexexchanger-state\\" type=\\"application\\/json\\">([\\s\\S]*?)<\\/script>/);'
    'if(js6){let st6=JSON.parse(js6[1]),pg6=st6["page-usdt-to-bank-card"];'
    'if(pg6&&pg6.content){b=b.replace(/(<div class=\\"prose my-6 formatting__text[^\\"]*\\"[^>]*>)\\s*<\\/div>/,'
    '"$1"+pg6.content+"</div>")}}}catch(ex6){}}'
)

SSR_NEW = SSR_OLD + (
    'if(b.includes("/guides/usdt-trc20-i-erc20")&&b.includes("page-usdt-trc20-i-erc20")&&!/<h2/i.test(b)){'
    'try{let js7=b.match(/<script id=\\"iexexchanger-state\\" type=\\"application\\/json\\">([\\s\\S]*?)<\\/script>/);'
    'if(js7){let st7=JSON.parse(js7[1]),pg7=st7["page-usdt-trc20-i-erc20"];'
    'if(pg7&&pg7.content){b=b.replace(/(<div class=\\"prose my-6 formatting__text[^\\"]*\\"[^>]*>)\\s*<\\/div>/,'
    '"$1"+pg7.content+"</div>")}}}catch(ex7){}}'
)


def patch_chunk(text: str) -> tuple[str, list[str]]:
    if BASE_CHUNK_MARKER not in text:
        return text, []
    if CHUNK_MARKER in text:
        return text, []

    changes: list[str] = []
    if META_OLD in text and "guides\\/usdt-trc20-i-erc20" not in text.split("else if(/\\/guides\\/usdt-exchange")[0]:
        text = text.replace(META_OLD, META_NEW, 1)
        changes.append("metadata")
    if PAGES_OLD in text and "pages\\/usdt-trc20-i-erc20" not in text:
        text = text.replace(PAGES_OLD, PAGES_NEW, 1)
        changes.append("pages_alias")
    if SCHEMA_OLD in text and "let guideTrc20Erc20=" not in text:
        text = text.replace(SCHEMA_OLD, SCHEMA_NEW, 1)
        changes.append("schema")
    if changes:
        text = text.rstrip() + f"\n/* {CHUNK_MARKER} */\n"
    return text, changes


def patch_server(text: str) -> tuple[str, list[str]]:
    if SSR_MARKER in text or "page-usdt-trc20-i-erc20" in text:
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
