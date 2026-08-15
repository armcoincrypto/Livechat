#!/usr/bin/env python3
"""SEO-AUTHORITY-4 — Dist patches for /ru/guides/seti-usdt."""
from __future__ import annotations

import sys
from pathlib import Path

DIST_ROOT = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/server")
LANGS = ["ru", "en", "uk", "ka"]
CHUNK_MARKER = "EXS_GUIDE_SEO_AUTHORITY4_V1"
SSR_MARKER = "EXS_GUIDE_SSR_CONTENT_AUTHORITY4_V1"
BASE_CHUNK_MARKER = "EXS_GUIDE_SEO_AUTHORITY3_V1"

META_OLD = (
    'else if(/\\/guides\\/monitoring-kriptovalyutnyh-obmennikov\\/?$/.test(this.exsStripLocalePrefixes(reqPath))&&loc==="ru"){'
    'this.exsSetTitle(D,"Мониторинги обменников — как оценить сервис | Exswaping");'
    'this.exsSetMetaDesc(D,"Как работают мониторинги обменников: BestChange, Crypto.ru, Exnode, ExchangeSumo. Как оценить отзывы и безопасно перейти к обмену на exswaping.com.");'
    'try{let B=D.body,inj=B&&B.querySelector("h1[data-exs-seo-v2]"),all=B&&B.querySelectorAll("h1");'
    'inj&&all&&all.length>1&&inj.remove();all&&all.length===1&&all[0].setAttribute("data-exs-seo-v2","1")}catch{}}'
)

META_NEW = META_OLD + (
    'else if(/\\/guides\\/seti-usdt\\/?$/.test(this.exsStripLocalePrefixes(reqPath))&&loc==="ru"){'
    'this.exsSetTitle(D,"Сети USDT — TRC20, ERC20, BEP20 | Exswaping");'
    'this.exsSetMetaDesc(D,"Экосистема сетей USDT: TRC20, ERC20, BEP20. Комиссии, скорость, кошельки, выбор сети и типичные ошибки перед обменом на exswaping.com.");'
    'try{let B=D.body,inj=B&&B.querySelector("h1[data-exs-seo-v2]"),all=B&&B.querySelectorAll("h1");'
    'inj&&all&&all.length>1&&inj.remove();all&&all.length===1&&all[0].setAttribute("data-exs-seo-v2","1")}catch{}}'
)

PAGES_OLD = (
    'if(/\\/pages\\/monitoring-kriptovalyutnyh-obmennikov\\/?$/.test(this.exsStripLocalePrefixes(reqPath))){'
    'let R12=D.querySelector(\'meta[name="robots"]\');'
    'R12||(R12=D.createElement("meta"),R12.setAttribute("name","robots"),D.head.appendChild(R12));'
    'R12.setAttribute("content","noindex,follow");R12.setAttribute("data-exs-seo-v2","1");'
    'let C12=D.querySelector(\'link[rel="canonical"]\');'
    'C12||(C12=D.createElement("link"),C12.setAttribute("rel","canonical"),D.head.appendChild(C12));'
    'C12.setAttribute("href",t+"/ru/guides/monitoring-kriptovalyutnyh-obmennikov");C12.setAttribute("data-exs-seo-v2","1");return}'
)

PAGES_NEW = PAGES_OLD + (
    'if(/\\/pages\\/seti-usdt\\/?$/.test(this.exsStripLocalePrefixes(reqPath))){'
    'let R13=D.querySelector(\'meta[name="robots"]\');'
    'R13||(R13=D.createElement("meta"),R13.setAttribute("name","robots"),D.head.appendChild(R13));'
    'R13.setAttribute("content","noindex,follow");R13.setAttribute("data-exs-seo-v2","1");'
    'let C13=D.querySelector(\'link[rel="canonical"]\');'
    'C13||(C13=D.createElement("link"),C13.setAttribute("rel","canonical"),D.head.appendChild(C13));'
    'C13.setAttribute("href",t+"/ru/guides/seti-usdt");C13.setAttribute("data-exs-seo-v2","1");return}'
)

SCHEMA_OLD = (
    '"mainEntity":mainEntityMO}]});return}'
    'let guideUsdtEn=/\\/guides\\/usdt-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath));'
)

SCHEMA_NEW = (
    '"mainEntity":mainEntityMO}]});return}'
    'let guideUsdtNetworks=/\\/guides\\/seti-usdt\\/?$/.test(this.exsStripLocalePrefixes(reqPath));'
    'if(guideUsdtNetworks&&loc==="ru"){let pageUrl=t+"/ru/guides/seti-usdt";'
    'let faqItems=[{q:"Что такое USDT?",a:"Стейблкоин в нескольких сетях. Сеть заявки = сеть кошелька."},'
    '{q:"Можно ли TRC20 на ERC20-адрес?",a:"Нет. Сети не взаимозаменяемы."},'
    '{q:"Какая сеть дешевле?",a:"Зависит от баланса и комиссий сегодня. Exswaping не ранжирует сети."},'
    '{q:"Где обмен USDT на рубли?",a:"Заявка на exswaping.com в разделе guides."},'
    '{q:"Нужен ли AML/KYC?",a:"По политике сервиса в отдельных случаях."},'
    '{q:"Где инструкция?",a:"Страница instructions на exswaping.com."},'
    '{q:"Где trust-гид?",a:"bezopasnyj-kriptoobmen на exswaping.com."},'
    '{q:"Куда писать при ошибке?",a:"Официальные контакты с номером заявки."}];'
    'let mainEntityUN=faqItems.map(it=>({"@type":"Question","name":it.q,"acceptedAnswer":{"@type":"Answer","text":it.a}}));'
    'this.exsInjectJsonLd(D,{"@context":"https://schema.org","@graph":['
    '{"@type":"BreadcrumbList","itemListElement":['
    '{"@type":"ListItem","position":1,"name":"Exswaping","item":t+"/ru/"},'
    '{"@type":"ListItem","position":2,"name":"Guides","item":t+"/ru/guides/obmen-usdt-trc20"},'
    '{"@type":"ListItem","position":3,"name":"Сети USDT","item":pageUrl}'
    "]},"
    '{"@type":"Article","headline":"Сети USDT: TRC20, ERC20, BEP20","description":"Экосистема сетей USDT: комиссии, скорость, кошельки и выбор сети перед обменом.",'
    '"author":{"@type":"Organization","name":"Exswaping","url":t+"/"},"publisher":{"@type":"Organization","name":"Exswaping","url":t+"/","logo":{"@type":"ImageObject","url":t+"/images/favicons/favicon-96x96.png"}},'
    '"mainEntityOfPage":{"@type":"WebPage","@id":pageUrl},"inLanguage":"ru-RU"},'
    '{"@type":"FAQPage","mainEntity":mainEntityUN}'
    "]});return}"
    'let guideUsdtEn=/\\/guides\\/usdt-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath));'
)

SSR_OLD = (
    'if(b.includes("/guides/monitoring-kriptovalyutnyh-obmennikov")&&b.includes("page-monitoring-kriptovalyutnyh-obmennikov")&&!/<h2/i.test(b)){'
    'try{let js9=b.match(/<script id=\\"iexexchanger-state\\" type=\\"application\\/json\\">([\\s\\S]*?)<\\/script>/);'
    'if(js9){let st9=JSON.parse(js9[1]),pg9=st9["page-monitoring-kriptovalyutnyh-obmennikov"];'
    'if(pg9&&pg9.content){b=b.replace(/(<div class=\\"prose my-6 formatting__text[^\\"]*\\"[^>]*>)\\s*<\\/div>/,'
    '"$1"+pg9.content+"</div>")}}}catch(ex9){}}'
)

SSR_NEW = SSR_OLD + (
    'if(b.includes("/guides/seti-usdt")&&b.includes("page-seti-usdt")&&!/<h2/i.test(b)){'
    'try{let js10=b.match(/<script id=\\"iexexchanger-state\\" type=\\"application\\/json\\">([\\s\\S]*?)<\\/script>/);'
    'if(js10){let st10=JSON.parse(js10[1]),pg10=st10["page-seti-usdt"];'
    'if(pg10&&pg10.content){b=b.replace(/(<div class=\\"prose my-6 formatting__text[^\\"]*\\"[^>]*>)\\s*<\\/div>/,'
    '"$1"+pg10.content+"</div>")}}}catch(ex10){}}'
)


def patch_chunk(text: str) -> tuple[str, list[str]]:
    if BASE_CHUNK_MARKER not in text:
        return text, []
    if CHUNK_MARKER in text:
        return text, []

    changes: list[str] = []
    if META_OLD in text and "guides\\/seti-usdt" not in text.split("else if(/\\/guides\\/usdt-exchange")[0]:
        text = text.replace(META_OLD, META_NEW, 1)
        changes.append("metadata")
    if PAGES_OLD in text and "pages\\/seti-usdt" not in text:
        text = text.replace(PAGES_OLD, PAGES_NEW, 1)
        changes.append("pages_alias")
    if SCHEMA_OLD in text and "let guideUsdtNetworks=" not in text:
        text = text.replace(SCHEMA_OLD, SCHEMA_NEW, 1)
        changes.append("schema")
    if changes:
        text = text.rstrip() + f"\n/* {CHUNK_MARKER} */\n"
    return text, changes


def patch_server(text: str) -> tuple[str, list[str]]:
    if SSR_MARKER in text or "page-seti-usdt" in text:
        return text, []
    if SSR_OLD not in text:
        return text, []
    text = text.replace(SSR_OLD, SSR_NEW, 1)
    text = text.rstrip() + f"\n/* {SSR_MARKER} */\n"
    return text, ["ssr"]


def main() -> int:
    errors: list[str] = []
    for lang in LANGS:
        path = DIST_ROOT / lang / "chunk-3RSX4ZSH.mjs"
        text = path.read_text(encoding="utf-8")
        if CHUNK_MARKER in text:
            print(f"[INFO] {lang}: already patched")
            continue
        new_text, changes = patch_chunk(text)
        if not changes:
            errors.append(f"{lang}: chunk anchors not matched")
            continue
        path.write_text(new_text, encoding="utf-8")
        print(f"[OK] {lang}: chunk patched ({', '.join(changes)})")

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

    if errors:
        for e in errors:
            print(f"[ERROR] {e}", file=sys.stderr)
        return 1
    print("SUMMARY patch complete")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
