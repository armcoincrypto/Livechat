#!/usr/bin/env python3
"""SEO-10C — Extend blog FAQPage JSON-LD for Priority 3 articles."""
from __future__ import annotations

import sys
from pathlib import Path

DIST_ROOT = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/server")
LANGS = ["ru", "en", "uk", "ka"]
MARKER = "EXS_BLOG_FAQ_SEO10C_V1"

FAQ_RU = {
    "exswaping-teper-na-exchangesumo-15": [
        ("Заменяет ли listing официальный сайт?", "Нет — заявка только через exswaping.com."),
        ("Как проверить сервис?", "Домен exswaping.com и инструкция."),
        ("Где гид USDT?", "Гиды USDT на exswaping.com."),
        ("Вопросы по заявке?", "Контакты и FAQ на сайте."),
        ("AML/KYC?", "Политика AML/KYC."),
        ("Безопасность?", "Статья о защите средств в блоге."),
    ],
    "top-10-obmennikov-2025-goda-lucsie-servisy-dlia-bystrogo-i-vygodnogo-obmena-kriptovaliuty-16": [
        ("Есть ли рейтинг лучших обменников?", "Exswaping не публикует рейтинги конкурентов."),
        ("С чего начать проверку?", "Калькулятор, инструкция и FAQ на сайте."),
        ("Как выбрать сеть USDT?", "Гиды TRC20 vs ERC20 и USDT TRC20."),
        ("Зачем AML/KYC?", "Защита от мошенничества по политике сервиса."),
        ("Куда писать при ошибке?", "Официальные контакты с номером заявки."),
        ("Где гид USDT → RUB?", "Официальный гид obmen-usdt-na-rubli."),
    ],
    "exswaping-teper-na-exnode-odin-sag-blize-k-lideram-rynka-obmena-kriptovaliut-17": [
        ("Listing заменяет сайт?", "Нет — заявка только через exswaping.com."),
        ("Как убедиться, что это Exswaping?", "Домен exswaping.com и инструкция."),
        ("Где гиды USDT?", "Гиды USDT → RUB и TRC20 на сайте."),
        ("Поддержка?", "Контакты и FAQ Exswaping."),
        ("AML/KYC?", "Политика AML/KYC."),
        ("Вывод на карту?", "Гид USDT на карту на сайте."),
    ],
    "exswaping-obmen-kriptovaliuty-i-valiut-v-los-andzelese-crypto-to-cash-la-19": [
        ("Где создать заявку?", "Калькулятор на exswaping.com."),
        ("Какие направления доступны?", "Актуальный список в калькуляторе."),
        ("USDT направления?", "Гиды USDT на exswaping.com."),
        ("AML/KYC?", "Политика AML/KYC."),
        ("Поддержка?", "Контакты и FAQ."),
        ("Инструкция?", "Страница instructions."),
    ],
}

FAQ_EN = {
    "exswaping-teper-na-exchangesumo-15": [
        ("Does listing replace the official site?", "No — orders only via exswaping.com."),
        ("Verify the service how?", "Domain exswaping.com and instructions."),
        ("USDT guide?", "USDT guides on exswaping.com."),
        ("Order questions?", "Contacts and FAQ on the site."),
        ("AML/KYC?", "AML/KYC policy."),
        ("Security?", "Fund protection blog article."),
    ],
    "top-10-obmennikov-2025-goda-lucsie-servisy-dlia-bystrogo-i-vygodnogo-obmena-kriptovaliuty-16": [
        ("Best exchangers ranking?", "Exswaping does not publish competitor rankings."),
        ("Where to start verification?", "Calculator, instructions, and FAQ."),
        ("Pick USDT network how?", "USDT TRC20 and exchange guides."),
        ("Why AML/KYC?", "Fraud prevention per service policy."),
        ("Wrong order details?", "Official support with order ID."),
        ("USDT exchange guide?", "Official guide on exswaping.com."),
    ],
    "exswaping-teper-na-exnode-odin-sag-blize-k-lideram-rynka-obmena-kriptovaliut-17": [
        ("Listing replaces the site?", "No — orders only via exswaping.com."),
        ("Verify it is Exswaping how?", "Domain exswaping.com and instructions."),
        ("USDT guides?", "USDT exchange and TRC20 guides."),
        ("Support?", "Contacts and FAQ."),
        ("AML/KYC?", "AML/KYC policy."),
        ("Bank card guide?", "USDT to bank card guide."),
    ],
    "exswaping-obmen-kriptovaliuty-i-valiut-v-los-andzelese-crypto-to-cash-la-19": [
        ("Create an order where?", "Calculator on exswaping.com."),
        ("Which routes exist?", "Live list in the calculator."),
        ("USDT routes?", "USDT guides on exswaping.com."),
        ("AML/KYC?", "AML/KYC policy."),
        ("Support?", "Contacts and FAQ."),
        ("Instructions?", "Instructions page."),
    ],
}


def faq_js(items: dict[str, list[tuple[str, str]]]) -> str:
    parts = []
    for slug, qs in items.items():
        qparts = []
        for q, a in qs:
            q_esc = q.replace("\\", "\\\\").replace('"', '\\"')
            a_esc = a.replace("\\", "\\\\").replace('"', '\\"')
            qparts.append(f'{{q:"{q_esc}",a:"{a_esc}"}}')
        parts.append(f'"{slug}":[{",".join(qparts)}]')
    return "{" + ",".join(parts) + "}"


RU_LOGIC_OLD = "let faqRu=faq10aRu[slug]||faq10bRu[slug];if(faqRu){let me=faqRu.map"
RU_LOGIC_NEW = "let faqRu=faq10aRu[slug]||faq10bRu[slug]||faq10cRu[slug];if(faqRu){let me=faqRu.map"
RU_INSERT_ANCHOR = "let faq10bRu="
RU_INSERT = f"let faq10cRu={faq_js(FAQ_RU)};let faq10bRu="

EN_LOGIC_OLD = "let faqEn=faq10aEn[slug]||faq10bEn[slug];if(faqEn){let me=faqEn.map"
EN_LOGIC_NEW = "let faqEn=faq10aEn[slug]||faq10bEn[slug]||faq10cEn[slug];if(faqEn){let me=faqEn.map"
EN_INSERT_ANCHOR = "let faq10bEn="
EN_INSERT = f"let faq10cEn={faq_js(FAQ_EN)};let faq10bEn="


def patch_chunk(text: str) -> tuple[str, list[str]]:
    if MARKER in text:
        return text, []
    changes: list[str] = []
    if RU_INSERT_ANCHOR in text and "faq10cRu" not in text:
        text = text.replace(RU_INSERT_ANCHOR, RU_INSERT, 1)
        changes.append("faq10c_ru_data")
    if RU_LOGIC_OLD in text and RU_LOGIC_NEW not in text:
        text = text.replace(RU_LOGIC_OLD, RU_LOGIC_NEW, 1)
        changes.append("faq10c_ru_logic")
    if EN_INSERT_ANCHOR in text and "faq10cEn" not in text:
        text = text.replace(EN_INSERT_ANCHOR, EN_INSERT, 1)
        changes.append("faq10c_en_data")
    if EN_LOGIC_OLD in text and EN_LOGIC_NEW not in text:
        text = text.replace(EN_LOGIC_OLD, EN_LOGIC_NEW, 1)
        changes.append("faq10c_en_logic")
    if changes:
        text = text.rstrip() + f"\n/* {MARKER} */\n"
    return text, changes


def main() -> int:
    errors: list[str] = []
    for lang in LANGS:
        path = DIST_ROOT / lang / "chunk-3RSX4ZSH.mjs"
        text = path.read_text(encoding="utf-8")
        new_text, changes = patch_chunk(text)
        if not changes and MARKER not in text:
            errors.append(f"{lang}: anchors not matched")
            continue
        if changes:
            path.write_text(new_text, encoding="utf-8")
            print(f"OK {lang}: {','.join(changes)}")
    if errors:
        for e in errors:
            print(f"ERR {e}", file=sys.stderr)
        return 1
    print("SUMMARY patch complete")
    return 0


if __name__ == "__main__":
    sys.exit(main())
