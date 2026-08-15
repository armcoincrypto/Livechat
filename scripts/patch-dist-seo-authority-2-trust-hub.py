#!/usr/bin/env python3
"""SEO-AUTHORITY-2 — Dist patches for /ru/guides/bezopasnyj-kriptoobmen trust hub."""
from __future__ import annotations

import sys
from pathlib import Path

DIST_ROOT = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/server")
LANGS = ["ru", "en", "uk", "ka"]
CHUNK_MARKER = "EXS_GUIDE_SEO_AUTHORITY2_V1"
SSR_MARKER = "EXS_GUIDE_SSR_CONTENT_AUTHORITY2_V1"
BASE_CHUNK_MARKER = "EXS_GUIDE_SEO_RU10A_V1"

META_OLD = (
    'else if(/\\/guides\\/usdt-trc20-i-erc20\\/?$/.test(this.exsStripLocalePrefixes(reqPath))&&loc==="ru"){'
    'this.exsSetTitle(D,"USDT TRC20 и ERC20 — В чем разница и что выбрать | Exswaping");'
    'this.exsSetMetaDesc(D,"Сравнение USDT TRC20 и ERC20. Узнайте различия в комиссиях, скорости подтверждений, совместимости кошельков и сценариях использования.");'
    'try{let B=D.body,inj=B&&B.querySelector("h1[data-exs-seo-v2]"),all=B&&B.querySelectorAll("h1");'
    'inj&&all&&all.length>1&&inj.remove();all&&all.length===1&&all[0].setAttribute("data-exs-seo-v2","1")}catch{}}'
)

META_NEW = META_OLD + (
    'else if(/\\/guides\\/bezopasnyj-kriptoobmen\\/?$/.test(this.exsStripLocalePrefixes(reqPath))&&loc==="ru"){'
    'this.exsSetTitle(D,"Безопасный обмен криптовалют — как выбрать сервис | Exswaping");'
    'this.exsSetMetaDesc(D,"Как безопасно обменять криптовалюту: выбор сервиса, AML/KYC, мониторинги, проверка репутации, чеклист перед переводом и типичные ошибки.");'
    'try{let B=D.body,inj=B&&B.querySelector("h1[data-exs-seo-v2]"),all=B&&B.querySelectorAll("h1");'
    'inj&&all&&all.length>1&&inj.remove();all&&all.length===1&&all[0].setAttribute("data-exs-seo-v2","1")}catch{}}'
)

PAGES_OLD = (
    'if(/\\/pages\\/usdt-trc20-i-erc20\\/?$/.test(this.exsStripLocalePrefixes(reqPath))){'
    'let R10=D.querySelector(\'meta[name="robots"]\');'
    'R10||(R10=D.createElement("meta"),R10.setAttribute("name","robots"),D.head.appendChild(R10));'
    'R10.setAttribute("content","noindex,follow");R10.setAttribute("data-exs-seo-v2","1");'
    'let C10=D.querySelector(\'link[rel="canonical"]\');'
    'C10||(C10=D.createElement("link"),C10.setAttribute("rel","canonical"),D.head.appendChild(C10));'
    'C10.setAttribute("href",t+"/ru/guides/usdt-trc20-i-erc20");C10.setAttribute("data-exs-seo-v2","1");return}'
)

PAGES_NEW = PAGES_OLD + (
    'if(/\\/pages\\/bezopasnyj-kriptoobmen\\/?$/.test(this.exsStripLocalePrefixes(reqPath))){'
    'let R11=D.querySelector(\'meta[name="robots"]\');'
    'R11||(R11=D.createElement("meta"),R11.setAttribute("name","robots"),D.head.appendChild(R11));'
    'R11.setAttribute("content","noindex,follow");R11.setAttribute("data-exs-seo-v2","1");'
    'let C11=D.querySelector(\'link[rel="canonical"]\');'
    'C11||(C11=D.createElement("link"),C11.setAttribute("rel","canonical"),D.head.appendChild(C11));'
    'C11.setAttribute("href",t+"/ru/guides/bezopasnyj-kriptoobmen");C11.setAttribute("data-exs-seo-v2","1");return}'
)

SCHEMA_OLD = (
    '"mainEntity":mainEntityTE}]});return}'
    'let guideUsdtEn=/\\/guides\\/usdt-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath));'
)

SCHEMA_NEW = (
    '"mainEntity":mainEntityTE}]});return}'
    'let guideSafeTrust=/\\/guides\\/bezopasnyj-kriptoobmen\\/?$/.test(this.exsStripLocalePrefixes(reqPath));'
    'if(guideSafeTrust&&loc==="ru"){let pageUrl=t+"/ru/guides/bezopasnyj-kriptoobmen";'
    'let faqItems=[{q:"Как проверить официальный сайт Exswaping?",a:"Вводите exswaping.com вручную и создавайте заявку только на сайте."},'
    '{q:"Зачем нужны AML/KYC?",a:"Процедуры снижают риск мошенничества. Полный текст на странице AML/KYC."},'
    '{q:"Можно ли доверять только BestChange?",a:"Listing не заменяет проверку условий на exswaping.com."},'
    '{q:"Exswaping запрашивает seed-фразу?",a:"Нет. Никогда не сообщайте seed-фразу."},'
    '{q:"Как выбрать сеть USDT?",a:"См. гид TRC20 vs ERC20 и заявку в калькуляторе."},'
    '{q:"Куда писать при ошибке?",a:"Официальные контакты с номером заявки и хешем транзакции."},'
    '{q:"Гарантирует ли Exswaping курс?",a:"Нет. Итог фиксируется в заявке на момент расчёта."},'
    '{q:"Где инструкция обмена?",a:"Страница instructions и гид USDT → RUB на сайте."}];'
    'let mainEntityST=faqItems.map(it=>({"@type":"Question","name":it.q,"acceptedAnswer":{"@type":"Answer","text":it.a}}));'
    'this.exsInjectJsonLd(D,{"@context":"https://schema.org","@graph":['
    '{"@type":"BreadcrumbList","itemListElement":['
    '{"@type":"ListItem","position":1,"name":"Exswaping","item":t+"/ru/"},'
    '{"@type":"ListItem","position":2,"name":"Guides","item":t+"/ru/guides/obmen-usdt-na-rubli"},'
    '{"@type":"ListItem","position":3,"name":"Безопасный обмен криптовалют","item":pageUrl}'
    "]},"
    '{"@type":"Article","headline":"Безопасный обмен криптовалют","description":"Как безопасно обменять криптовалюту: выбор сервиса, AML/KYC, мониторинги и чеклист перед переводом.",'
    '"author":{"@type":"Organization","name":"Exswaping","url":t+"/"},"publisher":{"@type":"Organization","name":"Exswaping","url":t+"/","logo":{"@type":"ImageObject","url":t+"/images/favicons/favicon-96x96.png"}},'
    '"mainEntityOfPage":{"@type":"WebPage","@id":pageUrl},"inLanguage":"ru-RU"},'
    '{"@type":"FAQPage","mainEntity":mainEntityST}'
    "]});return}"
    'let guideUsdtEn=/\\/guides\\/usdt-exchange\\/?$/.test(this.exsStripLocalePrefixes(reqPath));'
)

SSR_OLD = (
    'if(b.includes("/guides/usdt-trc20-i-erc20")&&b.includes("page-usdt-trc20-i-erc20")&&!/<h2/i.test(b)){'
    'try{let js7=b.match(/<script id=\\"iexexchanger-state\\" type=\\"application\\/json\\">([\\s\\S]*?)<\\/script>/);'
    'if(js7){let st7=JSON.parse(js7[1]),pg7=st7["page-usdt-trc20-i-erc20"];'
    'if(pg7&&pg7.content){b=b.replace(/(<div class=\\"prose my-6 formatting__text[^\\"]*\\"[^>]*>)\\s*<\\/div>/,'
    '"$1"+pg7.content+"</div>")}}}catch(ex7){}}'
)

SSR_NEW = SSR_OLD + (
    'if(b.includes("/guides/bezopasnyj-kriptoobmen")&&b.includes("page-bezopasnyj-kriptoobmen")&&!/<h2/i.test(b)){'
    'try{let js8=b.match(/<script id=\\"iexexchanger-state\\" type=\\"application\\/json\\">([\\s\\S]*?)<\\/script>/);'
    'if(js8){let st8=JSON.parse(js8[1]),pg8=st8["page-bezopasnyj-kriptoobmen"];'
    'if(pg8&&pg8.content){b=b.replace(/(<div class=\\"prose my-6 formatting__text[^\\"]*\\"[^>]*>)\\s*<\\/div>/,'
    '"$1"+pg8.content+"</div>")}}}catch(ex8){}}'
)


def patch_chunk(text: str) -> tuple[str, list[str]]:
    if BASE_CHUNK_MARKER not in text:
        return text, []
    if CHUNK_MARKER in text:
        return text, []

    changes: list[str] = []
    if META_OLD in text and "guides\\/bezopasnyj-kriptoobmen" not in text.split("else if(/\\/guides\\/usdt-exchange")[0]:
        text = text.replace(META_OLD, META_NEW, 1)
        changes.append("metadata")
    if PAGES_OLD in text and "pages\\/bezopasnyj-kriptoobmen" not in text:
        text = text.replace(PAGES_OLD, PAGES_NEW, 1)
        changes.append("pages_alias")
    if SCHEMA_OLD in text and "let guideSafeTrust=" not in text:
        text = text.replace(SCHEMA_OLD, SCHEMA_NEW, 1)
        changes.append("schema")
    if changes:
        text = text.rstrip() + f"\n/* {CHUNK_MARKER} */\n"
    return text, changes


def patch_server(text: str) -> tuple[str, list[str]]:
    if SSR_MARKER in text or "page-bezopasnyj-kriptoobmen" in text:
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
        if not path.exists():
            errors.append(f"{lang}: missing chunk")
            continue
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
