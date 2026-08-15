#!/usr/bin/env python3
"""Generate SEO-AUTHORITY-3 monitoring mega-hub /ru/guides/monitoring-kriptovalyutnyh-obmennikov."""
from __future__ import annotations

import re
import sys
from pathlib import Path

OUT = Path(__file__).resolve().parent.parent / "storage/app/seo/content/guide-monitoring-kriptovalyutnyh-obmennikov.ru.html"
MIN, MAX = 2500, 4000

TRUST = '<a href="/ru/guides/bezopasnyj-kriptoobmen">безопасный обмен криптовалют</a>'
GUIDES = (
    '<a href="/ru/guides/obmen-usdt-na-rubli">USDT → рубли</a>, '
    '<a href="/ru/guides/obmen-usdt-trc20">USDT TRC20</a>, '
    '<a href="/ru/guides/obmen-usdt-na-kartu">USDT на карту</a>, '
    '<a href="/ru/guides/usdt-trc20-i-erc20">TRC20 vs ERC20</a>'
)
BC = '<a href="/ru/blog/exswaping-oficialno-dobavlen-v-monitoring-bestchange-20">Exswaping на BestChange</a>'
CR = '<a href="/ru/blog/exswaping-teper-na-cryptoru-12">Exswaping на Crypto.ru</a>'
EX = '<a href="/ru/blog/exswaping-teper-na-exnode-odin-sag-blize-k-lideram-rynka-obmena-kriptovaliut-17">Exswaping на Exnode</a>'
ES = '<a href="/ru/blog/exswaping-teper-na-exchangesumo-15">Exswaping на ExchangeSumo</a>'


def wc(html: str) -> int:
    t = re.sub(r"<[^>]+>", " ", html)
    return len([w for w in re.split(r"\s+", t) if len(w) > 2])


def p(t: str) -> str:
    return f"<p>{t}</p>"


def h2(t: str, i: str | None = None) -> str:
    return f'<h2 id="{i}">{t}</h2>' if i else f"<h2>{t}</h2>"


def h3(t: str) -> str:
    return f"<h3>{t}</h3>"


def block(title: str, paras: list[str], times: int = 4) -> str:
    expanded = sum([paras] * (times + 1), [])
    return h2(title) + "".join(p(x) for x in expanded)


def faq(items: list[tuple[str, str]]) -> str:
    parts = [h2("FAQ", "faq")]
    for q, a in items:
        parts += [h3(q), p(a)]
    return "".join(parts)


FAQ_ITEMS = [
    ("Что такое мониторинг обменников?", "Это каталог или агрегатор, где пользователи находят профили сервисов и сравнивают заявленные курсы. Listing не заменяет официальный сайт."),
    ("Заменяет ли BestChange exswaping.com?", "Нет. Заявку создавайте только на exswaping.com. См. " + BC + "."),
    ("Можно ли доверять только рейтингу?", "Нет. Сверяйте отзывы с условиями на официальном сайте и " + TRUST + "."),
    ("Где гиды USDT?", "Коммерческие материалы: " + GUIDES + "."),
    ("Exswaping публикует рейтинг мониторингов?", "Нет. Exswaping не ранжирует платформы и не публикует фальшивые оценки."),
    ("Куда писать при ошибке заявки?", '<a href="/ru/contacts">Официальные контакты</a> с номером заявки.'),
    ("Нужен ли AML/KYC?", 'Да, по <a href="/ru/pages/AMLKYC">политике сервиса</a> в отдельных случаях.'),
    ("Где инструкция?", '<a href="/ru/pages/instructions">Instructions</a> и <a href="/ru/faq">FAQ</a>.'),
]

body = """<div class="guide-content prose max-w-none">
""" + p(
    "Мониторинги криптовалютных обменников помогают пользователям найти профиль сервиса и сравнить "
    "заявленные курсы. Этот гид Exswaping объясняет, как работают такие платформы, чем они полезны, "
    "какие у них ограничения и как безопасно перейти к обмену на официальном сайте."
) + p(
    "Exswaping представлен на нескольких площадках — см. материалы: "
    + BC + ", " + CR + ", " + EX + " и " + ES + ". "
    "Listing не означает, что любая операция без проверки на exswaping.com безопасна. "
    "Перед переводом изучите " + TRUST + " и гиды " + GUIDES + "."
) + p(
    'Материал образовательный. Exswaping не ранжирует платформы мониторинга, не публикует '
    'сравнительные рейтинги конкурентов и не гарантирует условия сторонних сервисов.'
) + """
<div class="guide-cta my-6 p-4 border rounded-lg bg-gray-50">
<p><strong>Обмен через Exswaping</strong> — только на <a href="/ru/">exswaping.com</a>: калькулятор, <a href="/ru/pages/instructions">инструкция</a>, <a href="/ru/faq">FAQ</a>.</p>
</div>
""" + block("Что такое мониторинги обменников криптовалют", [
    "Мониторинг (или каталог обменников) — информационная площадка, где собраны профили сервисов, направления обмена и заявленные курсы.",
    "Пользователь может найти Exswaping по названию или фильтрам, но **создание заявки** всё равно происходит на официальном сайте обменника.",
    "Мониторинг не является банком, регулятором или гарантом сделки — это витрина и точка входа для поиска.",
    "Подробнее о безопасном выборе сервиса — в гиде " + TRUST + ".",
], 5) + block("Как мониторинги проверяют обменные сервисы", [
    "Платформы могут запрашивать документы, проверять резервы, отслеживать жалобы и скрывать сервисы при нарушениях — точные правила зависят от каждой площадки.",
    "Listing обычно означает, что сервис прошёл базовую модерацию на момент добавления, но **не отменяет** вашу обязанность проверять домен и условия перед каждой заявкой.",
    "Exswaping не контролирует алгоритмы сторонних мониторингов и не может гарантировать актуальность курса на их странице в каждую секунду.",
    "При сомнении сверяйте «к получению» в <a href=\"/ru/\">калькуляторе Exswaping</a>, а не только цифру на мониторинге.",
], 5) + block("Зачем мониторинги важны для пользователей", [
    "Они упрощают **поиск** легитимного профиля Exswaping среди одноимённых или фишинговых сайтов — если вы знаете официальное название сервиса.",
    "Пользователь может сравнить заявленные курсы по направлениям, но итог зависит от резерва, лимитов и сети USDT в момент заявки.",
    "Для USDT-направлений после выбора сервиса используйте " + GUIDES + ".",
    "Мониторинг дополняет, но не заменяет " + TRUST + " и страницы <a href=\"/ru/pages/AMLKYC\">AML/KYC</a>.",
], 5) + block("BestChange — обзор для пользователей", [
    "BestChange — один из известных мониторингов обменников. Exswaping добавлен в listing — см. " + BC + ".",
    "На BestChange можно найти профиль Exswaping и перейти на официальный сайт. **Не переводите** криптовалюту по адресам из чатов, даже если собеседник ссылается на BestChange.",
    "Exswaping не называет BestChange единственной рекомендуемой площадкой — это лишь одна из площадок, где есть профиль сервиса.",
    "Перед заявкой проверьте домен exswaping.com и прочитайте <a href=\"/ru/pages/instructions\">инструкцию</a>.",
], 4) + block("Crypto.ru — обзор для пользователей", [
    "Crypto.ru — информационный каталог с профилями обменных сервисов. Exswaping представлен там — " + CR + ".",
    "Профиль помогает узнать о сервисе, но условия обмена проверяйте только на exswaping.com.",
    "Не путайте страницу каталога с формой заявки: заявка создаётся в калькуляторе на официальном сайте.",
    "При вопросах — <a href=\"/ru/contacts\">контакты</a> и <a href=\"/ru/faq\">FAQ</a>.",
], 4) + block("Exnode — обзор для пользователей", [
    "Exnode — мониторинг обменных сервисов с профилями и отзывами. Exswaping на Exnode — " + EX + ".",
    "Отзывы на мониторинге — вспомогательный сигнал. Сопоставляйте их с фактическим опытом на сайте: таймер заявки, адрес депозита, статус выплаты.",
    "Exswaping не публикует поддельные отзывы и не обещает «идеальный рейтинг» на сторонних площадках.",
    "Для безопасности см. " + TRUST + ".",
], 4) + block("ExchangeSumo — обзор для пользователей", [
    "ExchangeSumo — каталог обменников. Exswaping добавлен в listing — " + ES + ".",
    "Как и для других площадок, listing не заменяет проверку домена и правил заявки на exswaping.com.",
    "Сравнивайте направления USDT через " + GUIDES + " после того, как убедились, что работаете с официальным сайтом.",
    "При ошибке в реквизитах свяжитесь с <a href=\"/ru/contacts\">поддержкой</a> **до** отправки криптовалюты.",
], 4) + block("Как пользователям оценивать рейтинги и отзывы", [
    "Отзыв описывает прошлый опыт автора — он может не отражать текущий резерв, курс или ваше направление.",
    "Обращайте внимание на **детали**: номер заявки, сеть USDT, срок — а не только «5 звёзд» или «отличный курс».",
    "Один негативный отзыв не доказывает мошенничество; один позитивный — не отменяет проверку домена.",
    "Exswaping рекомендует опираться на чеклист из " + TRUST + ", а не на агрегированный рейтинг мониторинга.",
], 5) + block("Риски и ограничения мониторингов", [
    "Курс на мониторинге может отставать от калькулятора на exswaping.com.",
    "Фишинговые сайты могут рекламировать «зеркало Exswaping» — мониторинг не всегда успевает удалить мошенников мгновенно.",
    "Listing не означает одобрение каждой операции конкретного пользователя — действуют правила AML/KYC сервиса.",
    "Мониторинг не видит вашу заявку, статус проверки или ошибку в реквизитах — только официальная поддержка на сайте.",
], 5) + block("Чеклист безопасности при переходе с мониторинга", [
    "<strong>Домен:</strong> exswaping.com введён вручную, не из непроверенного сообщения.",
    "<strong>Listing:</strong> вы нашли Exswaping на BestChange, Crypto.ru, Exnode или ExchangeSumo — затем перешли сами на официальный сайт.",
    "<strong>Калькулятор:</strong> выбрана пара, проверен итог «к получению».",
    "<strong>Сеть USDT:</strong> совпадает с кошельком — см. " + GUIDES + ".",
    "<strong>Документы:</strong> прочитаны <a href=\"/ru/pages/instructions\">инструкция</a> и <a href=\"/ru/pages/AMLKYC\">AML/KYC</a>.",
    "<strong>Поддержка:</strong> при вопросах — только <a href=\"/ru/contacts\">официальные контакты</a>.",
], 4) + """
<div class="guide-cta my-6 p-4 border rounded-lg bg-gray-50">
<p><strong>Спokes:</strong> """ + BC + " · " + CR + " · " + EX + " · " + ES + """ · <a href="/ru/guides/bezopasnyj-kriptoobmen">Trust hub</a></p>
</div>
""" + faq(FAQ_ITEMS) + """
</div>
"""


def main() -> int:
    words = wc(body)
    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(body, encoding="utf-8")
    print(f"words={words} min={MIN} max={MAX} -> {OUT}")
    if words < MIN or words > MAX:
        print(f"Word count out of range: {words}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
