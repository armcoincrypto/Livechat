#!/usr/bin/env python3
"""SEO-AUTHORITY-3 — Dist patches for /ru/guides/monitoring-kriptovalyutnyh-obmennikov."""
from __future__ import annotations

import sys
from pathlib import Path

DIST_ROOT = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/server")
LANGS = ["ru", "en", "uk", "ka"]
CHUNK_MARKER = "EXS_GUIDE_SEO_AUTHORITY3_V1"
SSR_MARKER = "EXS_GUIDE_SSR_CONTENT_AUTHORITY3_V1"
BASE_CHUNK_MARKER = "EXS_GUIDE_SEO_AUTHORITY2_V1"

META_OLD = (
    'else if(/\\/guides\\/bezopasnyj-kriptoobmen\\/?$/.test(this.exsStripLocalePrefixes(reqPath))&&loc==="ru"){'
    'this.exsSetTitle(D,"Безопасный обмен криптовалют — как выбрать сервис | Exswaping");'
    'this.exsSetMetaDesc(D,"Как безопасно обменять криптовалюту: выбор сервиса, AML/KYC, мониторинги, проверка репутации, чеклист перед переводом и типичные ошибки.");'
    'try{let B=D.body,inj=B&&B.querySelector("h1[data-exs-seo-v2]"),all=B&&B.querySelectorAll("h1");'
    'inj&&all&&all.length>1&&inj.remove();all&&all.length===1&&all[0].setAttribute("data-exs-seo-v2","1")}catch{}}'
)

META_NEW = META_OLD + (
    'else if(/\\/guides\\/monitoring-kriptovalyutnyh-obmennikov\\/?$/.test(this.exsStripLocalePrefixes(reqPath))&&loc==="ru"){'
    'this.exsSetTitle(D,"Мониторинги обменников — как оценить сервис | Exswaping");'
    'this.exsSetMetaDesc(D,"Как работают мониторинги обменников: BestChange, Crypto.ru, Exnode, ExchangeSumo. Как оценить отзывы и безопасно перейти к обмену на exswaping.com.");'
    'try{let B=D.body,inj=B&&B.querySelector("h1[data-exs-seo-v2]"),all=B&&B.querySelectorAll("h1");'
    'inj&&all&&all.length>1&&inj.remove();all&&all.length===1&&all[0].setAttribute("data-exs-seo-v2","1")}catch{}}'
)

PAGES_OLD = (
    'if(/\\/pages\\/bezopasnyj-kriptoobmen\\/?$/.test(this.exsStripLocalePrefixes(reqPath))){'
    'let R11=D.querySelector(\'meta[name="robots"]\');'
    'R11||(R11=D.createElement("meta"),R11.setAttribute("name","robots"),D.head.appendChild(R11));'
    'R11.setAttribute("content","noindex,follow");R11.setAttribute("data-exs-seo-v2","1");'
    'let C11=D.querySelector(\'link[rel="canonical"]\');'
    'C11||(C11=D.createElement("link"),C11.setAttribute("rel","canonical"),D.head.appendChild(C11));'
    'C11.setAttribute("href",t+"/ru/guides/bezopasnyj-kriptoobmen");C11.setAttribute("data-exs-seo-v2","1");return}'
)

PAGES_NEW = PAGES_OLD + (
    'if(/\\/pages\\/monitoring-kriptovalyutnyh-obmennikov\\/?$/.test(this.exsStripLocalePrefixes(reqPath))){'
    'let R12=D.querySelector(\'meta[name="robots"]\');'
    'R12||(R12=D.createElement("meta"),R12.setAttribute("name","robots"),D.head.appendChild(R12));'
    'R12.setAttribute("content","noindex,follow");R12.setAttribute("data-exs-seo-v2","1");'
    'let C12=D.querySelector(\'link[rel="canonical"]\');'
    'C12||(C12=D.createElement("link"),C12.setAttribute("rel","canonical"),D.head.appendChild(C12));'
    'C12.setAttribute("href",t+"/ru/guides/monitoring-kriptovalyutnyh-obmennikov");C12.setAttribute("data-exs-seo-v2","1");return}'
)

SCHEMA_OLD = (
    '"mainEntity":mainEntityST}]});return}'
    'let guideUsdtEn=/\\/guides\\/usdt-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath));'
)

SCHEMA_NEW = SCHEMA_OLD + (
    'let guideMonitoring=/\\/guides\\/monitoring-kriptovalyutnyh-obmennikov\\/?$/.test(this.exsStripLocalePrefixes(reqPath));'
    'if(guideMonitoring&&loc==="ru"){let pageUrl=t+"/ru/guides/monitoring-kriptovalyutnyh-obmennikov";'
    'let faqItems=[{q:"Что такое мониторинг обменников?",a:"Каталог профилей и курсов. Listing не заменяет официальный сайт."},'
    '{q:"Заменяет ли BestChange exswaping.com?",a:"Нет. Заявка только на exswaping.com."},'
    '{q:"Можно ли доверять только рейтингу?",a:"Нет. Сверяйте с условиями на официальном сайте."},'
    '{q:"Exswaping публикует рейтинг мониторингов?",a:"Нет. Exswaping не ранжирует платформы."},'
    '{q:"Где гиды USDT?",a:"На exswaping.com в разделе guides."},'
    '{q:"Куда писать при ошибке?",a:"Официальные контакты с номером заявки."},'
    '{q:"Нужен ли AML/KYC?",a:"По политике сервиса в отдельных случаях."},'
    '{q:"Где trust-гид?",a:"Страница bezopasnyj-kriptoobmen на exswaping.com."}];'
    'let mainEntityMO=faqItems.map(it=>({"@type":"Question","name":it.q,"acceptedAnswer":{"@type":"Answer","text":it.a}}));'
    'this.exsInjectJsonLd(D,{"@context":"https://schema.org","@graph":['
    '{"@type":"BreadcrumbList","itemListElement":['
    '{"@type":"ListItem","position":1,"name":"Exswaping","item":t+"/ru/"},'
    '{"@type":"ListItem","position":2,"name":"Guides","item":t+"/ru/guides/bezopasnyj-kriptoobmen"},'
    '{"@type":"ListItem","position":3,"name":"Мониторинги обменников","item":pageUrl}'
    "]},"
    '{"@type":"Article","headline":"Мониторинги криптовалютных обменников","description":"Как работают мониторинги обменников и как безопасно оценить сервис перед обменом.",'
    '"author":{"@type":"Organization","name":"Exswaping","url":t+"/"},"publisher":{"@type":"Organization","name":"Exswaping","url":t+"/","logo":{"@type":"ImageObject","url":t+"/images/favicons/favicon-96x96.png"}},'
    '"mainEntityOfPage":{"@type":"WebPage","@id":pageUrl},"inLanguage":"ru-RU"},'
    '{"@type":"FAQPage","mainEntity":mainEntityMO}'
    "]});return}"
)

SSR_OLD = (
    'if(b.includes("/guides/bezopasnyj-kriptoobmen")&&b.includes("page-bezopasnyj-kriptoobmen")&&!/<h2/i.test(b)){'
    'try{let js8=b.match(/<script id=\\"iexexchanger-state\\" type=\\"application\\/json\\">([\\s\\S]*?)<\\/script>/);'
    'if(js8){let st8=JSON.parse(js8[1]),pg8=st8["page-bezopasnyj-kriptoobmen"];'
    'if(pg8&&pg8.content){b=b.replace(/(<div class=\\"prose my-6 formatting__text[^\\"]*\\"[^>]*>)\\s*<\\/div>/,'
    '"$1"+pg8.content+"</div>")}}}catch(ex8){}}'
)

SSR_NEW = SSR_OLD + (
    'if(b.includes("/guides/monitoring-kriptovalyutnyh-obmennikov")&&b.includes("page-monitoring-kriptovalyutnyh-obmennikov")&&!/<h2/i.test(b)){'
    'try{let js9=b.match(/<script id=\\"iexexchanger-state\\" type=\\"application\\/json\\">([\\s\\S]*?)<\\/script>/);'
    'if(js9){let st9=JSON.parse(js9[1]),pg9=st9["page-monitoring-kriptovalyutnyh-obmennikov"];'
    'if(pg9&&pg9.content){b=b.replace(/(<div class=\\"prose my-6 formatting__text[^\\"]*\\"[^>]*>)\\s*<\\/div>/,'
    '"$1"+pg9.content+"</div>")}}}catch(ex9){}}'
)


def patch_chunk(text: str) -> tuple[str, list[str]]:
    if BASE_CHUNK_MARKER not in text:
        return text, []
    if CHUNK_MARKER in text:
        return text, []

    changes: list[str] = []
    if META_OLD in text and "monitoring-kriptovalyutnyh-obmennikov" not in text.split("else if(/\\/guides\\/usdt-exchange")[0]:
        text = text.replace(META_OLD, META_NEW, 1)
        changes.append("metadata")
    if PAGES_OLD in text and "pages\\/monitoring-kriptovalyutnyh-obmennikov" not in text:
        text = text.replace(PAGES_OLD, PAGES_NEW, 1)
        changes.append("pages_alias")
    if SCHEMA_OLD in text and "let guideMonitoring=" not in text:
        text = text.replace(SCHEMA_OLD, SCHEMA_NEW, 1)
        changes.append("schema")
    if changes:
        text = text.rstrip() + f"\n/* {CHUNK_MARKER} */\n"
    return text, changes


def patch_server(text: str) -> tuple[str, list[str]]:
    if SSR_MARKER in text or "page-monitoring-kriptovalyutnyh-obmennikov" in text:
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
