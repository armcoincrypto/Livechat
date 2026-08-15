#!/usr/bin/env python3
"""SEO-10B — Extend blog FAQPage JSON-LD for Priority 2 articles."""
from __future__ import annotations

import sys
from pathlib import Path

DIST_ROOT = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/server")
LANGS = ["ru", "en", "uk", "ka"]
MARKER = "EXS_BLOG_FAQ_SEO10B_V1"

FAQ_RU = {
    "kriptovaliuta-dlia-novickov-vvedenie-v-osnovy-kriptovaliut-blokcein-nft-i-defi-6": [
        ("С чего начать новичку?", "Изучите FAQ, инструкцию и гид USDT → RUB на exswaping.com."),
        ("Что такое блокчейн?", "Распределённый реестр; переводы необратимы."),
        ("Нужен ли опыт для USDT?", "Следуйте заявке на сайте и гиду USDT TRC20."),
        ("Как избежать фишинга?", "Вводите exswaping.com вручную."),
        ("Зачем AML/KYC?", "Защита от мошенничества по политике сервиса."),
        ("Где калькулятор?", "На главной странице Exswaping."),
    ],
    "zakony-i-regulirovanie-obnovleniia-zakonodatelstva-o-kriptovaliute-v-raznyx-stranax-9": [
        ("Это юридическая консультация?", "Нет — только обзор."),
        ("Влияет ли регулирование на обмен?", "Может — проверяйте локальные правила."),
        ("Как Exswaping соблюдает требования?", "Через AML/KYC и правила сервиса."),
        ("Где правила обмена?", "Инструкция и FAQ на сайте."),
        ("Нужны документы?", "В отдельных случаях по AML/KYC."),
        ("Как обменять USDT?", "По официальному гиду на exswaping.com."),
    ],
    "p2p-obmeny-v-rossii-pocemu-rastut-zaderzki-i-kak-exswaping-zashhishhaet-klientov-18": [
        ("Почему задержки P2P?", "Проверки банков и споры между частными лицами."),
        ("Чем отличается Exswaping?", "Онлайн-заявка с адресом депозита из формы."),
        ("Где гид USDT?", "Официальный гид USDT → RUB."),
        ("Задержка выплаты?", "Проверьте статус заявки и поддержку."),
        ("AML/KYC?", "Политика на странице AML/KYC."),
        ("Инструкция?", "Страница instructions."),
    ],
    "rukovodstvo-po-obmenu-kriptovaliuty-na-ukrainskie-karty-s-exswaping-3": [
        ("Какие UA направления?", "В калькуляторе Exswaping."),
        ("Какую сеть USDT?", "См. гиды USDT TRC20 и TRC20 vs ERC20."),
        ("Срок выплаты?", "На странице выбранной пары."),
        ("Ошибка в карте?", "Поддержка до отправки криптовалюты."),
        ("AML/KYC?", "Политика AML/KYC."),
        ("Инструкция?", "Страница instructions."),
    ],
    "rukovodstvo-po-obmenu-kriptovaliuty-na-kriptovaliutu-s-exswaping-4": [
        ("Где crypto-crypto пара?", "В калькуляторе Exswaping."),
        ("Как проверить сеть?", "Сверьте network в кошельке с заявкой."),
        ("Комиссии?", "Сетевая в кошельке; итог в заявке."),
        ("Задержка?", "Подтверждения блокчейна; см. FAQ."),
        ("AML/KYC?", "Политика AML/KYC."),
        ("Поддержка?", "Официальные контакты на сайте."),
    ],
    "exswaping-prinimaet-samye-izvestnye-kriptovaliuty-dlia-obmena-5": [
        ("Где список монет?", "В калькуляторе — актуальные пары."),
        ("Можно ли USDT?", "Да — гиды USDT на сайте."),
        ("BTC/ETH доступны?", "Если пара есть в калькуляторе."),
        ("Выбор сети USDT?", "Гид TRC20 vs ERC20."),
        ("AML/KYC?", "Политика AML/KYC."),
        ("FAQ?", "Раздел FAQ Exswaping."),
    ],
    "exswaping-oficialno-dobavlen-v-monitoring-bestchange-20": [
        ("Listing заменяет сайт?", "Нет — заявка только через exswaping.com."),
        ("Как проверить сервис?", "Домен exswaping.com и инструкция."),
        ("Где гид USDT?", "Гид USDT → RUB."),
        ("Вопросы по заявке?", "Контакты и FAQ."),
        ("AML/KYC?", "Политика AML/KYC."),
        ("Безопасность?", "Статья о защите средств в блоге."),
    ],
    "exswaping-teper-na-cryptoru-12": [
        ("Что даёт профиль Crypto.ru?", "Информационная точка, не замена сайта."),
        ("Как создать заявку?", "Калькулятор на exswaping.com."),
        ("USDT направления?", "Гид USDT → RUB."),
        ("Поддержка?", "Контакты на сайте."),
        ("Правила?", "Инструкция и AML/KYC."),
        ("FAQ?", "FAQ Exswaping."),
    ],
}

FAQ_EN = {
    "kriptovaliuta-dlia-novickov-vvedenie-v-osnovy-kriptovaliut-blokcein-nft-i-defi-6": [
        ("Where should beginners start?", "FAQ, instructions, and USDT exchange guide on exswaping.com."),
        ("What is blockchain?", "A distributed ledger; transfers are irreversible."),
        ("Experience for USDT?", "Follow the on-site order and USDT TRC20 guide."),
        ("Avoid phishing?", "Type exswaping.com manually."),
        ("Why AML/KYC?", "Fraud prevention per service policy."),
        ("Calculator where?", "Exswaping homepage."),
    ],
    "zakony-i-regulirovanie-obnovleniia-zakonodatelstva-o-kriptovaliute-v-raznyx-stranax-9": [
        ("Legal advice?", "No — overview only."),
        ("Regulation affect exchange?", "It may — check local rules."),
        ("Exswaping compliance?", "Via AML/KYC and service rules."),
        ("Exchange rules?", "Instructions and FAQ."),
        ("Documents needed?", "In specific AML/KYC cases."),
        ("Exchange USDT how?", "Official guide on exswaping.com."),
    ],
    "p2p-obmeny-v-rossii-pocemu-rastut-zaderzki-i-kak-exswaping-zashhishhaet-klientov-18": [
        ("Why P2P delays?", "Bank checks and private disputes."),
        ("Exswaping difference?", "On-site order with form deposit address."),
        ("USDT guide?", "Official USDT exchange guide."),
        ("Payout delay?", "Check order status and support."),
        ("AML/KYC?", "AML/KYC policy page."),
        ("Instructions?", "Instructions page."),
    ],
    "rukovodstvo-po-obmenu-kriptovaliuty-na-ukrainskie-karty-s-exswaping-3": [
        ("UA routes?", "Exswaping calculator."),
        ("USDT network?", "USDT TRC20 guides."),
        ("Payout timing?", "Selected route page."),
        ("Wrong card?", "Support before sending crypto."),
        ("AML/KYC?", "AML/KYC policy."),
        ("Instructions?", "Instructions page."),
    ],
    "rukovodstvo-po-obmenu-kriptovaliuty-na-kriptovaliutu-s-exswaping-4": [
        ("Crypto-crypto pair?", "Exswaping calculator."),
        ("Verify network?", "Match wallet network to order."),
        ("Fees?", "Network in wallet; net in order."),
        ("Delays?", "Blockchain confirmations; FAQ."),
        ("AML/KYC?", "AML/KYC policy."),
        ("Support?", "Official site contacts."),
    ],
    "exswaping-prinimaet-samye-izvestnye-kriptovaliuty-dlia-obmena-5": [
        ("Coin list?", "Live pairs in calculator."),
        ("Exchange USDT?", "Yes — USDT guides on site."),
        ("BTC/ETH?", "If pair exists in calculator."),
        ("USDT network?", "USDT TRC20 guide."),
        ("AML/KYC?", "AML/KYC policy."),
        ("FAQ?", "Exswaping FAQ."),
    ],
    "exswaping-oficialno-dobavlen-v-monitoring-bestchange-20": [
        ("Listing replaces site?", "No — orders via exswaping.com only."),
        ("Verify service?", "Domain exswaping.com and instructions."),
        ("USDT guide?", "USDT exchange guide."),
        ("Order questions?", "Contacts and FAQ."),
        ("AML/KYC?", "AML/KYC policy."),
        ("Security?", "Fund protection blog article."),
    ],
    "exswaping-teper-na-cryptoru-12": [
        ("Crypto.ru profile?", "Information touchpoint, not site replacement."),
        ("Create order?", "Calculator on exswaping.com."),
        ("USDT routes?", "USDT exchange guide."),
        ("Support?", "Site contacts."),
        ("Rules?", "Instructions and AML/KYC."),
        ("FAQ?", "Exswaping FAQ."),
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


RU_OLD = 'let faq10aRu='
RU_INSERT = f'let faq10bRu={faq_js(FAQ_RU)};let faq10aRu='
RU_LOGIC_OLD = "if(faq10aRu[slug]){let me=faq10aRu[slug].map"
RU_LOGIC_NEW = "let faqRu=faq10aRu[slug]||faq10bRu[slug];if(faqRu){let me=faqRu.map"

EN_OLD = "let faq10aEn="
EN_INSERT = f"let faq10bEn={faq_js(FAQ_EN)};let faq10aEn="
EN_LOGIC_OLD = "if(faq10aEn[slug]){let me=faq10aEn[slug].map"
EN_LOGIC_NEW = "let faqEn=faq10aEn[slug]||faq10bEn[slug];if(faqEn){let me=faqEn.map"


def patch_chunk(text: str) -> tuple[str, list[str]]:
    if MARKER in text:
        return text, []
    changes: list[str] = []
    if RU_OLD in text and "faq10bRu" not in text:
        text = text.replace(RU_OLD, RU_INSERT, 1)
        changes.append("faq10b_ru_data")
    if RU_LOGIC_OLD in text and RU_LOGIC_NEW not in text:
        text = text.replace(RU_LOGIC_OLD, RU_LOGIC_NEW, 1)
        changes.append("faq10b_ru_logic")
    if EN_OLD in text and "faq10bEn" not in text:
        text = text.replace(EN_OLD, EN_INSERT, 1)
        changes.append("faq10b_en_data")
    if EN_LOGIC_OLD in text and EN_LOGIC_NEW not in text:
        text = text.replace(EN_LOGIC_OLD, EN_LOGIC_NEW, 1)
        changes.append("faq10b_en_logic")
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
