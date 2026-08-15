#!/usr/bin/env python3
"""SEO-10A — FAQPage JSON-LD for Priority 1 blog articles (RU + EN)."""
from __future__ import annotations

import sys
from pathlib import Path

DIST_ROOT = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/server")
LANGS = ["ru", "en", "uk", "ka"]
MARKER = "EXS_BLOG_FAQ_SEO10A_V1"

FAQ_RU = {
    "rukovodstvo-po-obmenu-kriptovaliuty-na-rublevye-karty-s-exswaping-2": [
        ("Можно ли вывести USDT на российскую карту?", "Доступность зависит от направления в калькуляторе Exswaping."),
        ("Какую сеть USDT выбрать?", "Чаще TRC20; если USDT в другой сети — выберите соответствующую пару."),
        ("Сколько ждать рубли?", "После оплаты заявки — по правилам выбранного маршрута."),
        ("Где гид по USDT на карту?", "См. /ru/guides/obmen-usdt-na-kartu на exswaping.com."),
        ("Нужна ли верификация?", "По правилам AML/KYC в отдельных случаях."),
        ("Куда писать при ошибке?", "В официальные контакты поддержки на сайте."),
    ],
    "rukovodstvo-po-bezopasnosti-kak-izbezat-mosennicestva-i-zashhitit-svoi-sredstva-7": [
        ("Как распознать фишинг?", "Сверяйте домен exswaping.com, не вводите seed-фразы."),
        ("Нужна ли 2FA?", "Рекомендуется для почты и мессенджеров."),
        ("Что делать при подозрительной заявке?", "Остановите перевод и напишите в поддержку."),
        ("Как проверить сеть USDT?", "См. гид USDT TRC20 и TRC20 vs ERC20."),
        ("Где правила сервиса?", "На страницах правил, AML/KYC и FAQ."),
        ("Как безопасно обменять USDT?", "Следуйте официальному гиду USDT → RUB."),
    ],
    "amlkyc-i-vasa-bezopasnost-obieiasnenie-politiki-po-borbe-s-otmyvaniem-deneg-i-kyc-8": [
        ("Что такое AML?", "Процедуры против отмывания средств и мониторинг транзакций."),
        ("Что такое KYC?", "Идентификация клиента по политике сервиса."),
        ("Зачем это пользователю?", "Снижает риск мошенничества."),
        ("Когда запросят документы?", "При срабатывании правил мониторинга."),
        ("Влияет ли на скорость?", "В штатных случаях нет; при доп. проверке возможна задержка."),
        ("Где полный текст?", "Страница AML/KYC на exswaping.com."),
    ],
    "exswaping-polnaia-instrukciia-10": [
        ("С чего начать обмен?", "Калькулятор на exswaping.com — выбор пары и суммы."),
        ("Как оплатить заявку?", "Отправить USDT на адрес заявки в указанной сети."),
        ("Сколько ждать выплату?", "Зависит от сети и направления."),
        ("Ошибка сети?", "Сразу связаться с поддержкой с хешем транзакции."),
        ("Где инструкция?", "Страница instructions на сайте."),
        ("AML/KYC?", "По политике сервиса в отдельных случаях."),
    ],
    "kak-vygodno-i-bezopasno-obmeniat-usdt-na-rubli-v-2025-godu-11": [
        ("Где пошаговый гид?", "Официальный гид obmen-usdt-na-rubli."),
        ("Безопаснее P2P?", "Заявка через exswaping.com с фиксированным депозитом."),
        ("Как сравнить курс?", "Итог «к получению» в калькуляторе."),
        ("TRC20 или ERC20?", "См. сравнение сетей USDT."),
        ("Задержка выплаты?", "Проверить подтверждения и статус заявки."),
        ("AML/KYC?", "Политика на странице AML/KYC."),
    ],
    "usdt-to-amd-13": [
        ("Какие банки AMD?", "Список в калькуляторе для USDT → AMD."),
        ("Какую сеть выбрать?", "Где лежит USDT и что доступно в заявке."),
        ("Срок перевода?", "На странице направления после создания заявки."),
        ("Нужны документы?", "По AML/KYC в отдельных случаях."),
        ("Ошибка в карте?", "Связаться с поддержкой до отправки USDT."),
        ("FAQ по USDT?", "Раздел FAQ и гиды USDT на сайте."),
    ],
    "usdt-to-kzt-14": [
        ("Где USDT → KZT?", "В калькуляторе Exswaping."),
        ("Комиссия TRON?", "Оплачивается в кошельке; итог в заявке."),
        ("Лимиты KZT?", "На странице направления."),
        ("AML-проверка?", "По политике AML/KYC."),
        ("Задержка?", "Статус заявки и поддержка."),
        ("Инструкция?", "Страница instructions."),
    ],
}

FAQ_EN = {
    "rukovodstvo-po-obmenu-kriptovaliuty-na-rublevye-karty-s-exswaping-2": [
        ("Can I receive rubles on a Russian card?", "Depends on the selected route in the calculator."),
        ("Which USDT network?", "Often TRC20; pick the network matching your balance."),
        ("How long for rubles?", "Per route rules after order payment."),
        ("USDT to card guide?", "See /en/guides/usdt-to-bank-card."),
        ("Verification required?", "Per AML/KYC in specific cases."),
        ("Wrong details?", "Contact official support on the site."),
    ],
    "rukovodstvo-po-bezopasnosti-kak-izbezat-mosennicestva-i-zashhitit-svoi-sredstva-7": [
        ("How to spot phishing?", "Verify exswaping.com; never enter seed phrases."),
        ("Enable 2FA?", "Recommended for email and messengers."),
        ("Suspicious order?", "Stop the transfer and contact support."),
        ("Verify USDT network?", "See USDT TRC20 and exchange guides."),
        ("Service rules?", "AML/KYC page and FAQ."),
        ("Safest USDT exchange?", "Follow the official USDT exchange guide."),
    ],
    "amlkyc-i-vasa-bezopasnost-obieiasnenie-politiki-po-borbe-s-otmyvaniem-deneg-i-kyc-8": [
        ("What is AML?", "Anti-money laundering controls and monitoring."),
        ("What is KYC?", "Customer identification per service policy."),
        ("Why does it matter?", "Reduces fraud risk."),
        ("When are documents requested?", "When monitoring rules trigger."),
        ("Does it affect speed?", "Usually not; extra review may add time."),
        ("Full policy?", "AML/KYC page on exswaping.com."),
    ],
    "exswaping-polnaia-instrukciia-10": [
        ("Where to start?", "Exswaping calculator — pick pair and amount."),
        ("How to pay?", "Send USDT to the order deposit address in the listed network."),
        ("Payout timing?", "Depends on network and route."),
        ("Wrong network?", "Contact support with transaction hash immediately."),
        ("More instructions?", "Instructions page on the site."),
        ("AML/KYC?", "Per policy in specific cases."),
    ],
    "kak-vygodno-i-bezopasno-obmeniat-usdt-na-rubli-v-2025-godu-11": [
        ("Step-by-step guide?", "Official USDT exchange guide."),
        ("Safer than P2P?", "Orders via exswaping.com with fixed deposit address."),
        ("Compare rates?", "Net payout in the calculator."),
        ("TRC20 or ERC20?", "See USDT TRC20 guide."),
        ("Payout delayed?", "Check confirmations and order status."),
        ("AML/KYC?", "Policy on AML/KYC page."),
    ],
    "usdt-to-amd-13": [
        ("Which AMD banks?", "Listed in calculator for USDT → AMD."),
        ("Which network?", "Where your USDT is held and listed in the order."),
        ("AMD payout time?", "On the route page after order creation."),
        ("Documents needed?", "Per AML/KYC in specific cases."),
        ("Wrong card?", "Contact support before sending USDT."),
        ("USDT FAQ?", "FAQ and USDT guides on the site."),
    ],
    "usdt-to-kzt-14": [
        ("Where USDT → KZT?", "Exswaping calculator."),
        ("TRON fee?", "Paid in wallet; net in order."),
        ("KZT limits?", "On the selected route page."),
        ("AML review?", "Per AML/KYC policy."),
        ("Delay?", "Order status and support."),
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


RU_OLD = (
    'if(headline){this.exsInjectJsonLd(D,{"@context":"https://schema.org","@graph":[{"@type":"BreadcrumbList","itemListElement":[{"@type":"ListItem","position":1,"name":"Exswaping","item":t+"/ru/"},{"@type":"ListItem","position":2,"name":"Блог","item":t+"/ru/news"},{"@type":"ListItem","position":3,"name":headline,"item":pageUrl}]},{"@type":"Article","headline":headline,"description":desc,"author":{"@type":"Organization","name":"Exswaping","url":t+"/"},"publisher":{"@type":"Organization","name":"Exswaping","url":t+"/","logo":{"@type":"ImageObject","url":t+"/images/favicons/favicon-96x96.png"}},"mainEntityOfPage":{"@type":"WebPage","@id":pageUrl},"inLanguage":"ru-RU"}]});return}}if(bs&&loc==="en")'
)

RU_NEW = (
    'if(headline){let graphBlog=[{"@type":"BreadcrumbList","itemListElement":[{"@type":"ListItem","position":1,"name":"Exswaping","item":t+"/ru/"},{"@type":"ListItem","position":2,"name":"Блог","item":t+"/ru/news"},{"@type":"ListItem","position":3,"name":headline,"item":pageUrl}]},{"@type":"Article","headline":headline,"description":desc,"author":{"@type":"Organization","name":"Exswaping","url":t+"/"},"publisher":{"@type":"Organization","name":"Exswaping","url":t+"/","logo":{"@type":"ImageObject","url":t+"/images/favicons/favicon-96x96.png"}},"mainEntityOfPage":{"@type":"WebPage","@id":pageUrl},"inLanguage":"ru-RU"}];'
    f'let faq10aRu={faq_js(FAQ_RU)};if(faq10aRu[slug]){{let me=faq10aRu[slug].map(it=>({{"@type":"Question","name":it.q,"acceptedAnswer":{{"@type":"Answer","text":it.a}}}}));graphBlog.push({{"@type":"FAQPage","mainEntity":me}})}}'
    'this.exsInjectJsonLd(D,{"@context":"https://schema.org","@graph":graphBlog});return}}if(bs&&loc==="en")'
)

EN_OLD = (
    'if(headline){this.exsInjectJsonLd(D,{"@context":"https://schema.org","@graph":[{"@type":"BreadcrumbList","itemListElement":[{"@type":"ListItem","position":1,"name":"Exswaping","item":t+"/en/"},{"@type":"ListItem","position":2,"name":"Blog","item":t+"/en/news"},{"@type":"ListItem","position":3,"name":headline,"item":pageUrl}]},{"@type":"Article","headline":headline,"description":desc,"author":{"@type":"Organization","name":"Exswaping","url":t+"/"},"publisher":{"@type":"Organization","name":"Exswaping","url":t+"/","logo":{"@type":"ImageObject","url":t+"/images/favicons/favicon-96x96.png"}},"mainEntityOfPage":{"@type":"WebPage","@id":pageUrl},"inLanguage":"en-US"}]});return}}let guide=/\\/guides\\/obmen-usdt-na-rubli\\/?$/.test(this.exsStripLocalePrefixes(reqPath));'
)

EN_NEW = (
    'if(headline){let graphBlogEn=[{"@type":"BreadcrumbList","itemListElement":[{"@type":"ListItem","position":1,"name":"Exswaping","item":t+"/en/"},{"@type":"ListItem","position":2,"name":"Blog","item":t+"/en/news"},{"@type":"ListItem","position":3,"name":headline,"item":pageUrl}]},{"@type":"Article","headline":headline,"description":desc,"author":{"@type":"Organization","name":"Exswaping","url":t+"/"},"publisher":{"@type":"Organization","name":"Exswaping","url":t+"/","logo":{"@type":"ImageObject","url":t+"/images/favicons/favicon-96x96.png"}},"mainEntityOfPage":{"@type":"WebPage","@id":pageUrl},"inLanguage":"en-US"}];'
    f'let faq10aEn={faq_js(FAQ_EN)};if(faq10aEn[slug]){{let me=faq10aEn[slug].map(it=>({{"@type":"Question","name":it.q,"acceptedAnswer":{{"@type":"Answer","text":it.a}}}}));graphBlogEn.push({{"@type":"FAQPage","mainEntity":me}})}}'
    'this.exsInjectJsonLd(D,{"@context":"https://schema.org","@graph":graphBlogEn});return}}let guide=/\\/guides\\/obmen-usdt-na-rubli\\/?$/.test(this.exsStripLocalePrefixes(reqPath));'
)


def patch_chunk(text: str) -> tuple[str, list[str]]:
    if MARKER in text:
        return text, []
    changes: list[str] = []
    if RU_OLD in text:
        text = text.replace(RU_OLD, RU_NEW, 1)
        changes.append("ru_blog_faq")
    if EN_OLD in text:
        text = text.replace(EN_OLD, EN_NEW, 1)
        changes.append("en_blog_faq")
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
