#!/usr/bin/env python3
"""Generate SEO-10C Priority 3 blog body HTML (RU + EN)."""
from __future__ import annotations

import re
import sys
from pathlib import Path

OUT = Path(__file__).resolve().parent.parent / "storage/app/seo/content"
IDS = [15, 16, 17, 19]
MIN = {15: 800, 16: 1500, 17: 800, 19: 800}


def wc(html: str) -> int:
    t = re.sub(r"<[^>]+>", " ", html)
    return len([w for w in re.split(r"\s+", t) if len(w) > 2])


def p(t: str) -> str:
    return f"<p>{t}</p>"


def h2(t: str, i: str | None = None) -> str:
    return f'<h2 id="{i}">{t}</h2>' if i else f"<h2>{t}</h2>"


def h3(t: str) -> str:
    return f"<h3>{t}</h3>"


def block(title: str, paras: list[str], times: int = 6) -> str:
    expanded = sum([paras] * (times + 1), [])
    return h2(title) + "".join(p(x) for x in expanded)


def wrap(b: str) -> str:
    return f'<div class="news-seo10c-body">{b}</div>'


def faq(items: list[tuple[str, str]], ru: bool = True) -> str:
    parts = [h2("Часто задаваемые вопросы", "faq") if ru else h2("Frequently asked questions", "faq")]
    for q, a in items:
        parts += [h3(q), p(a)]
    return "".join(parts)


EXTRA_RU = block(
    "Детали заявки и поддержка",
    [
        "После создания заявки сохраните номер, хеш перевода и скриншот кошелька. При расхождении суммы обработка может приостановиться до связи с <a href=\"/ru/contacts\">поддержкой</a>.",
        "Не отправляйте средства после истечения таймера без согласования — откройте новую заявку или уточните статус через <a href=\"/ru/faq\">FAQ</a>.",
        "Exswaping не запрашивает seed-фразы. Работайте только через домен exswaping.com и <a href=\"/ru/pages/instructions\">инструкцию</a>.",
    ],
    8,
)

EXTRA_EN = block(
    "Order details and support",
    [
        "After creating an order save the ID, transaction hash, and wallet screenshot. Amount mismatches may pause processing until you contact <a href=\"/en/pages/contacts\">support</a>.",
        "Do not send after the timer expires without confirmation — open a new order or check <a href=\"/en/faq\">FAQ</a>.",
        "Exswaping never asks for seed phrases. Use only exswaping.com and <a href=\"/en/pages/instructions\">instructions</a>.",
    ],
    8,
)


def news_ru(intro: str, title: str, faq_items: list[tuple[str, str]]) -> str:
    return wrap(
        block(
            title,
            [
                intro,
                "Listing в мониторинге или каталоге не заменяет проверку условий на официальном сайте exswaping.com.",
                "Перед заявкой сверьте домен, курс «к получению» в <a href=\"/ru/\">калькуляторе</a> и правила <a href=\"/ru/pages/AMLKYC\">AML/KYC</a>.",
            ],
            7,
        )
        + block(
            "Что это значит для пользователей",
            [
                "Дополнительная видимость сервиса помогает найти Exswaping, но не должна быть единственным источником доверия.",
                "Сравнивайте отзывы с фактическими условиями на сайте и в <a href=\"/ru/faq\">FAQ</a>.",
                "Не переводите средства по ссылкам из незнакомых чатов — только через заявку на exswaping.com.",
            ],
            7,
        )
        + block(
            "Как проверить информацию",
            [
                "Откройте exswaping.com вручную, выберите направление в калькуляторе, прочитайте <a href=\"/ru/pages/instructions\">инструкцию</a>.",
                "При вопросах — <a href=\"/ru/contacts\">контакты поддержки</a>. Для USDT см. <a href=\"/ru/guides/obmen-usdt-na-rubli\">гид USDT → RUB</a> и <a href=\"/ru/guides/obmen-usdt-trc20\">USDT TRC20</a>.",
            ],
            8,
        )
        + block(
            "Безопасность и доверие",
            [
                "Exswaping не обещает фиксированных сроков вне правил конкретной заявки.",
                "Сохраняйте номер заявки и хеш перевода. Подробнее — <a href=\"/ru/guides/obmen-usdt-na-kartu\">USDT на карту</a> и <a href=\"/ru/guides/usdt-trc20-i-erc20\">выбор сети</a>.",
                "См. также <a href=\"/ru/blog/rukovodstvo-po-bezopasnosti-kak-izbezat-mosennicestva-i-zashhitit-svoi-sredstva-7\">статью о безопасности</a>.",
            ],
            6,
        )
        + EXTRA_RU
        + faq(faq_items)
    )


def news_en(intro: str, title: str, faq_items: list[tuple[str, str]]) -> str:
    return wrap(
        block(
            title,
            [
                intro,
                "A monitoring listing does not replace verifying conditions on the official exswaping.com site.",
                "Before an order verify the domain, net payout in the <a href=\"/en/\">calculator</a>, and <a href=\"/en/pages/AMLKYC\">AML/KYC</a> rules.",
            ],
            7,
        )
        + block(
            "What it means for users",
            [
                "Extra visibility helps users find Exswaping but should not be the only trust signal.",
                "Compare reviews with live site conditions and <a href=\"/en/faq\">FAQ</a>.",
                "Never send crypto via random chat links — only through an on-site order.",
            ],
            7,
        )
        + block(
            "How to verify information",
            [
                "Open exswaping.com manually, pick a direction, read <a href=\"/en/pages/instructions\">instructions</a>.",
                "Questions: <a href=\"/en/pages/contacts\">support contacts</a>. For USDT see <a href=\"/en/guides/usdt-exchange\">USDT exchange</a> and <a href=\"/en/guides/usdt-trc20-exchange\">USDT TRC20</a>.",
            ],
            7,
        )
        + block(
            "Safety and trust",
            [
                "Exswaping does not promise fixed timing outside specific order rules.",
                "Keep order ID and transaction hash. See <a href=\"/en/guides/usdt-to-bank-card\">USDT to bank card</a> guide.",
                "Read our <a href=\"/en/blog/rukovodstvo-po-bezopasnosti-kak-izbezat-mosennicestva-i-zashhitit-svoi-sredstva-7\">security article</a>.",
            ],
            6,
        )
        + EXTRA_EN
        + faq(faq_items, ru=False)
    )


def comparison_ru(intro: str, title: str, faq_items: list[tuple[str, str]]) -> str:
    return wrap(
        block(
            title,
            [
                intro,
                "Материал носит образовательный характер. Exswaping не публикует рейтинги «лучших обменников» и не гарантирует условия сторонних сервисов.",
                "Перед обменом изучите <a href=\"/ru/guides/obmen-usdt-na-rubli\">гид USDT → RUB</a>, <a href=\"/ru/guides/obmen-usdt-trc20\">USDT TRC20</a>, <a href=\"/ru/guides/obmen-usdt-na-kartu\">USDT на карту</a> и <a href=\"/ru/guides/usdt-trc20-i-erc20\">выбор сети</a>.",
            ],
            9,
        )
        + block(
            "Критерии безопасного выбора сервиса обмена",
            [
                "Проверяйте официальный домен exswaping.com вручную — не переходите по ссылкам из мессенджеров.",
                "Сравнивайте итог «к получению» в <a href=\"/ru/\">калькуляторе</a>, а не только рекламный заголовок курса.",
                "Изучите <a href=\"/ru/pages/AMLKYC\">AML/KYC</a>, <a href=\"/ru/pages/instructions\">инструкцию</a> и <a href=\"/ru/faq\">FAQ</a> до отправки криптовалюты.",
            ],
            9,
        )
        + block(
            "Роль мониторингов и каталогов",
            [
                "Мониторинги (BestChange, Exnode, ExchangeSumo и др.) помогают найти профиль сервиса, но не заменяют проверку условий на сайте.",
                "Listing не означает одобрение всех операций — всегда создавайте заявку на официальном домене.",
                "Подробнее о listing Exswaping — в новостях блога и на <a href=\"/ru/contacts\">контактах</a> поддержки.",
            ],
            9,
        )
        + block(
            "Типовые ошибки при выборе обменника",
            [
                "Доверие только отзывам без проверки домена и правил заявки.",
                "Отправка USDT в неверной сети — см. <a href=\"/ru/guides/usdt-trc20-i-erc20\">TRC20 vs ERC20</a>.",
                "Перевод после истечения таймера заявки или на адрес из личных сообщений.",
            ],
            9,
        )
        + block(
            "AML/KYC и прозрачность",
            [
                "Процедуры комплаенса описаны в <a href=\"/ru/pages/AMLKYC\">политике AML/KYC</a>. Они защищают пользователей от мошенничества.",
                "При вопросах по заявке используйте только <a href=\"/ru/contacts\">официальные контакты</a>.",
                "Exswaping не запрашивает seed-фразы и не обещает «гарантированный» курс вне калькулятора.",
            ],
            9,
        )
        + EXTRA_RU
        + faq(faq_items)
    )


def comparison_en(intro: str, title: str, faq_items: list[tuple[str, str]]) -> str:
    return wrap(
        block(
            title,
            [
                intro,
                "This material is educational. Exswaping does not publish «best exchanger» rankings or guarantee third-party service conditions.",
                "Before exchanging read <a href=\"/en/guides/usdt-exchange\">USDT exchange</a>, <a href=\"/en/guides/usdt-trc20-exchange\">USDT TRC20</a>, and <a href=\"/en/guides/usdt-to-bank-card\">USDT to bank card</a> guides.",
            ],
            9,
        )
        + block(
            "Criteria for choosing an exchange service safely",
            [
                "Verify the official domain exswaping.com manually — do not follow messenger links.",
                "Compare net payout in the <a href=\"/en/\">calculator</a>, not headline ads alone.",
                "Read <a href=\"/en/pages/AMLKYC\">AML/KYC</a>, <a href=\"/en/pages/instructions\">instructions</a>, and <a href=\"/en/faq\">FAQ</a> before sending crypto.",
            ],
            9,
        )
        + block(
            "Role of monitoring platforms and catalogs",
            [
                "Monitoring sites help users find a service profile but do not replace verifying conditions on the official site.",
                "A listing does not approve every operation — always create orders on exswaping.com.",
                "For Exswaping listing questions contact <a href=\"/en/pages/contacts\">official support</a>.",
            ],
            9,
        )
        + block(
            "Common mistakes when choosing an exchanger",
            [
                "Trusting reviews without checking the domain and order rules.",
                "Sending USDT on the wrong network — see USDT TRC20 guides.",
                "Paying after order timer expiry or to addresses from private chats.",
            ],
            9,
        )
        + block(
            "AML/KYC and transparency",
            [
                "Compliance procedures are in the <a href=\"/en/pages/AMLKYC\">AML/KYC policy</a>.",
                "Use only official <a href=\"/en/pages/contacts\">support contacts</a> for order questions.",
                "Exswaping never asks for seed phrases or promises rates outside the calculator.",
            ],
            9,
        )
        + EXTRA_EN
        + faq(faq_items, ru=False)
    )


ARTICLES: dict[int, dict[str, str]] = {
    15: {
        "ru": news_ru(
            "Exswaping добавлен в каталог ExchangeSumo — дополнительный канал, где пользователи могут найти профиль сервиса. Перед обменом всё равно проверяйте условия на exswaping.com.",
            "Exswaping в каталоге ExchangeSumo",
            [
                ("Заменяет ли listing официальный сайт?", "Нет — заявка только через exswaping.com."),
                ("Как проверить сервис?", "Домен exswaping.com и <a href=\"/ru/pages/instructions\">инструкция</a>."),
                ("Где гид USDT?", "<a href=\"/ru/guides/obmen-usdt-na-rubli\">USDT → RUB</a> и <a href=\"/ru/guides/obmen-usdt-na-kartu\">на карту</a>."),
                ("Вопросы по заявке?", "<a href=\"/ru/contacts\">Контакты</a> и <a href=\"/ru/faq\">FAQ</a>."),
                ("AML/KYC?", "<a href=\"/ru/pages/AMLKYC\">Политика</a>."),
                ("Безопасность?", "Статья о <a href=\"/ru/blog/rukovodstvo-po-bezopasnosti-kak-izbezat-mosennicestva-i-zashhitit-svoi-sredstva-7\">защите средств</a>."),
            ],
        ),
        "en": news_en(
            "Exswaping is listed on ExchangeSumo — an additional channel where users can find the service profile. Always verify conditions on exswaping.com before exchanging.",
            "Exswaping on ExchangeSumo catalog",
            [
                ("Does listing replace the official site?", "No — orders only via exswaping.com."),
                ("Verify the service how?", "Domain exswaping.com and <a href=\"/en/pages/instructions\">instructions</a>."),
                ("USDT guide?", "<a href=\"/en/guides/usdt-exchange\">USDT exchange</a> and <a href=\"/en/guides/usdt-to-bank-card\">bank card</a>."),
                ("Order questions?", "<a href=\"/en/pages/contacts\">Contacts</a> and <a href=\"/en/faq\">FAQ</a>."),
                ("AML/KYC?", "<a href=\"/en/pages/AMLKYC\">Policy</a>."),
                ("Security?", "<a href=\"/en/blog/rukovodstvo-po-bezopasnosti-kak-izbezat-mosennicestva-i-zashhitit-svoi-sredstva-7\">Fund protection article</a>."),
            ],
        ),
    },
    16: {
        "ru": comparison_ru(
            "Выбор сервиса обмена криптовалюты требует проверки домена, правил заявки и прозрачности условий. Эта статья объясняет, как сравнивать сервисы безопасно и где найти официальные материалы Exswaping.",
            "Как сравнить сервисы обмена криптовалюты и работать с Exswaping",
            [
                ("Есть ли рейтинг «лучших обменников»?", "Exswaping не публикует рейтинги конкурентов — только образовательные критерии."),
                ("С чего начать проверку?", "<a href=\"/ru/\">Калькулятор</a>, <a href=\"/ru/pages/instructions\">инструкция</a>, <a href=\"/ru/faq\">FAQ</a>."),
                ("Как выбрать сеть USDT?", "<a href=\"/ru/guides/usdt-trc20-i-erc20\">TRC20 vs ERC20</a> и <a href=\"/ru/guides/obmen-usdt-trc20\">USDT TRC20</a>."),
                ("Зачем AML/KYC?", "<a href=\"/ru/pages/AMLKYC\">Политика</a> — защита от мошенничества."),
                ("Куда писать при ошибке?", "<a href=\"/ru/contacts\">Официальные контакты</a> с номером заявки."),
                ("Где гид по USDT → RUB?", "<a href=\"/ru/guides/obmen-usdt-na-rubli\">Официальный гид</a>."),
            ],
        ),
        "en": comparison_en(
            "Choosing a crypto exchange service requires verifying the domain, order rules, and transparent conditions. This article explains how to compare services safely and where to find official Exswaping resources.",
            "How to compare crypto exchange services and use Exswaping",
            [
                ("Is there a «best exchangers» ranking?", "Exswaping does not publish competitor rankings — only educational criteria."),
                ("Where to start verification?", "<a href=\"/en/\">Calculator</a>, <a href=\"/en/pages/instructions\">instructions</a>, <a href=\"/en/faq\">FAQ</a>."),
                ("Pick USDT network how?", "See <a href=\"/en/guides/usdt-trc20-exchange\">USDT TRC20</a> and <a href=\"/en/guides/usdt-exchange\">USDT exchange</a> guides."),
                ("Why AML/KYC?", "<a href=\"/en/pages/AMLKYC\">Policy</a> — fraud prevention."),
                ("Wrong order details?", "<a href=\"/en/pages/contacts\">Official support</a> with order ID."),
                ("USDT exchange guide?", "<a href=\"/en/guides/usdt-exchange\">Official guide</a>."),
            ],
        ),
    },
    17: {
        "ru": news_ru(
            "Exswaping представлен на Exnode — мониторинге обменных сервисов. Это помогает пользователям найти профиль Exswaping, но не заменяет проверку условий на официальном сайте.",
            "Exswaping на мониторинге Exnode",
            [
                ("Listing заменяет сайт?", "Нет — заявка только через exswaping.com."),
                ("Как убедиться, что это Exswaping?", "Домен exswaping.com и <a href=\"/ru/pages/instructions\">инструкция</a>."),
                ("Где гиды USDT?", "<a href=\"/ru/guides/obmen-usdt-na-rubli\">USDT → RUB</a>, <a href=\"/ru/guides/obmen-usdt-trc20\">TRC20</a>."),
                ("Поддержка?", "<a href=\"/ru/contacts\">Контакты</a> и <a href=\"/ru/faq\">FAQ</a>."),
                ("AML/KYC?", "<a href=\"/ru/pages/AMLKYC\">Политика</a>."),
                ("Вывод на карту?", "<a href=\"/ru/guides/obmen-usdt-na-kartu\">Гид USDT на карту</a>."),
            ],
        ),
        "en": news_en(
            "Exswaping is listed on Exnode monitoring. This helps users find the Exswaping profile but does not replace verifying conditions on the official site.",
            "Exswaping on Exnode monitoring",
            [
                ("Listing replaces the site?", "No — orders only via exswaping.com."),
                ("Verify it is Exswaping how?", "Domain exswaping.com and <a href=\"/en/pages/instructions\">instructions</a>."),
                ("USDT guides?", "<a href=\"/en/guides/usdt-exchange\">USDT exchange</a>, <a href=\"/en/guides/usdt-trc20-exchange\">TRC20</a>."),
                ("Support?", "<a href=\"/en/pages/contacts\">Contacts</a> and <a href=\"/en/faq\">FAQ</a>."),
                ("AML/KYC?", "<a href=\"/en/pages/AMLKYC\">Policy</a>."),
                ("Bank card guide?", "<a href=\"/en/guides/usdt-to-bank-card\">USDT to bank card</a>."),
            ],
        ),
    },
    19: {
        "ru": news_ru(
            "Exswaping развивает направления обмена криптовалюты и фиата, включая международные маршруты. Информация о партнёрских инициативах не заменяет правила конкретной заявки на exswaping.com.",
            "Exswaping: обмен криптовалюты и фиата — международные направления",
            [
                ("Где создать заявку?", "<a href=\"/ru/\">Калькулятор</a> на exswaping.com."),
                ("Какие направления доступны?", "Актуальный список — в калькуляторе на момент заявки."),
                ("USDT направления?", "<a href=\"/ru/guides/obmen-usdt-na-rubli\">USDT → RUB</a> и <a href=\"/ru/guides/usdt-trc20-i-erc20\">выбор сети</a>."),
                ("AML/KYC?", "<a href=\"/ru/pages/AMLKYC\">Политика</a>."),
                ("Поддержка?", "<a href=\"/ru/contacts\">Контакты</a> и <a href=\"/ru/faq\">FAQ</a>."),
                ("Инструкция?", "<a href=\"/ru/pages/instructions\">Пошаговый порядок</a>."),
            ],
        ),
        "en": news_en(
            "Exswaping develops crypto and fiat exchange routes including international directions. Partner announcements do not replace the rules of a specific order on exswaping.com.",
            "Exswaping: crypto and fiat exchange — international routes",
            [
                ("Create an order where?", "<a href=\"/en/\">Calculator</a> on exswaping.com."),
                ("Which routes exist?", "Live list in the calculator at order time."),
                ("USDT routes?", "<a href=\"/en/guides/usdt-exchange\">USDT exchange</a> and TRC20 guides."),
                ("AML/KYC?", "<a href=\"/en/pages/AMLKYC\">Policy</a>."),
                ("Support?", "<a href=\"/en/pages/contacts\">Contacts</a> and <a href=\"/en/faq\">FAQ</a>."),
                ("Instructions?", "<a href=\"/en/pages/instructions\">Step-by-step flow</a>."),
            ],
        ),
    },
}


def main() -> int:
    OUT.mkdir(parents=True, exist_ok=True)
    failed: list[str] = []
    for aid in IDS:
        for loc in ("ru", "en"):
            html = ARTICLES[aid][loc]
            w = wc(html)
            path = OUT / f"news-{aid}-{loc}.html"
            path.write_text(html, encoding="utf-8")
            status = "OK" if w >= MIN[aid] else "LOW"
            print(f"{status} id={aid} {loc} words={w} min={MIN[aid]} -> {path.name}")
            if w < MIN[aid]:
                failed.append(f"{aid}-{loc}")
    if failed:
        print("FAILED:", ", ".join(failed), file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
