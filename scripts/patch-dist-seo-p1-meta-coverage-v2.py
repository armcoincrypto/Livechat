#!/usr/bin/env python3
"""P1 v2 — Sync exsSetMetaDesc with Angular Meta + SSR meta injector fallback."""
from __future__ import annotations

import sys
from pathlib import Path

DIST_ROOT = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/server")
LANGS = ["ru", "en", "uk", "ka"]
CHUNK_MARKER = "EXS_SEO_P1_META_COVERAGE_V2"
SSR_MARKER = "EXS_SSR_P1_META_COVERAGE_V2"

META_DESC_OLD = (
    "exsSetMetaDesc(D,x){let M=D.querySelector('meta[name=\"description\"]');"
    "M||(M=D.createElement(\"meta\"),M.setAttribute(\"name\",\"description\"),D.head.appendChild(M));"
    "M.setAttribute(\"content\",x);M.setAttribute(\"data-exs-seo-v2\",\"1\");"
    "let O=D.querySelector('meta[property=\"og:description\"]');"
    "O||(O=D.createElement(\"meta\"),O.setAttribute(\"property\",\"og:description\"),D.head.appendChild(O));"
    "O.setAttribute(\"content\",x);O.setAttribute(\"data-exs-seo-v2\",\"1\")}"
)

META_DESC_NEW = (
    "exsSetMetaDesc(D,x){try{this.updateMeta({name:\"description\",content:x});"
    "this.updateMeta({property:\"og:description\",content:x})}catch(e){}"
    "let M=D.querySelector('meta[name=\"description\"]');"
    "M||(M=D.createElement(\"meta\"),M.setAttribute(\"name\",\"description\"),D.head.appendChild(M));"
    "M.setAttribute(\"content\",x);M.setAttribute(\"data-exs-seo-v2\",\"1\");"
    "let O=D.querySelector('meta[property=\"og:description\"]');"
    "O||(O=D.createElement(\"meta\"),O.setAttribute(\"property\",\"og:description\"),D.head.appendChild(O));"
    "O.setAttribute(\"content\",x);O.setAttribute(\"data-exs-seo-v2\",\"1\")}"
)

SSR_OLD = 'catch(ex10){}}if(/rel=\\"canonical\\" href=\\"[^\\"]+\\/(ru|en)\\/contacts\\/?\\"/.test(b)){'

SSR_META_FN = (
    'catch(ex10){}}'
    'try{'
    'let p1m=(u)=>{'
    'let m={'
    '"/ru/contacts":"Контакты Exswaping: поддержка 24/7 в Telegram и email — статус заявок, AML/KYC и помощь при обмене криптовалют.",'
    '"/en/contacts":"Contact Exswaping support 24/7 via Telegram or email for exchange orders, AML/KYC questions, and help with crypto swaps.",'
    '"/ru/faq":"Ответы Exswaping: как обменять USDT и BTC, сроки, комиссии, AML/KYC, безопасность переводов и поддержка клиентов 24/7.",'
    '"/en/faq":"Exswaping FAQ: how to exchange USDT and BTC, fees, processing times, AML/KYC policy, security tips, and 24/7 customer support.",'
    '"/ru/partners":"Партнёрская программа Exswaping: условия сотрудничества, реферальные ссылки, мониторинги и поддержка для обменников и партнёров.",'
    '"/en/partners":"Exswaping partner program: referral terms, monitoring integrations, and collaboration details for exchangers and affiliates.",'
    '"/ru/news":"Новости Exswaping: обновления сервиса, новые направления обмена, курсы, резервы и материалы о безопасном обмене криптовалют.",'
    '"/en/news":"Exswaping news: service updates, new crypto exchange pairs, rates, reserves, and guides for safe USDT and BTC swaps.",'
    '"/uk/news":"Новини Exswaping: оновлення сервісу, нові напрямки обміну, курси, резерви та матеріали про безпечний обмін криптовалют.",'
    '"/ka/news":"Exswaping news and updates: exchange directions, rates, reserves, and safe crypto swap guides for clients.",'
    '"/ru/guides":"Руководства Exswaping: обмен USDT и BTC, безопасность, сети TRC20/ERC20, вывод на карту и популярные направления обмена.",'
    '"/en/guides":"Exswaping guides: USDT and crypto exchange tips, security, TRC20/ERC20 networks, bank card payouts, and popular swap routes.",'
    '"/uk/guides":"Exswaping guides: USDT and crypto exchange tips, security, TRC20/ERC20 networks, bank card payouts, and popular swap routes.",'
    '"/ka/guides":"Exswaping guides: USDT and crypto exchange tips, security, TRC20/ERC20 networks, bank card payouts, and popular swap routes.",'
    '"/ru/pages/about":"О сервисе Exswaping: онлайн обменник криптовалют с 500+ направлениями, резервами, AML/KYC и круглосуточной поддержкой клиентов.",'
    '"/ru/pages/AMLKYC":"Политика AML и KYC Exswaping: правила проверки переводов, требования к клиентам и меры безопасности при обмене криптовалют.",'
    '"/ru/pages/service":"Пользовательское соглашение Exswaping: правила обмена криптовалют, обязанности сторон, лимиты, сроки и порядок обработки заявок.",'
    '"/ru/pages/vozvrata":"Политика возврата Exswaping: условия возврата средств при ошибках в реквизитах, отмене заявки и спорных переводах криптовалют.",'
    '"/ru/pages/rekvizitov":"Политика изменения реквизитов Exswaping: как обновляются платёжные данные, защита от подмены и правила безопасного обмена.",'
    '"/ru/pages/instructions":"Инструкция Exswaping: пошаговый обмен криптовалют — выбор пары, проверка курса, оплата, подтверждение и получение средств."'
    '};'
    'return m[u]||null};'
    'let p1c=b.match(/rel=\\"canonical\\" href=\\"https:\\/\\/exswaping\\.com([^\\"?#]+)/i);'
    'let p1p=p1c&&p1c[1].replace(/\\/$/,"")||"";'
    'let p1d=p1m(p1p);'
    'if(p1d){'
    'let p1esc=(s)=>String(s).replace(/&/g,"&amp;").replace(/\"/g,"&quot;");'
    'let p1x=p1esc(p1d);'
    'let p1bo=/криптообменник с поддержкой 500\\+|crypto exchange with 500\\+/i;'
    'if(!/<meta[^>]+name=[\\"\\\']description[\\"\\\']/i.test(b)||p1bo.test(b)){'
    'b=b.replace(/<meta[^>]+name=[\\"\\\']description[\\"\\\'][^>]*>/gi,"");'
    'b=b.replace(/<meta[^>]+property=[\\"\\\']og:description[\\"\\\'][^>]*>/gi,"");'
    'b=b.replace(/<\\/title>/i,"</title><meta name=\\"description\\" content=\\""+p1x+"\\" data-exs-seo-v2=\\"1\\"><meta property=\\"og:description\\" content=\\""+p1x+"\\" data-exs-seo-v2=\\"1\\">");'
    '}}'
    '}catch(exP1Meta){}'
    'try{'
    'let p1b=(u)=>{'
    'let s=u.match(/\\/(uk|ka)\\/blog\\/([^\\"?#]+)/);'
    'if(!s)return null;'
    'let loc=s[1],slug=s[2],en={'
    '"kak-vygodno-i-bezopasno-obmeniat-usdt-na-rubli-v-2025-godu-11":"USDT to rubles on Exswaping: risks, method comparison, and a link to the official USDT→RUB exchange guide.",'
    '"exswaping-polnaia-instrukciia-10":"Step-by-step Exswaping guide: create an exchange order, pay, confirm transfer, and receive funds safely.",'
    '"rukovodstvo-po-obmenu-kriptovaliuty-na-rublevye-karty-s-exswaping-2":"How to exchange crypto to ruble bank cards via Exswaping: direction selection, security checks, and official site rules.",'
    '"rukovodstvo-po-obmenu-kriptovaliuty-na-ukrainskie-karty-s-exswaping-3":"How to exchange crypto to Ukrainian bank cards via Exswaping: pair selection, rate checks, and safe payout rules.",'
    '"rukovodstvo-po-obmenu-kriptovaliuty-na-kriptovaliutu-s-exswaping-4":"How to swap one cryptocurrency for another on Exswaping: choose a pair, verify the rate, reserve, and order steps.",'
    '"exswaping-prinimaet-samye-izvestnye-kriptovaliuty-dlia-obmena-5":"Popular cryptocurrencies on Exswaping: how to check live directions, rates, reserves, and order rules on the official site.",'
    '"kriptovaliuta-dlia-novickov-vvedenie-v-osnovy-kriptovaliut-blokcein-nft-i-defi-6":"Crypto basics for beginners and how to start a safe first exchange on Exswaping with verified site links.",'
    '"rukovodstvo-po-bezopasnosti-kak-izbezat-mosennicestva-i-zashhitit-svoi-sredstva-7":"Practical safe crypto exchange tips on Exswaping: avoid phishing, verify the domain, and protect your funds.",'
    '"amlkyc-i-vasa-bezopasnost-obieiasnenie-politiki-po-borbe-s-otmyvaniem-deneg-i-kyc-8":"Why Exswaping uses AML/KYC checks, how they protect users, and where to read the full compliance policy.",'
    '"zakony-i-regulirovanie-obnovleniia-zakonodatelstva-o-kriptovaliute-v-raznyx-stranax-9":"Crypto regulation overview for Exswaping users: what may affect exchanges and what to verify before ordering.",'
    '"exswaping-teper-na-cryptoru-12":"Exswaping on Crypto.ru: how users find the service and what to verify on exswaping.com before exchanging.",'
    '"usdt-to-amd-13":"Exchange USDT to AMD on Exswaping: check the live rate, reserve, direction rules, and create an order on the official site.",'
    '"usdt-to-kzt-14":"Exchange USDT to KZT on Exswaping: verify rate and reserve, review payout rules, and submit an order securely.",'
    '"exswaping-teper-na-exchangesumo-15":"Exswaping on Exchangesumo: monitoring profile reminder to check rate, reserve, and rules on the official site.",'
    '"top-10-obmennikov-2025-goda-lucsie-servisy-dlia-bystrogo-i-vygodnogo-obmena-kriptovaliuty-16":"How to compare crypto exchangers in 2025 and verify Exswaping conditions before placing an order.",'
    '"exswaping-teper-na-exnode-odin-sag-blize-k-lideram-rynka-obmena-kriptovaliut-17":"Exswaping on Exnode: why the listing matters and what to verify on exswaping.com before creating an order.",'
    '"p2p-obmeny-v-rossii-pocemu-rastut-zaderzki-i-kak-exswaping-zashhishhaet-klientov-18":"Why P2P delays grow in Russia and how Exswaping reduces risks with order rules, support, and AML checks.",'
    '"exswaping-obmen-kriptovaliuty-i-valiut-v-los-andzelese-crypto-to-cash-la-19":"Crypto exchange in Los Angeles via Exswaping and Crypto to Cash LA: verify the official site before ordering.",'
    '"exswaping-oficialno-dobavlen-v-monitoring-bestchange-20":"Exswaping on BestChange: what the listing means, how to compare rates, and checks before you exchange."'
    '};'
    'let d=en[slug];'
    'if(!d)return null;'
    'if(loc==="ka")return d.replace(/Exswaping/g,"Exswaping (GE)");'
    'return d};'
    'let p1bc=b.match(/rel=\\"canonical\\" href=\\"https:\\/\\/exswaping\\.com([^\\"?#]+)/i);'
    'let p1bp=p1bc&&p1bc[1]||"";'
    'let p1bd=p1b(p1bp);'
    'if(p1bd&&/crypto exchange with 500\\+/i.test(b)){'
    'let p1bx=p1bd.replace(/&/g,"&amp;").replace(/\"/g,"&quot;");'
    'b=b.replace(/<meta[^>]+name=[\\"\\\']description[\\"\\\'][^>]*>/gi,"");'
    'b=b.replace(/<meta[^>]+property=[\\"\\\']og:description[\\"\\\'][^>]*>/gi,"");'
    'b=b.replace(/<\\/title>/i,"</title><meta name=\\"description\\" content=\\""+p1bx+"\\" data-exs-seo-v2=\\"1\\"><meta property=\\"og:description\\" content=\\""+p1bx+"\\" data-exs-seo-v2=\\"1\\">");'
    '}'
    '}catch(exP1Blog){}'
    'if(/rel=\\"canonical\\" href=\\"[^\\"]+\\/(ru|en)\\/contacts\\/?\\"/.test(b)){'
)


def patch_chunk(text: str) -> tuple[str, list[str]]:
    if CHUNK_MARKER in text:
        return text, []
    if META_DESC_OLD not in text:
        return text, []
    text = text.replace(META_DESC_OLD, META_DESC_NEW, 1)
    text = text.rstrip() + f"\n/* {CHUNK_MARKER} */\n"
    return text, ["exsSetMetaDesc_updateMeta"]


def patch_server(text: str) -> tuple[str, list[str]]:
    if SSR_MARKER in text:
        return text, []
    if SSR_OLD not in text:
        return text, []
    text = text.replace(SSR_OLD, SSR_META_FN, 1)
    text = text.rstrip() + f"\n/* {SSR_MARKER} */\n"
    return text, ["ssr_meta_injector", "ssr_uk_ka_blog_meta"]


def main() -> int:
    errors: list[str] = []
    for lang in LANGS:
        path = DIST_ROOT / lang / "chunk-3RSX4ZSH.mjs"
        if not path.exists():
            errors.append(f"{lang}: missing chunk")
            continue
        text = path.read_text(encoding="utf-8")
        if CHUNK_MARKER in text:
            print(f"[INFO] {lang}: chunk already v2")
            continue
        new_text, changes = patch_chunk(text)
        if not changes:
            errors.append(f"{lang}: exsSetMetaDesc anchor not matched")
            continue
        path.write_text(new_text, encoding="utf-8")
        print(f"[OK] {lang}: {', '.join(changes)}")

    server = DIST_ROOT / "server.mjs"
    if server.exists():
        text = server.read_text(encoding="utf-8")
        if SSR_MARKER in text:
            print("[INFO] server.mjs already v2")
        else:
            new_text, changes = patch_server(text)
            if not changes:
                errors.append("server.mjs: SSR anchor not matched")
            else:
                server.write_text(new_text, encoding="utf-8")
                print(f"[OK] server.mjs: {', '.join(changes)}")

    if errors:
        for e in errors:
            print(f"[ERROR] {e}", file=sys.stderr)
        return 1
    print("P1 meta coverage v2 patch complete")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
