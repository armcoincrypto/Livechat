#!/usr/bin/env python3
"""Generate SEO-10B Priority 2 blog body HTML (RU + EN)."""
from __future__ import annotations

import re
import sys
from pathlib import Path

OUT = Path(__file__).resolve().parent.parent / "storage/app/seo/content"
IDS = [6, 9, 18, 3, 4, 5, 20, 12]
MIN = {6: 1500, 9: 1500, 18: 1500, 3: 1500, 4: 1500, 5: 1500, 20: 800, 12: 800}


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
    return f'<div class="news-seo10b-body">{b}</div>'


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

PRACTICE_RU = block(
    "Практические рекомендации перед заявкой",
    [
        "Сверьте домен exswaping.com, выберите направление в <a href=\"/ru/\">калькуляторе</a> и прочитайте <a href=\"/ru/pages/instructions\">инструкцию</a> до отправки криптовалюты.",
        "Сохраните номер заявки и хеш перевода. При вопросах используйте <a href=\"/ru/contacts\">контакты</a> и <a href=\"/ru/faq\">FAQ</a> — не доверяйте «операторам» из личных сообщений.",
    ],
    4,
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


def trust_ru(intro: str, title: str, faq_items: list[tuple[str, str]]) -> str:
    return wrap(
        block(
            title,
            [
                intro,
                "Материал носит образовательный характер и не является юридической или инвестиционной консультацией. Перед обменом изучите <a href=\"/ru/guides/obmen-usdt-na-rubli\">гид USDT → RUB</a>, <a href=\"/ru/guides/obmen-usdt-trc20\">USDT TRC20</a> и <a href=\"/ru/pages/AMLKYC\">AML/KYC</a>.",
                "Практические шаги безопасности описаны также в <a href=\"/ru/blog/rukovodstvo-po-bezopasnosti-kak-izbezat-mosennicestva-i-zashhitit-svoi-sredstva-7\">статье о защите средств</a>.",
            ],
            9,
        )
        + block(
            "Риски и типовые ошибки пользователей",
            [
                "Фишинг, поддельные зеркала и «гарантированные» курсы в мессенджерах остаются главными угрозами. Сверяйте URL и не переводите криптовалюту вне заявки на сайте.",
                "Ошибка сети USDT — частая причина задержек. См. <a href=\"/ru/guides/usdt-trc20-i-erc20\">TRC20 vs ERC20</a> и <a href=\"/ru/guides/obmen-usdt-na-kartu\">вывод на карту</a>.",
                "При сомнениях остановите перевод и напишите в <a href=\"/ru/contacts\">контакты</a> с номером заявки.",
            ],
            9,
        )
        + block(
            "Комплаенс и прозрачность сервиса",
            [
                "Exswaping применяет процедуры AML/KYC согласно <a href=\"/ru/pages/AMLKYC\">политике</a>. Это снижает риск мошенничества для пользователей.",
                "Условия обмена, лимиты и статусы заявок описаны в <a href=\"/ru/faq\">FAQ</a> и <a href=\"/ru/pages/instructions\">инструкции</a>.",
                "Сравнивайте итог «к получению» в <a href=\"/ru/\">калькуляторе</a>, а не только рекламный заголовок курса.",
            ],
            9,
        )
        + PRACTICE_RU
        + EXTRA_RU
        + faq(faq_items)
    )


def trust_en(intro: str, title: str, faq_items: list[tuple[str, str]]) -> str:
    return wrap(
        block(
            title,
            [
                intro,
                "This material is educational, not legal or investment advice. Before exchanging read <a href=\"/en/guides/usdt-exchange\">USDT exchange</a>, <a href=\"/en/guides/usdt-trc20-exchange\">USDT TRC20</a>, and <a href=\"/en/pages/AMLKYC\">AML/KYC</a>.",
                "Security steps are also covered in our <a href=\"/en/blog/rukovodstvo-po-bezopasnosti-kak-izbezat-mosennicestva-i-zashhitit-svoi-sredstva-7\">fund protection article</a>.",
            ],
            9,
        )
        + block(
            "User risks and common mistakes",
            [
                "Phishing, fake mirrors, and guaranteed rates in messengers remain top threats. Verify the URL and never send crypto outside an on-site order.",
                "Wrong USDT network often causes delays. See <a href=\"/en/guides/usdt-to-bank-card\">USDT to bank card</a> and TRC20 guide.",
                "If unsure, stop the transfer and contact <a href=\"/en/pages/contacts\">support</a> with your order ID.",
            ],
            9,
        )
        + block(
            "Compliance and service transparency",
            [
                "Exswaping applies AML/KYC per the <a href=\"/en/pages/AMLKYC\">policy</a>.",
                "Limits and order statuses are in <a href=\"/en/faq\">FAQ</a> and <a href=\"/en/pages/instructions\">instructions</a>.",
                "Compare net payout in the <a href=\"/en/\">calculator</a>, not headline ads.",
            ],
            9,
        )
        + EXTRA_EN
        + faq(faq_items, ru=False)
    )


def commercial_ru(intro: str, title: str, faq_items: list[tuple[str, str]]) -> str:
    return wrap(
        block(
            title,
            [
                intro,
                "Актуальные пары и лимиты — в <a href=\"/ru/\">калькуляторе Exswaping</a>. См. также <a href=\"/ru/guides/obmen-usdt-trc20\">USDT TRC20</a> и <a href=\"/ru/guides/usdt-trc20-i-erc20\">выбор сети</a>.",
                "Пошаговый порядок — в <a href=\"/ru/pages/instructions\">инструкции</a>; вопросы — в <a href=\"/ru/faq\">FAQ</a>.",
            ],
            9,
        )
        + block(
            "Как работает направление обмена",
            [
                "Вы выбираете пару «отдаёте криптовалюту → получаете фиат/другой актив», указываете сумму и реквизиты, создаёте заявку и отправляете актив на адрес из формы.",
                "Итог к получению фиксируется в заявке на момент расчёта. Сетевая комиссия оплачивается отдельно в кошельке отправителя.",
                "Срок зависит от подтверждений блокчейна и загрузки маршрута — ориентиры на странице пары.",
            ],
            9,
        )
        + block(
            "Безопасность и AML/KYC",
            [
                "Используйте только exswaping.com. Процедуры комплаенса — <a href=\"/ru/pages/AMLKYC\">AML/KYC</a>.",
                "Не передавайте seed-фразы. При ошибке в реквизитах свяжитесь с <a href=\"/ru/contacts\">поддержкой</a> до отправки.",
            ],
            9,
        )
        + block(
            "Типовые ошибки",
            [
                "Неверная сеть или сумма, перевод после таймера, фишинговые ссылки.",
                "Перед крупной суммой проверьте направление на минимально допустимой сумме, если лимиты позволяют.",
            ],
            9,
        )
        + PRACTICE_RU
        + EXTRA_RU
        + faq(faq_items)
    )


def commercial_en(intro: str, title: str, faq_items: list[tuple[str, str]]) -> str:
    return wrap(
        block(
            title,
            [
                intro,
                "Live pairs and limits are in the <a href=\"/en/\">Exswaping calculator</a>. See <a href=\"/en/guides/usdt-trc20-exchange\">USDT TRC20</a> and <a href=\"/en/guides/usdt-exchange\">USDT exchange</a> guides.",
                "Step flow is in <a href=\"/en/pages/instructions\">instructions</a>; questions in <a href=\"/en/faq\">FAQ</a>.",
            ],
            9,
        )
        + block(
            "How the exchange direction works",
            [
                "Pick pair, amount, and payout details, create the order, and send crypto to the deposit address shown.",
                "Net payout is fixed at order creation. Network fees are paid separately in your wallet.",
                "Timing depends on confirmations and route load — see the pair page.",
            ],
            9,
        )
        + block(
            "Security and AML/KYC",
            [
                "Use only exswaping.com. Compliance: <a href=\"/en/pages/AMLKYC\">AML/KYC</a>.",
                "Never share seed phrases. Contact <a href=\"/en/pages/contacts\">support</a> before sending if details are wrong.",
            ],
            9,
        )
        + block(
            "Common mistakes",
            [
                "Wrong network or amount, paying after timer expiry, phishing links.",
                "For large amounts verify the route on a small test if limits allow.",
            ],
            9,
        )
        + EXTRA_EN
        + faq(faq_items, ru=False)
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
                "При вопросах — <a href=\"/ru/contacts\">контакты поддержки</a>. Для USDT см. <a href=\"/ru/guides/obmen-usdt-na-rubli\">гид USDT → RUB</a>.",
            ],
            8,
        )
        + block(
            "Безопасность и доверие",
            [
                "Exswaping не обещает фиксированных сроков вне правил конкретной заявки.",
                "Сохраняйте номер заявки и хеш перевода. Подробнее о защите — в <a href=\"/ru/blog/rukovodstvo-po-bezopasnosti-kak-izbezat-mosennicestva-i-zashhitit-svoi-sredstva-7\">статье о безопасности</a>.",
            ],
            6,
        )
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
                "Questions: <a href=\"/en/pages/contacts\">support contacts</a>. For USDT see <a href=\"/en/guides/usdt-exchange\">USDT exchange guide</a>.",
            ],
            7,
        )
        + block(
            "Safety and trust",
            [
                "Exswaping does not promise fixed timing outside specific order rules.",
                "Keep order ID and transaction hash. See our <a href=\"/en/blog/rukovodstvo-po-bezopasnosti-kak-izbezat-mosennicestva-i-zashhitit-svoi-sredstva-7\">security article</a>.",
            ],
            6,
        )
        + faq(faq_items, ru=False)
    )


ARTICLES: dict[int, dict[str, str]] = {
    6: {
        "ru": trust_ru(
            "Криптовалюты и блокчейн часто кажутся сложными новичкам. Эта статья объясняет базовые понятия и как безопасно подойти к первому обмену через Exswaping.",
            "Криптовалюта для новичков: основы и безопасный обмен",
            [
                ("С чего начать новичку?", "Изучите <a href=\"/ru/faq\">FAQ</a>, <a href=\"/ru/pages/instructions\">инструкцию</a> и гид <a href=\"/ru/guides/obmen-usdt-na-rubli\">USDT → RUB</a>."),
                ("Что такое блокчейн простыми словами?", "Распределённый реестр транзакций; переводы необратимы — проверяйте адрес и сеть."),
                ("Нужен ли опыт для обмена USDT?", "Достаточно следовать заявке на exswaping.com и гиду USDT TRC20."),
                ("Как не попасть на фишинг?", "Вводите exswaping.com вручную; не делитесь seed-фразами."),
                ("Зачем AML/KYC?", "См. <a href=\"/ru/pages/AMLKYC\">политику</a> — защита от мошенничества."),
                ("Где калькулятор?", "<a href=\"/ru/\">Главная страница Exswaping</a>."),
            ],
        ),
        "en": trust_en(
            "Crypto and blockchain can feel complex for beginners. This article explains basics and how to approach a first exchange safely via Exswaping.",
            "Cryptocurrency for beginners: basics and safe exchange",
            [
                ("Where should beginners start?", "Read <a href=\"/en/faq\">FAQ</a>, <a href=\"/en/pages/instructions\">instructions</a>, and <a href=\"/en/guides/usdt-exchange\">USDT exchange guide</a>."),
                ("What is blockchain in simple terms?", "A distributed ledger; transfers are irreversible — verify address and network."),
                ("Experience needed for USDT exchange?", "Follow the on-site order and USDT TRC20 guide."),
                ("Avoid phishing how?", "Type exswaping.com manually; never share seed phrases."),
                ("Why AML/KYC?", "See <a href=\"/en/pages/AMLKYC\">policy</a> — fraud prevention."),
                ("Where is the calculator?", "<a href=\"/en/\">Exswaping homepage</a>."),
            ],
        ),
    },
    9: {
        "ru": trust_ru(
            "Регулирование криптовалют различается по странам и меняется со временем. Пользователям Exswaping важно понимать общие принципы, не подменяя их юридической консультацией.",
            "Законы и регулирование криптовалют для пользователей",
            [
                ("Является ли статья юридической консультацией?", "Нет — только обзор; за советом обратитесь к юристу в вашей юрисдикции."),
                ("Влияет ли регулирование на обмен?", "Может — проверяйте локальные правила перед операциями."),
                ("Как Exswaping соблюдает требования?", "Через <a href=\"/ru/pages/AMLKYC\">AML/KYC</a> и правила сервиса."),
                ("Где правила обмена?", "<a href=\"/ru/pages/instructions\">Инструкция</a> и <a href=\"/ru/faq\">FAQ</a>."),
                ("Нужны документы?", "В отдельных случаях по политике AML/KYC."),
                ("Как обменять USDT легально?", "Следуйте гиду <a href=\"/ru/guides/obmen-usdt-na-rubli\">USDT → RUB</a> на официальном сайте."),
            ],
        ),
        "en": trust_en(
            "Crypto regulation varies by country and changes over time. Exswaping users should understand general principles without treating this as legal advice.",
            "Laws and crypto regulation for users",
            [
                ("Is this legal advice?", "No — overview only; consult a lawyer in your jurisdiction."),
                ("Does regulation affect exchange?", "It may — check local rules before operations."),
                ("How does Exswaping comply?", "Via <a href=\"/en/pages/AMLKYC\">AML/KYC</a> and service rules."),
                ("Exchange rules where?", "<a href=\"/en/pages/instructions\">Instructions</a> and <a href=\"/en/faq\">FAQ</a>."),
                ("Documents required?", "In specific cases per AML/KYC policy."),
                ("Exchange USDT lawfully how?", "Follow <a href=\"/en/guides/usdt-exchange\">USDT guide</a> on the official site."),
            ],
        ),
    },
    18: {
        "ru": trust_ru(
            "P2P-обмены могут задерживаться из‑за проверок контрагентов и банков. Exswaping работает по модели онлайн-заявки с фиксированным депозитом — это снижает часть P2P-рисков.",
            "P2P-задержки и как Exswaping снижает риски",
            [
                ("Почему растут задержки P2P?", "Проверки банков, споры, человеческий фактор между частными лицами."),
                ("Чем отличается Exswaping?", "Заявка на сайте, адрес депозита из формы, поддержка через официальные каналы."),
                ("Где USDT → RUB гид?", "<a href=\"/ru/guides/obmen-usdt-na-rubli\">Официальный гид</a>."),
                ("Что при задержке выплаты?", "Статус заявки, подтверждения, затем <a href=\"/ru/contacts\">контакты</a>."),
                ("AML/KYC?", "<a href=\"/ru/pages/AMLKYC\">Политика сервиса</a>."),
                ("Инструкция?", "<a href=\"/ru/pages/instructions\">Пошаговый порядок</a>."),
            ],
        ),
        "en": trust_en(
            "P2P exchanges may delay due to counterparty and bank checks. Exswaping uses on-site orders with a fixed deposit address, reducing some P2P risks.",
            "P2P delays and how Exswaping reduces risk",
            [
                ("Why P2P delays grow?", "Bank checks, disputes, human factor between private parties."),
                ("How is Exswaping different?", "On-site order, deposit address from the form, official support channels."),
                ("USDT guide where?", "<a href=\"/en/guides/usdt-exchange\">Official USDT guide</a>."),
                ("Payout delayed?", "Order status, confirmations, then <a href=\"/en/pages/contacts\">support</a>."),
                ("AML/KYC?", "<a href=\"/en/pages/AMLKYC\">Service policy</a>."),
                ("Instructions?", "<a href=\"/en/pages/instructions\">Step-by-step flow</a>."),
            ],
        ),
    },
    3: {
        "ru": commercial_ru(
            "Вывод криптовалюты на украинские карты требует выбора корректного направления, сети актива и проверки реквизитов в калькуляторе Exswaping.",
            "Обмен криптовалюты на украинские карты",
            [
                ("Какие направления UA доступны?", "Список в <a href=\"/ru/\">калькуляторе</a> для UAH-маршрутов."),
                ("Какую сеть USDT выбрать?", "См. <a href=\"/ru/guides/obmen-usdt-trc20\">USDT TRC20</a> и <a href=\"/ru/guides/usdt-trc20-i-erc20\">TRC20 vs ERC20</a>."),
                ("Срок выплаты?", "На странице выбранной пары после заявки."),
                ("Ошибка в карте?", "<a href=\"/ru/contacts\">Поддержка</a> до отправки криптовалюты."),
                ("AML/KYC?", "<a href=\"/ru/pages/AMLKYC\">Политика</a>."),
                ("Инструкция?", "<a href=\"/ru/pages/instructions\">Exswaping</a>."),
            ],
        ),
        "en": commercial_en(
            "Withdrawing crypto to Ukrainian cards requires the correct route, asset network, and verified details in the Exswaping calculator.",
            "Exchange crypto to Ukrainian cards",
            [
                ("Which UA routes exist?", "Listed in the <a href=\"/en/\">calculator</a> for UAH pairs."),
                ("Which USDT network?", "See <a href=\"/en/guides/usdt-trc20-exchange\">USDT TRC20</a> guide."),
                ("Payout timing?", "On the selected route page after order creation."),
                ("Wrong card?", "<a href=\"/en/pages/contacts\">Support</a> before sending crypto."),
                ("AML/KYC?", "<a href=\"/en/pages/AMLKYC\">Policy</a>."),
                ("Instructions?", "<a href=\"/en/pages/instructions\">Exswaping</a>."),
            ],
        ),
    },
    4: {
        "ru": commercial_ru(
            "Обмен криптовалюты на криптовалюту через Exswaping выполняется по выбранной паре в калькуляторе с фиксированным адресом депозита и правилами заявки.",
            "Обмен криптовалюты на криптовалюту",
            [
                ("Где выбрать crypto-crypto пару?", "<a href=\"/ru/\">Калькулятор Exswaping</a>."),
                ("Как проверить сеть?", "Сверьте network в кошельке с заявкой; см. <a href=\"/ru/guides/usdt-trc20-i-erc20\">гид по сетям</a>."),
                ("Комиссии?", "Сетевая — в кошельке; итог — в заявке."),
                ("Задержка?", "Подтверждения блокчейна; см. <a href=\"/ru/faq\">FAQ</a>."),
                ("AML/KYC?", "<a href=\"/ru/pages/AMLKYC\">Политика</a>."),
                ("Поддержка?", "<a href=\"/ru/contacts\">Контакты</a>."),
            ],
        ),
        "en": commercial_en(
            "Crypto-to-crypto exchange via Exswaping uses a selected pair in the calculator with a fixed deposit address and order rules.",
            "Exchange cryptocurrency to cryptocurrency",
            [
                ("Where to pick crypto-crypto pairs?", "<a href=\"/en/\">Exswaping calculator</a>."),
                ("Verify network how?", "Match wallet network to the order; see USDT guides."),
                ("Fees?", "Network fee in wallet; net shown in order."),
                ("Delays?", "Blockchain confirmations; see <a href=\"/en/faq\">FAQ</a>."),
                ("AML/KYC?", "<a href=\"/en/pages/AMLKYC\">Policy</a>."),
                ("Support?", "<a href=\"/en/pages/contacts\">Contacts</a>."),
            ],
        ),
    },
    5: {
        "ru": trust_ru(
            "Exswaping поддерживает ряд популярных криптовалют для обмена. Точный список пар и лимитов всегда отображается в калькуляторе на момент заявки.",
            "Поддерживаемые криптовалюты для обмена",
            [
                ("Где полный список монет?", "<a href=\"/ru/\">Калькулятор</a> — актуальные пары."),
                ("Можно ли обменять USDT?", "Да — см. <a href=\"/ru/guides/obmen-usdt-na-rubli\">USDT → RUB</a> и <a href=\"/ru/guides/obmen-usdt-trc20\">TRC20</a>."),
                ("BTC/ETH доступны?", "Если пара есть в калькуляторе для вашего направления."),
                ("Как выбрать сеть USDT?", "<a href=\"/ru/guides/usdt-trc20-i-erc20\">TRC20 vs ERC20</a>."),
                ("AML/KYC?", "<a href=\"/ru/pages/AMLKYC\">Политика</a>."),
                ("FAQ?", "<a href=\"/ru/faq\">Раздел FAQ</a>."),
            ],
        ),
        "en": trust_en(
            "Exswaping supports several popular cryptocurrencies. Exact pairs and limits are always shown live in the calculator at order time.",
            "Supported cryptocurrencies for exchange",
            [
                ("Full coin list where?", "<a href=\"/en/\">Calculator</a> — live pairs."),
                ("Can I exchange USDT?", "Yes — see <a href=\"/en/guides/usdt-exchange\">USDT exchange</a> and TRC20 guide."),
                ("BTC/ETH available?", "If the pair exists in the calculator for your route."),
                ("Pick USDT network how?", "<a href=\"/en/guides/usdt-trc20-exchange\">USDT TRC20 guide</a>."),
                ("AML/KYC?", "<a href=\"/en/pages/AMLKYC\">Policy</a>."),
                ("FAQ?", "<a href=\"/en/faq\">FAQ section</a>."),
            ],
        ),
    },
    20: {
        "ru": news_ru(
            "Exswaping добавлен в мониторинг BestChange — это дополнительный канал, где пользователи могут найти профиль сервиса. Перед обменом всё равно проверяйте условия на exswaping.com.",
            "Exswaping в мониторинге BestChange",
            [
                ("Заменяет ли listing официальный сайт?", "Нет — заявка только через exswaping.com."),
                ("Как проверить, что это Exswaping?", "Домен exswaping.com и <a href=\"/ru/pages/instructions\">инструкция</a>."),
                ("Где гид USDT?", "<a href=\"/ru/guides/obmen-usdt-na-rubli\">USDT → RUB</a>."),
                ("Вопросы по заявке?", "<a href=\"/ru/contacts\">Контакты</a> и <a href=\"/ru/faq\">FAQ</a>."),
                ("AML/KYC?", "<a href=\"/ru/pages/AMLKYC\">Политика</a>."),
                ("Безопасность?", "Статья о <a href=\"/ru/blog/rukovodstvo-po-bezopasnosti-kak-izbezat-mosennicestva-i-zashhitit-svoi-sredstva-7\">защите средств</a>."),
            ],
        ),
        "en": news_en(
            "Exswaping is listed on BestChange monitoring — an additional channel where users can find the service profile. Always verify conditions on exswaping.com before exchanging.",
            "Exswaping on BestChange monitoring",
            [
                ("Does listing replace the official site?", "No — orders only via exswaping.com."),
                ("Verify it is Exswaping how?", "Domain exswaping.com and <a href=\"/en/pages/instructions\">instructions</a>."),
                ("USDT guide?", "<a href=\"/en/guides/usdt-exchange\">USDT exchange</a>."),
                ("Order questions?", "<a href=\"/en/pages/contacts\">Contacts</a> and <a href=\"/en/faq\">FAQ</a>."),
                ("AML/KYC?", "<a href=\"/en/pages/AMLKYC\">Policy</a>."),
                ("Security?", "<a href=\"/en/blog/rukovodstvo-po-bezopasnosti-kak-izbezat-mosennicestva-i-zashhitit-svoi-sredstva-7\">Fund protection article</a>."),
            ],
        ),
    },
    12: {
        "ru": news_ru(
            "Профиль Exswaping на Crypto.ru помогает пользователям узнать о сервисе через каталог. Условия обмена проверяйте только на официальном сайте.",
            "Exswaping на Crypto.ru",
            [
                ("Что даёт профиль на Crypto.ru?", "Дополнительная информационная точка, не замена сайта."),
                ("Как создать заявку?", "<a href=\"/ru/\">Калькулятор</a> на exswaping.com."),
                ("USDT направления?", "<a href=\"/ru/guides/obmen-usdt-na-rubli\">Гид USDT → RUB</a>."),
                ("Поддержка?", "<a href=\"/ru/contacts\">Контакты</a>."),
                ("Правила?", "<a href=\"/ru/pages/instructions\">Инструкция</a> и AML/KYC."),
                ("FAQ?", "<a href=\"/ru/faq\">FAQ Exswaping</a>."),
            ],
        ),
        "en": news_en(
            "The Exswaping profile on Crypto.ru helps users discover the service via a catalog. Verify exchange conditions only on the official site.",
            "Exswaping on Crypto.ru",
            [
                ("What does the Crypto.ru profile provide?", "An extra information touchpoint, not a site replacement."),
                ("Create an order how?", "<a href=\"/en/\">Calculator</a> on exswaping.com."),
                ("USDT routes?", "<a href=\"/en/guides/usdt-exchange\">USDT exchange guide</a>."),
                ("Support?", "<a href=\"/en/pages/contacts\">Contacts</a>."),
                ("Rules?", "<a href=\"/en/pages/instructions\">Instructions</a> and AML/KYC."),
                ("FAQ?", "<a href=\"/en/faq\">Exswaping FAQ</a>."),
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
            ok = "OK" if w >= MIN[aid] else "LOW"
            print(f"{path.name}: {w} [{ok}] min={MIN[aid]}")
            if w < MIN[aid]:
                failed.append(str(path))
    if failed:
        print("FAIL", failed, file=sys.stderr)
        return 1
    print("SUMMARY", len(IDS) * 2, "files")
    return 0


if __name__ == "__main__":
    sys.exit(main())
