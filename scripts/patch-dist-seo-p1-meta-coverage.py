#!/usr/bin/env python3
"""P1 — Meta description + OG coverage for trust, CMS, guides, FAQ, news, UK/KA blog."""
from __future__ import annotations

import sys
from pathlib import Path

DIST_ROOT = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/server")
LANGS = ["ru", "en", "uk", "ka"]
MARKER = "EXS_SEO_P1_META_COVERAGE_V1"

FAQ_OLD = (
    'if(/\\/faq\\/?$/.test(this.exsStripLocalePrefixes(reqPath))){if(loc==="ru")this.exsSetTitle(D,"Вопросы и ответы — Exswaping");'
    'else if(loc==="en")this.exsSetTitle(D,"Frequently Asked Questions — Exswaping");'
)

FAQ_NEW = (
    'if(/\\/faq\\/?$/.test(this.exsStripLocalePrefixes(reqPath))){if(loc==="ru"){this.exsSetTitle(D,"Вопросы и ответы — Exswaping");'
    'this.exsSetMetaDesc(D,"Ответы Exswaping: как обменять USDT и BTC, сроки, комиссии, AML/KYC, безопасность переводов и поддержка клиентов 24/7.");}'
    'else if(loc==="en"){this.exsSetTitle(D,"Frequently Asked Questions — Exswaping");'
    'this.exsSetMetaDesc(D,"Exswaping FAQ: how to exchange USDT and BTC, fees, processing times, AML/KYC policy, security tips, and 24/7 customer support.");}'
)

P1_BLOCK = (
    '{let _p1=this.exsStripLocalePrefixes(reqPath);'
    'if(/\\/contacts\\/?$/.test(_p1)){if(loc==="ru")this.exsSetMetaDesc(D,"Контакты Exswaping: поддержка 24/7 в Telegram и email — статус заявок, AML/KYC и помощь при обмене криптовалют.");'
    'else this.exsSetMetaDesc(D,"Contact Exswaping support 24/7 via Telegram or email for exchange orders, AML/KYC questions, and help with crypto swaps.");}'
    'else if(/\\/partners\\/?$/.test(_p1)){if(loc==="ru")this.exsSetMetaDesc(D,"Партнёрская программа Exswaping: условия сотрудничества, реферальные ссылки, мониторинги и поддержка для обменников и партнёров.");'
    'else this.exsSetMetaDesc(D,"Exswaping partner program: referral terms, monitoring integrations, and collaboration details for exchangers and affiliates.");}'
    'else if(/\\/news\\/?$/.test(_p1)){if(loc==="ru")this.exsSetMetaDesc(D,"Новости Exswaping: обновления сервиса, новые направления обмена, курсы, резервы и материалы о безопасном обмене криптовалют.");'
    'else if(loc==="en")this.exsSetMetaDesc(D,"Exswaping news: service updates, new crypto exchange pairs, rates, reserves, and guides for safe USDT and BTC swaps.");'
    'else if(loc==="uk")this.exsSetMetaDesc(D,"Новини Exswaping: оновлення сервісу, нові напрямки обміну, курси, резерви та матеріали про безпечний обмін криптовалют.");'
    'else this.exsSetMetaDesc(D,"Exswaping news and updates: exchange directions, rates, reserves, and safe crypto swap guides for clients.");}'
    'else if(/\\/guides\\/?$/.test(_p1)){if(loc==="ru")this.exsSetMetaDesc(D,"Руководства Exswaping: обмен USDT и BTC, безопасность, сети TRC20/ERC20, вывод на карту и популярные направления обмена.");'
    'else this.exsSetMetaDesc(D,"Exswaping guides: USDT and crypto exchange tips, security, TRC20/ERC20 networks, bank card payouts, and popular swap routes.");}'
    'else if(/\\/pages\\/about\\/?$/.test(_p1)){this.exsSetMetaDesc(D,"О сервисе Exswaping: онлайн обменник криптовалют с 500+ направлениями, резервами, AML/KYC и круглосуточной поддержкой клиентов.");}'
    'else if(/\\/pages\\/AMLKYC\\/?$/.test(_p1)){this.exsSetMetaDesc(D,"Политика AML и KYC Exswaping: правила проверки переводов, требования к клиентам и меры безопасности при обмене криптовалют.");}'
    'else if(/\\/pages\\/service\\/?$/.test(_p1)){this.exsSetMetaDesc(D,"Пользовательское соглашение Exswaping: правила обмена криптовалют, обязанности сторон, лимиты, сроки и порядок обработки заявок.");}'
    'else if(/\\/pages\\/vozvrata\\/?$/.test(_p1)){this.exsSetMetaDesc(D,"Политика возврата Exswaping: условия возврата средств при ошибках в реквизитах, отмене заявки и спорных переводах криптовалют.");}'
    'else if(/\\/pages\\/rekvizitov\\/?$/.test(_p1)){this.exsSetMetaDesc(D,"Политика изменения реквизитов Exswaping: как обновляются платёжные данные, защита от подмены и правила безопасного обмена.");}'
    'else if(/\\/pages\\/instructions\\/?$/.test(_p1)){this.exsSetMetaDesc(D,"Инструкция Exswaping: пошаговый обмен криптовалют — выбор пары, проверка курса, оплата, подтверждение и получение средств.");}'
    '}'
)

BLOG_OLD = "bl=(bp[loc]||bp.en)[slug];bl&&this.exsSetMetaDesc(D,bl)"

BLOG_NEW = (
    "bl=(bp[loc]||bp.en)[slug];"
    "if(!bl&&(loc===\"uk\"||loc===\"ka\"||loc===\"zh\")){"
    "let p1u={"
    '"rukovodstvo-po-obmenu-kriptovaliuty-na-rublevye-karty-s-exswaping-2":"How to exchange crypto to ruble bank cards via Exswaping: direction selection, security checks, and official site rules.",'
    '"rukovodstvo-po-obmenu-kriptovaliuty-na-ukrainskie-karty-s-exswaping-3":"How to exchange crypto to Ukrainian bank cards via Exswaping: pair selection, rate checks, and safe payout rules.",'
    '"rukovodstvo-po-obmenu-kriptovaliuty-na-kriptovaliutu-s-exswaping-4":"How to swap one cryptocurrency for another on Exswaping: choose a pair, verify the rate, reserve, and order steps.",'
    '"exswaping-prinimaet-samye-izvestnye-kriptovaliuty-dlia-obmena-5":"Popular cryptocurrencies on Exswaping: how to check live directions, rates, reserves, and order rules on the official site.",'
    '"kriptovaliuta-dlia-novickov-vvedenie-v-osnovy-kriptovaliut-blokcein-nft-i-defi-6":"Crypto basics for beginners and how to start a safe first exchange on Exswaping with verified site links.",'
    '"rukovodstvo-po-bezopasnosti-kak-izbezat-mosennicestva-i-zashhitit-svoi-sredstva-7":"Practical safe crypto exchange tips on Exswaping: avoid phishing, verify the domain, and protect your funds.",'
    '"amlkyc-i-vasa-bezopasnost-obieiasnenie-politiki-po-borbe-s-otmyvaniem-deneg-i-kyc-8":"Why Exswaping uses AML/KYC checks, how they protect users, and where to read the full compliance policy.",'
    '"zakony-i-regulirovanie-obnovleniia-zakonodatelstva-o-kriptovaliute-v-raznyx-stranax-9":"Crypto regulation overview for Exswaping users: what may affect exchanges and what to verify before ordering.",'
    '"exswaping-polnaia-instrukciia-10":"Step-by-step Exswaping guide: create an exchange order, pay, confirm transfer, and receive funds safely.",'
    '"kak-vygodno-i-bezopasno-obmeniat-usdt-na-rubli-v-2025-godu-11":"USDT to rubles on Exswaping: risks, method comparison, and a link to the official USDT→RUB exchange guide.",'
    '"exswaping-teper-na-cryptoru-12":"Exswaping on Crypto.ru: how users find the service and what to verify on exswaping.com before exchanging.",'
    '"usdt-to-amd-13":"Exchange USDT to AMD on Exswaping: check the live rate, reserve, direction rules, and create an order on the official site.",'
    '"usdt-to-kzt-14":"Exchange USDT to KZT on Exswaping: verify rate and reserve, review payout rules, and submit an order securely.",'
    '"exswaping-teper-na-exchangesumo-15":"Exswaping on Exchangesumo: monitoring profile reminder to check rate, reserve, and rules on the official site.",'
    '"top-10-obmennikov-2025-goda-lucsie-servisy-dlia-bystrogo-i-vygodnogo-obmena-kriptovaliuty-16":"How to compare crypto exchangers in 2025 and verify Exswaping conditions before placing an order.",'
    '"exswaping-teper-na-exnode-odin-sag-blize-k-lideram-rynka-obmena-kriptovaliut-17":"Exswaping on Exnode: why the listing matters and what to verify on exswaping.com before creating an order.",'
    '"p2p-obmeny-v-rossii-pocemu-rastut-zaderzki-i-kak-exswaping-zashhishhaet-klientov-18":"Why P2P delays grow in Russia and how Exswaping reduces risks with order rules, support, and AML checks.",'
    '"exswaping-obmen-kriptovaliuty-i-valiut-v-los-andzelese-crypto-to-cash-la-19":"Crypto exchange in Los Angeles via Exswaping and Crypto to Cash LA: verify the official site before ordering.",'
    '"exswaping-oficialno-dobavlen-v-monitoring-bestchange-20":"Exswaping on BestChange: what the listing means, how to compare rates, and checks before you exchange."'
    "};"
    'bl=p1u[slug];'
    'if(bl&&loc==="ka")bl=bl.replace(/Exswaping/g,"Exswaping (GE)");'
    'if(bl&&loc==="zh")bl=bl+" Official Exswaping crypto exchange guides and order rules.";'
    "}"
    "bl&&this.exsSetMetaDesc(D,bl)"
)


def patch_chunk(text: str) -> tuple[str, list[str]]:
    if MARKER in text:
        return text, []
    changes: list[str] = []

    if FAQ_OLD not in text:
        return text, []

    if P1_BLOCK + FAQ_OLD not in text:
        text = text.replace(FAQ_OLD, P1_BLOCK + FAQ_NEW, 1)
        changes.extend(["p1_trust_cms", "faq_meta"])
    else:
        return text, []

    if BLOG_OLD in text and BLOG_NEW not in text:
        text = text.replace(BLOG_OLD, BLOG_NEW, 1)
        changes.append("uk_ka_blog_meta")

    if changes:
        text = text.rstrip() + f"\n/* {MARKER} */\n"
    return text, changes


def main() -> int:
    errors: list[str] = []
    for lang in LANGS:
        path = DIST_ROOT / lang / "chunk-3RSX4ZSH.mjs"
        if not path.exists():
            errors.append(f"{lang}: missing chunk")
            continue
        text = path.read_text(encoding="utf-8")
        if MARKER in text:
            print(f"[INFO] {lang}: already patched")
            continue
        new_text, changes = patch_chunk(text)
        if not changes:
            errors.append(f"{lang}: anchors not matched")
            continue
        path.write_text(new_text, encoding="utf-8")
        print(f"[OK] {lang}: {', '.join(changes)}")

    if errors:
        for e in errors:
            print(f"[ERROR] {e}", file=sys.stderr)
        return 1
    print("P1 meta coverage patch complete")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
