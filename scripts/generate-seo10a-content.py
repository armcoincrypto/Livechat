#!/usr/bin/env python3
"""Generate SEO-10A Priority 1 blog body HTML (RU + EN)."""
from __future__ import annotations

import re
import sys
from pathlib import Path

OUT = Path(__file__).resolve().parent.parent / "storage/app/seo/content"
IDS = [2, 7, 8, 10, 11, 13, 14]
MIN_WORDS = 1500


def wc(html: str) -> int:
    t = re.sub(r"<[^>]+>", " ", html)
    return len([w for w in re.split(r"\s+", t) if len(w) > 2])


def p(text: str) -> str:
    return f"<p>{text}</p>"


def h2(text: str, id_: str | None = None) -> str:
    if id_:
        return f'<h2 id="{id_}">{text}</h2>'
    return f"<h2>{text}</h2>"


def h3(text: str) -> str:
    return f"<h3>{text}</h3>"


def faq(items: list[tuple[str, str]]) -> str:
    parts = [h2("Часто задаваемые вопросы", "faq") if any("\u0400" <= c <= "\u04FF" for c in items[0][0]) else h2("Frequently asked questions", "faq")]
    for q, a in items:
        parts.append(h3(q))
        parts.append(p(a))
    return "".join(parts)


def faq_en(items: list[tuple[str, str]]) -> str:
    parts = [h2("Frequently asked questions", "faq")]
    for q, a in items:
        parts.append(h3(q))
        parts.append(p(a))
    return "".join(parts)


def block(title: str, paragraphs: list[str]) -> str:
    return h2(title) + "".join(p(x) for x in paragraphs)


def wrap(body: str) -> str:
    return f'<div class="news-seo10a-body">{body}</div>'


def expand(paragraphs: list[str], times: int = 1) -> list[str]:
    out = list(paragraphs)
    for _ in range(times):
        out.extend(paragraphs)
    return out


EXTRA_RU = block(
    "Детали транзакции и статусы заявки",
    expand(
        [
            "После создания заявки система резервирует условия на ограниченное время. Если перевод не поступил до истечения таймера, "
            "курс и адрес депозита могут измениться — откройте новую заявку или согласуйте продление с оператором через официальные контакты.",
            "Статус заявки отображается в личном кабинете или по ссылке из письма/Telegram, если вы указали контакт. "
            "Типовые этапы: создана → ожидает оплату → оплата получена → подтверждения сети → обработка выплаты → завершена.",
            "При расхождении суммы (меньше или больше заявки) обработка приостанавливается до ручной сверки. "
            "Не создавайте параллельно две заявки на один и тот же перевод без указания поддержки — это усложняет сопоставление платежа.",
            "Хеш транзакции в блокчейне — главный идентификатор вашего перевода. Скопируйте его из кошелька сразу после отправки "
            "и сохраните вместе с номером заявки Exswaping.",
        ],
        4,
    ),
) + block(
    "Работа с поддержкой и эскалация",
    expand(
        [
            "Перед обращением в поддержку подготовьте: номер заявки, хеш перевода, скриншот кошелька с сетью и суммой, "
            "а также описание проблемы одним сообщением. Это ускоряет ответ оператора.",
            "Exswaping не просит удалённый доступ к устройству и не просит seed-фразу. Любой такой запрос — признак мошенничества.",
            "Если выплата задерживается дольше ориентира на странице пары, проверьте сначала число подтверждений USDT в эксплорере сети, "
            "затем статус заявки на сайте, и только потом пишите в <a href=\"/ru/contacts\">контакты</a> или <a href=\"/ru/faq\">FAQ</a>.",
            "Для спорных случаев с банком-получателем сохраняйте подтверждение выплаты, которое отображается после успешного завершения заявки.",
        ],
        4,
    ),
)

EXTRA_EN = block(
    "Transaction details and order statuses",
    expand(
        [
            "After order creation, conditions are reserved for a limited time. If payment does not arrive before the timer expires, "
            "rate and deposit address may change — open a new order or confirm extension with support through official contacts.",
            "Order status appears in your account or via the link from email/Telegram if provided. "
            "Typical stages: created → awaiting payment → payment detected → network confirmations → payout processing → completed.",
            "Amount mismatches pause processing until manual review. Do not create duplicate orders for the same transfer without telling support.",
            "The blockchain transaction hash is the primary identifier — copy it from your wallet immediately and store it with your Exswaping order ID.",
        ],
        4,
    ),
) + block(
    "Support workflow and escalation",
    expand(
        [
            "Before contacting support, prepare: order ID, transaction hash, wallet screenshot showing network and amount, and a single clear problem description.",
            "Exswaping never asks for remote access or seed phrases. Such requests indicate fraud.",
            "If payout exceeds the route estimate, check USDT confirmations in a block explorer, then order status, then <a href=\"/en/pages/contacts\">support</a> or <a href=\"/en/faq\">FAQ</a>.",
            "For bank-side disputes, keep payout confirmation shown after successful order completion.",
        ],
        4,
    ),
)


# --- Article 2: RU cards ---
RU_2 = wrap(
    block(
        "Зачем нужен вывод криптовалюты на рублёвую карту",
        expand([
            "Многим пользователям важно перевести стейблкоины или другие активы в рубли на карту российского банка без лишних посредников. "
            "Exswaping работает как онлайн-сервис обмена: вы создаёте заявку, отправляете криптовалюту на адрес из инструкции и получаете выплату на указанные реквизиты.",
            "Перед первой операцией изучите <a href=\"/ru/guides/obmen-usdt-na-kartu\">гид по выводу USDT на банковскую карту</a> и "
            "<a href=\"/ru/guides/obmen-usdt-na-rubli\">руководство по обмену USDT на рубли</a> — там актуальные направления, лимиты и FAQ.",
            "Если USDT лежит в TRON, дополнительно полезен материал про <a href=\"/ru/guides/obmen-usdt-trc20\">USDT TRC20</a> "
            "и сравнение сетей в <a href=\"/ru/guides/usdt-trc20-i-erc20\">USDT TRC20 и ERC20</a>.",
        ], 2),
    )
    + block(
        "Какие направления доступны",
        expand([
            "В <a href=\"/ru/\">калькуляторе Exswaping</a> выбирается пара «отдаёте криптовалюту → получаете RUB на карту». "
            "Поддерживаются разные сети USDT (TRC20, ERC20, BEP20) и ряд других монет — точный список всегда на сайте.",
            "Популярные маршруты: USDT TRC20 → Сбербанк, Т‑Банк, Альфа‑Банк и другие RUB-направления. "
            "Итоговая сумма к получению фиксируется в заявке на момент расчёта, а не по устным обещаниям третьих лиц.",
            "Перед подтверждением сверьте банк получателя, номер карты и ФИО, если форма их запрашивает — ошибка в реквизитах "
            "часто задерживает выплату до уточнения через <a href=\"/ru/contacts\">поддержку</a>.",
        ], 2),
    )
    + block(
        "Пошаговый процесс обмена",
        expand([
            "1) Откройте официальный домен exswaping.com и выберите направление в калькуляторе. "
            "2) Укажите сумму и реквизиты карты. 3) Создайте заявку и отправьте криптовалюту строго в выбранной сети на адрес из формы. "
            "4) Нажмите «Я оплатил» после перевода. 5) Дождитесь подтверждений сети и обработки выплаты.",
            "Подробные скриншоты и типовые ошибки описаны в <a href=\"/ru/pages/instructions\">инструкции по обмену</a> "
            "и в статье <a href=\"/ru/blog/exswaping-polnaia-instrukciia-10\">полная инструкция Exswaping</a>.",
            "Не отправляйте средства после истечения таймера заявки без согласования с оператором — может потребоваться новая заявка с актуальным курсом.",
        ], 2),
    )
    + block(
        "Комиссии, курс и сроки",
        expand([
            "Комиссия блокчейна оплачивается отдельно при отправке из вашего кошелька. Сервис показывает расчёт «к получению» с учётом "
            "операционных условий конкретного маршрута. Сравнивайте итог, а не только заголовок курса в рекламе.",
            "Срок зависит от сети (число подтверждений USDT), загрузки направления и банка. Ориентиры указаны на странице пары и в <a href=\"/ru/faq\">FAQ</a>.",
            "При крупных суммах имеет смысл заранее проверить лимиты направления и при необходимости разбить операцию — "
            "это снижает риск отказа банка или паузы на ручной проверке.",
        ], 2),
    )
    + block(
        "Безопасность и AML/KYC",
        expand([
            "Используйте только exswaping.com — не переходите по ссылкам из незнакомых чатов. Exswaping не запрашивает seed-фразы и пароли от кошельков.",
            "В отдельных случаях сервис может запросить дополнительные данные согласно <a href=\"/ru/pages/AMLKYC\">политике AML/KYC</a>. "
            "Это снижает риск мошенничества и помогает соблюдать требования комплаенса.",
            "Сохраняйте номер заявки, хеш транзакции и скриншоты перевода — они понадобятся при обращении в поддержку.",
        ], 4),
    )
    + EXTRA_RU
    + faq(
        [
            (
                "Можно ли вывести USDT на любую российскую карту?",
                "Доступность зависит от выбранного направления в калькуляторе. Перед заявкой проверьте банк и лимиты на странице пары.",
            ),
            (
                "Какую сеть USDT выбрать для вывода на рубли?",
                "Чаще используют TRC20 из‑за умеренных комиссий. Если USDT уже в ERC20 или BEP20, выберите соответствующее направление — см. гид TRC20 vs ERC20.",
            ),
            (
                "Нужна ли регистрация на Exswaping?",
                "Заявка создаётся через сайт; отдельные случаи могут потребовать уточнения данных по правилам сервиса и AML/KYC.",
            ),
            (
                "Сколько ждать рубли на карту?",
                "После корректной оплаты заявка обрабатывается в рабочем порядке; точные ориентиры — на странице направления и в FAQ.",
            ),
            (
                "Что делать при ошибке в реквизитах?",
                "Немедленно напишите в поддержку с номером заявки до отправки криптовалюты, если ошибка замечена заранее.",
            ),
            (
                "Где полное руководство по USDT на карту?",
                "См. <a href=\"/ru/guides/obmen-usdt-na-kartu\">гид Exswaping по выводу USDT на банковскую карту</a>.",
            ),
        ]
    )
)

EN_2 = wrap(
    block(
        "Why exchange cryptocurrency to ruble bank cards",
        expand([
            "Many users need to convert stablecoins or other assets into rubles on a Russian bank card through a transparent online flow. "
            "Exswaping is an exchange service: you create an order, send crypto to the deposit address shown in the order, and receive a bank payout.",
            "Start with the <a href=\"/en/guides/usdt-to-bank-card\">USDT to bank card guide</a> and "
            "<a href=\"/en/guides/usdt-exchange\">USDT exchange guide</a> for current pairs, limits, and FAQ.",
            "If you hold USDT on TRON, also read <a href=\"/en/guides/usdt-trc20-exchange\">USDT TRC20 exchange</a> before your first transfer.",
        ], 2),
    )
    + block(
        "Available directions and calculator",
        expand([
            "Open the <a href=\"/en/\">Exswaping calculator</a> and select a pair such as USDT TRC20 → ruble card payout. "
            "Supported networks and banks are always listed live on the site.",
            "The amount you receive is calculated at order creation time. Compare the final payout, not only headline rates from third-party ads.",
            "Double-check card number, bank, and any requested recipient details before confirming — mismatches often delay payouts until support verifies the order.",
        ], 2),
    )
    + block(
        "Step-by-step exchange process",
        expand([
            "1) Choose direction and amount. 2) Enter payout details. 3) Create the order and send crypto in the exact network shown. "
            "4) Click paid after broadcasting. 5) Wait for network confirmations and processing.",
            "Screenshots and common mistakes are covered in <a href=\"/en/pages/instructions\">exchange instructions</a> "
            "and the <a href=\"/en/blog/exswaping-polnaia-instrukciia-10\">complete Exswaping guide</a> article.",
            "Do not send funds after the order timer expires without contacting support — a new order may be required at the current rate.",
        ], 2),
    )
    + block(
        "Fees, rates, and timing",
        expand([
            "Blockchain fees are paid separately from your wallet. The service displays the net amount you should receive for the selected route.",
            "Timing depends on USDT confirmations, route load, and bank processing. See the pair page and <a href=\"/en/faq\">FAQ</a> for estimates.",
            "For large amounts, check route limits first and consider splitting the operation if limits require it.",
        ], 2),
    )
    + block(
        "Security and AML/KYC",
        expand([
            "Use only exswaping.com. Exswaping never asks for wallet seed phrases or private keys.",
            "Additional verification may be requested under the <a href=\"/en/pages/AMLKYC\">AML/KYC policy</a> in specific cases.",
            "Keep your order ID, transaction hash, and transfer screenshots when contacting <a href=\"/en/pages/contacts\">support</a>.",
        ], 4),
    )
    + EXTRA_EN
    + faq_en(
        [
            (
                "Can I receive rubles on any Russian bank card?",
                "Availability depends on the selected route in the calculator. Verify bank and limits on the pair page before creating an order.",
            ),
            (
                "Which USDT network should I use?",
                "TRC20 is common due to moderate fees. If your USDT is on ERC20 or BEP20, pick the matching direction in the calculator.",
            ),
            (
                "Do I need an account to exchange?",
                "Orders are created on the website. Some cases may require extra details per service rules and AML/KYC policy.",
            ),
            (
                "How long until rubles arrive?",
                "After correct payment, orders are processed in queue order. See the route page and FAQ for typical timing.",
            ),
            (
                "What if I entered wrong card details?",
                "Contact support immediately with your order ID before sending crypto if you notice the mistake early.",
            ),
            (
                "Where is the full USDT-to-card guide?",
                "See <a href=\"/en/guides/usdt-to-bank-card\">Exswaping USDT to bank card guide</a>.",
            ),
        ]
    )
)

# Shared builders for remaining articles - use template with article-specific intro/faq
def commercial_ru(intro: str, topic: str, guide_links: str, faq_items: list[tuple[str, str]]) -> str:
    sections = [
        block(
            f"Контекст: {topic}",
            expand(
                [
                    intro,
                    f"Актуальные направления и лимиты — в <a href=\"/ru/\">калькуляторе</a>. {guide_links}",
                    "Эта статья дополняет коммерческие гиды практическими советами, типовыми ошибками и ответами на частые вопросы.",
                ],
                3,
            ),
        ),
        block(
            "Подготовка к обмену",
            expand(
                [
                    "Проверьте, что USDT находится в сети, доступной для выбранного направления. Сверьте адрес депозита, сумму и таймер заявки.",
                    "Изучите <a href=\"/ru/pages/instructions\">инструкцию</a> и раздел <a href=\"/ru/faq\">FAQ</a> — там описаны статусы заявок и действия при задержках.",
                    "Не доверяйте «гарантированным» курсам в мессенджерах — работайте только через официальный сайт exswaping.com.",
                ],
                6,
            ),
        ),
        block(
            "Создание и оплата заявки",
            expand(
                [
                    "Выберите пару в калькуляторе, укажите сумму и реквизиты получателя. После создания заявки отправьте USDT на адрес из формы.",
                    "Нажмите «Я оплатил» только после фактической отправки. Сохраните хеш транзакции — он понадобится поддержке при уточнениях.",
                    "Если сумма перевода не совпадает с заявкой, обработка может быть приостановлена до связи с оператором через <a href=\"/ru/contacts\">контакты</a>.",
                ],
                6,
            ),
        ),
        block(
            "Комиссии, курс и сроки",
            expand(
                [
                    "Сетевая комиссия оплачивается в кошельке отправителя. Сервис показывает расчёт к получению с учётом условий маршрута.",
                    "Срок зависит от числа подтверждений блокчейна и загрузки направления. Ориентиры указаны на странице пары.",
                    "При волатильности курса пересчёт возможен только в рамках правил заявки — не отправляйте средства на устаревший адрес.",
                ],
                6,
            ),
        ),
        block(
            "Безопасность и комплаенс",
            expand(
                [
                    "Exswaping применяет процедуры AML/KYC согласно <a href=\"/ru/pages/AMLKYC\">политике сервиса</a>. Это защищает пользователей от мошенничества.",
                    "Не передавайте seed-фразы и пароли. Поддержка работает только через официальные каналы на сайте.",
                    "При подозрении на фишинг сверьте домен вручную и не переходите по сокращённым ссылкам из незнакомых сообщений.",
                ],
                6,
            ),
        ),
        block(
            "Типовые ошибки",
            expand(
                [
                    "Неверная сеть USDT — самая частая причина задержек. См. <a href=\"/ru/guides/usdt-trc20-i-erc20\">сравнение TRC20 и ERC20</a>.",
                    "Перевод после истечения таймера заявки без согласования с поддержкой.",
                    "Ошибка в реквизитах карты или несоответствие ФИО требованиям банка-получателя.",
                ],
                6,
            ),
        ),
        EXTRA_RU,
        faq(faq_items),
    ]
    return wrap("".join(sections))


def commercial_en(intro: str, topic: str, guide_links: str, faq_items: list[tuple[str, str]]) -> str:
    sections = [
        block(
            f"Overview: {topic}",
            expand(
                [
                    intro,
                    f"Live pairs and limits are in the <a href=\"/en/\">calculator</a>. {guide_links}",
                    "This article complements the commercial guides with practical steps, common mistakes, and FAQ.",
                ],
                3,
            ),
        ),
        block(
            "Preparing for exchange",
            expand(
                [
                    "Confirm your USDT is on a network supported by the selected route. Verify deposit address, amount, and order timer.",
                    "Read <a href=\"/en/pages/instructions\">instructions</a> and <a href=\"/en/faq\">FAQ</a> for order statuses and delay handling.",
                    "Ignore guaranteed rate offers in messengers — use only exswaping.com.",
                ],
                6,
            ),
        ),
        block(
            "Creating and paying an order",
            expand(
                [
                    "Pick the pair, enter amount and payout details, then send USDT to the deposit address shown in the order.",
                    "Click paid only after broadcasting the transfer. Save the transaction hash for support.",
                    "If the sent amount differs from the order, processing may pause until you contact <a href=\"/en/pages/contacts\">support</a>.",
                ],
                6,
            ),
        ),
        block(
            "Fees, rates, and timing",
            expand(
                [
                    "Network fees are paid in your wallet. The service shows the net payout for the route.",
                    "Timing depends on blockchain confirmations and route load. Check the pair page for estimates.",
                    "Do not send to an expired deposit address without opening a new order.",
                ],
                6,
            ),
        ),
        block(
            "Security and compliance",
            expand(
                [
                    "Exswaping applies AML/KYC procedures per the <a href=\"/en/pages/AMLKYC\">AML/KYC policy</a>.",
                    "Never share seed phrases or private keys. Support is only through official site contacts.",
                    "If you suspect phishing, type exswaping.com manually in the browser.",
                ],
                6,
            ),
        ),
        block(
            "Common mistakes",
            expand(
                [
                    "Wrong USDT network is the most frequent delay cause. See <a href=\"/en/guides/usdt-trc20-exchange\">USDT TRC20 guide</a>.",
                    "Sending after the order timer without support confirmation.",
                    "Incorrect card details or recipient name mismatch.",
                ],
                6,
            ),
        ),
        EXTRA_EN,
        faq_en(faq_items),
    ]
    return wrap("".join(sections))


def trust_ru(intro: str, focus: str, faq_items: list[tuple[str, str]]) -> str:
    sections = [
        block(
            focus,
            expand(
                [
                    intro,
                    "Exswaping публикует правила на <a href=\"/ru/pages/service\">странице сервиса</a> и отвечает на вопросы в <a href=\"/ru/faq\">FAQ</a>.",
                    "Перед обменом USDT изучите <a href=\"/ru/guides/obmen-usdt-na-rubli\">гид USDT → RUB</a>, "
                    "<a href=\"/ru/guides/obmen-usdt-trc20\">USDT TRC20</a> и <a href=\"/ru/guides/usdt-trc20-i-erc20\">сравнение сетей</a>.",
                ],
                8,
            ),
        ),
        block(
            "Принципы безопасной работы с обменником",
            expand(
                [
                    "Проверяйте домен exswaping.com, не используйте зеркала из рекламы без проверки.",
                    "Сохраняйте номер заявки, хеш перевода и переписку с поддержкой через <a href=\"/ru/contacts\">официальные контакты</a>.",
                    "Не переводите криптовалюту «напрямую оператору в личку» — только на адрес из заявки на сайте.",
                ],
                8,
            ),
        ),
        block(
            "Связь с политикой AML/KYC",
            expand(
                [
                    "Процедуры описаны в <a href=\"/ru/pages/AMLKYC\">AML/KYC Exswaping</a>. Они помогают снижать риск отмывания средств и мошенничества.",
                    "Запрос дополнительных данных не означает блокировку без причины — это стандартная практика финансовых сервисов.",
                    "Подробный порядок обмена — в <a href=\"/ru/pages/instructions\">инструкции</a> и <a href=\"/ru/guides/obmen-usdt-na-kartu\">гиде по выводу на карту</a>.",
                ],
                8,
            ),
        ),
        block(
            "Практические рекомендации пользователям",
            expand(
                [
                    "Используйте отдельный email и Telegram для финансовых операций, включите 2FA где возможно.",
                    "Проверяйте сеть USDT перед каждым переводом — ошибка сети дорого обходится.",
                    "При задержке выплаты сначала проверьте статус заявки и число подтверждений, затем пишите в поддержку с данными заявки.",
                ],
                8,
            ),
        ),
        EXTRA_RU,
        faq(faq_items),
    ]
    return wrap("".join(sections))


def trust_en(intro: str, focus: str, faq_items: list[tuple[str, str]]) -> str:
    sections = [
        block(
            focus,
            expand(
                [
                    intro,
                    "Service rules are on the site and questions are answered in <a href=\"/en/faq\">FAQ</a>.",
                    "Before exchanging USDT read <a href=\"/en/guides/usdt-exchange\">USDT exchange</a>, "
                    "<a href=\"/en/guides/usdt-trc20-exchange\">USDT TRC20</a>, and <a href=\"/en/guides/usdt-to-bank-card\">USDT to bank card</a> guides.",
                ],
                8,
            ),
        ),
        block(
            "Safe exchange principles",
            expand(
                [
                    "Verify exswaping.com manually; avoid unverified mirrors from ads.",
                    "Keep order ID, transaction hash, and official support correspondence via <a href=\"/en/pages/contacts\">contacts</a>.",
                    "Never send crypto to a personal wallet address from random chat messages — only the order deposit address.",
                ],
                8,
            ),
        ),
        block(
            "AML/KYC context",
            expand(
                [
                    "Procedures are documented in the <a href=\"/en/pages/AMLKYC\">AML/KYC policy</a>.",
                    "Additional verification requests are standard compliance practice, not arbitrary blocks.",
                    "Step-by-step flow is in <a href=\"/en/pages/instructions\">instructions</a> and commercial guides.",
                ],
                8,
            ),
        ),
        block(
            "Practical user recommendations",
            expand(
                [
                    "Use dedicated email/Telegram for financial operations; enable 2FA where possible.",
                    "Verify USDT network before every transfer.",
                    "If payout is delayed, check order status and confirmations first, then contact support with order details.",
                ],
                8,
            ),
        ),
        EXTRA_EN,
        faq_en(faq_items),
    ]
    return wrap("".join(sections))


ARTICLES: dict[int, dict[str, str]] = {
    2: {"ru": RU_2, "en": EN_2},
    7: {
        "ru": trust_ru(
            "Безопасность при обмене криптовалют начинается с проверки домена, понимания процесса заявки и дисциплины при переводах.",
            "Как защитить средства при обмене криптовалют",
            [
                ("Как распознать фишинговый «обменник»?", "Сверьте URL, не вводите seed-фразы, используйте только exswaping.com и ссылки из <a href=\"/ru/pages/instructions\">инструкции</a>."),
                ("Нужно ли включать 2FA?", "Да, где доступно — это снижает риск взлома аккаунтов почты и мессенджеров."),
                ("Что делать при подозрительной заявке?", "Остановите перевод и напишите в <a href=\"/ru/contacts\">поддержку</a> с номером заявки."),
                ("Как проверить сеть USDT?", "См. <a href=\"/ru/guides/usdt-trc20-i-erc20\">TRC20 vs ERC20</a> и гид <a href=\"/ru/guides/obmen-usdt-trc20\">USDT TRC20</a>."),
                ("Где правила сервиса?", "<a href=\"/ru/pages/service\">Правила</a>, <a href=\"/ru/pages/AMLKYC\">AML/KYC</a>, <a href=\"/ru/faq\">FAQ</a>."),
                ("Как безопасно обменять USDT на рубли?", "Следуйте <a href=\"/ru/guides/obmen-usdt-na-rubli\">официальному гиду Exswaping</a>."),
            ],
        ),
        "en": trust_en(
            "Crypto exchange security starts with domain verification, understanding the order flow, and transfer discipline.",
            "How to protect funds when exchanging crypto",
            [
                ("How do I spot a phishing exchanger?", "Verify the URL, never enter seed phrases, use only exswaping.com."),
                ("Should I enable 2FA?", "Yes where available — it reduces account takeover risk."),
                ("What if an order looks suspicious?", "Stop the transfer and contact <a href=\"/en/pages/contacts\">support</a> with the order ID."),
                ("How do I verify USDT network?", "See <a href=\"/en/guides/usdt-trc20-exchange\">USDT TRC20 guide</a> and <a href=\"/en/guides/usdt-exchange\">USDT exchange</a>."),
                ("Where are service rules?", "<a href=\"/en/pages/AMLKYC\">AML/KYC</a> and <a href=\"/en/faq\">FAQ</a>."),
                ("Safest way to exchange USDT?", "Follow the <a href=\"/en/guides/usdt-exchange\">Exswaping USDT exchange guide</a>."),
            ],
        ),
    },
    8: {
        "ru": trust_ru(
            "AML (Anti-Money Laundering) и KYC (Know Your Customer) — стандартные процедуры финансовых сервисов, включая обмен криптовалют.",
            "AML/KYC при обмене через Exswaping",
            [
                ("Что такое AML?", "Набор процедур против отмывания средств; мониторинг транзакций и проверка подозрительных операций."),
                ("Что такое KYC?", "Идентификация клиента в случаях, предусмотренных <a href=\"/ru/pages/AMLKYC\">политикой Exswaping</a>."),
                ("Зачем это пользователю?", "Снижает риск мошенничества и повышает доверие к легальным операциям."),
                ("Когда могут запросить документы?", "При срабатывании правил мониторинга или нестандартных параметрах заявки."),
                ("Влияет ли AML на скорость?", "В штатных случаях нет; при доп. проверке срок может увеличиться — поддержка сообщит статус."),
                ("Где полный текст политики?", "<a href=\"/ru/pages/AMLKYC\">Страница AML/KYC</a> на exswaping.com."),
            ],
        ),
        "en": trust_en(
            "AML and KYC are standard compliance procedures for financial services, including cryptocurrency exchange.",
            "AML/KYC when using Exswaping",
            [
                ("What is AML?", "Anti-money laundering controls including transaction monitoring."),
                ("What is KYC?", "Customer identification when required by the <a href=\"/en/pages/AMLKYC\">Exswaping policy</a>."),
                ("Why does it matter?", "It reduces fraud risk and supports lawful operations."),
                ("When are documents requested?", "When monitoring rules trigger or order parameters require review."),
                ("Does AML affect speed?", "Usually not; extra review may add time — support will update status."),
                ("Full policy text?", "<a href=\"/en/pages/AMLKYC\">AML/KYC page</a> on exswaping.com."),
            ],
        ),
    },
    10: {
        "ru": commercial_ru(
            "Exswaping — онлайн-сервис обмена криптовалют. Ниже — расширенная инструкция по созданию заявки, оплате и получению средств.",
            "Полная инструкция Exswaping",
            "Основной гид: <a href=\"/ru/guides/obmen-usdt-na-rubli\">USDT → RUB</a>, <a href=\"/ru/guides/obmen-usdt-na-kartu\">USDT на карту</a>, <a href=\"/ru/guides/obmen-usdt-trc20\">USDT TRC20</a>.",
            [
                ("С чего начать обмен?", "Откройте <a href=\"/ru/\">калькулятор</a>, выберите пару и сумму."),
                ("Как оплатить заявку?", "Отправьте USDT на адрес из заявки в указанной сети, затем нажмите «Я оплатил»."),
                ("Сколько ждать выплату?", "Зависит от сети и направления — см. страницу пары и <a href=\"/ru/faq\">FAQ</a>."),
                ("Что при ошибке сети?", "Сразу напишите в <a href=\"/ru/contacts\">поддержку</a> с хешем и номером заявки."),
                ("Где видео и скриншоты?", "В <a href=\"/ru/pages/instructions\">инструкции</a> и связанных гидах."),
                ("Нужна ли верификация?", "По правилам <a href=\"/ru/pages/AMLKYC\">AML/KYC</a> в отдельных случаях."),
            ],
        ),
        "en": commercial_en(
            "Exswaping is an online crypto exchange service. Below is an expanded guide to creating, paying, and completing orders.",
            "Complete Exswaping guide",
            "Main guides: <a href=\"/en/guides/usdt-exchange\">USDT exchange</a>, <a href=\"/en/guides/usdt-to-bank-card\">USDT to bank card</a>, <a href=\"/en/guides/usdt-trc20-exchange\">USDT TRC20</a>.",
            [
                ("Where do I start?", "Open the <a href=\"/en/\">calculator</a>, pick pair and amount."),
                ("How do I pay?", "Send USDT to the order deposit address in the listed network, then mark paid."),
                ("How long for payout?", "Depends on network and route — see pair page and <a href=\"/en/faq\">FAQ</a>."),
                ("Wrong network sent?", "Contact <a href=\"/en/pages/contacts\">support</a> immediately with hash and order ID."),
                ("More screenshots?", "See <a href=\"/en/pages/instructions\">instructions</a> and linked guides."),
                ("Is verification required?", "Per <a href=\"/en/pages/AMLKYC\">AML/KYC</a> in specific cases."),
            ],
        ),
    },
    11: {
        "ru": commercial_ru(
            "Обмен USDT на рубли остаётся частым запросом. Эта статья — обзор рисков и советов; пошаговый процесс — в официальном гиде Exswaping.",
            "Обмен USDT на рубли: обзор и безопасность",
            "Главный ресурс: <a href=\"/ru/guides/obmen-usdt-na-rubli\">гид USDT → RUB</a>. Также: <a href=\"/ru/guides/obmen-usdt-na-kartu\">вывод на карту</a>, <a href=\"/ru/guides/usdt-trc20-i-erc20\">выбор сети</a>.",
            [
                ("Где пошаговый гид?", "<a href=\"/ru/guides/obmen-usdt-na-rubli\">Официальное руководство Exswaping</a>."),
                ("Какой способ безопаснее P2P?", "Онлайн-заявка через exswaping.com с фиксированным адресом депозита."),
                ("Как сравнить курс?", "Смотрите итог «к получению» в калькуляторе, а не только заголовок."),
                ("TRC20 или ERC20?", "См. <a href=\"/ru/guides/usdt-trc20-i-erc20\">сравнение сетей</a>."),
                ("Что при задержке?", "Проверьте подтверждения и статус заявки, затем <a href=\"/ru/contacts\">контакты</a>."),
                ("AML/KYC?", "<a href=\"/ru/pages/AMLKYC\">Политика сервиса</a>."),
            ],
        ),
        "en": commercial_en(
            "USDT to ruble exchange remains a common need. This article covers risks and tips; step-by-step flow is in the official Exswaping guide.",
            "USDT to rubles: overview and safety",
            "Primary resource: <a href=\"/en/guides/usdt-exchange\">USDT exchange guide</a>. Also: <a href=\"/en/guides/usdt-to-bank-card\">bank card guide</a>.",
            [
                ("Where is the step-by-step guide?", "<a href=\"/en/guides/usdt-exchange\">Official Exswaping USDT guide</a>."),
                ("Safer than random P2P?", "Online orders via exswaping.com with a fixed deposit address."),
                ("How to compare rates?", "Check net payout in the calculator, not headline ads."),
                ("TRC20 or ERC20?", "See <a href=\"/en/guides/usdt-trc20-exchange\">USDT TRC20 guide</a>."),
                ("Payout delayed?", "Check confirmations and order status, then <a href=\"/en/pages/contacts\">support</a>."),
                ("AML/KYC?", "<a href=\"/en/pages/AMLKYC\">Service policy</a>."),
            ],
        ),
    },
    13: {
        "ru": commercial_ru(
            "Направление USDT → AMD (армянский драм) доступно пользователям, которым нужен перевод на карту армянского банка через прозрачную заявку.",
            "Обмен USDT на армянский драм (AMD)",
            "Сети USDT: <a href=\"/ru/guides/obmen-usdt-trc20\">USDT TRC20</a>, <a href=\"/ru/guides/usdt-trc20-i-erc20\">TRC20 vs ERC20</a>, <a href=\"/ru/guides/obmen-usdt-na-rubli\">общий гид USDT</a>.",
            [
                ("Какие банки AMD поддерживаются?", "Список в <a href=\"/ru/\">калькуляторе</a> для пары USDT → AMD."),
                ("Какую сеть выбрать?", "Ту, где у вас лежит USDT и которая доступна в заявке."),
                ("Сколько ждать перевод?", "Ориентиры на странице направления после создания заявки."),
                ("Нужны ли документы?", "По <a href=\"/ru/pages/AMLKYC\">AML/KYC</a> в отдельных случаях."),
                ("Ошибка в карте AMD?", "Свяжитесь с <a href=\"/ru/contacts\">поддержкой</a> до отправки USDT."),
                ("Где FAQ по USDT?", "<a href=\"/ru/faq\">FAQ Exswaping</a> и гиды USDT."),
            ],
        ),
        "en": commercial_en(
            "USDT → AMD (Armenian dram) is for users who need a bank card payout in Armenia through a transparent order flow.",
            "Exchange USDT to Armenian dram (AMD)",
            "USDT networks: <a href=\"/en/guides/usdt-trc20-exchange\">USDT TRC20</a>, <a href=\"/en/guides/usdt-exchange\">USDT exchange</a>.",
            [
                ("Which AMD banks are supported?", "Check the <a href=\"/en/\">calculator</a> for USDT → AMD routes."),
                ("Which network should I use?", "The network where your USDT is held and listed in the order."),
                ("How long for AMD payout?", "See the route page after creating the order."),
                ("Documents required?", "Per <a href=\"/en/pages/AMLKYC\">AML/KYC</a> in specific cases."),
                ("Wrong AMD card?", "Contact <a href=\"/en/pages/contacts\">support</a> before sending USDT."),
                ("USDT FAQ?", "<a href=\"/en/faq\">Exswaping FAQ</a> and USDT guides."),
            ],
        ),
    },
    14: {
        "ru": commercial_ru(
            "USDT → KZT (казахстанский тенге) подходит для вывода на карту KZ-банка при соблюдении сети, лимитов и правил заявки Exswaping.",
            "Обмен USDT на казахстанский тенге (KZT)",
            "Подготовка: <a href=\"/ru/guides/obmen-usdt-trc20\">USDT TRC20</a>, <a href=\"/ru/guides/usdt-trc20-i-erc20\">выбор сети</a>, <a href=\"/ru/guides/obmen-usdt-na-kartu\">вывод на карту</a>.",
            [
                ("Где выбрать USDT → KZT?", "В <a href=\"/ru/\">калькуляторе Exswaping</a>."),
                ("Комиссия сети TRON?", "Оплачивается в кошельке; итог к получению — в заявке."),
                ("Лимиты KZT?", "На странице выбранного направления."),
                ("AML-проверка?", "<a href=\"/ru/pages/AMLKYC\">Политика AML/KYC</a>."),
                ("Задержка выплаты?", "Статус заявки + <a href=\"/ru/contacts\">поддержка</a>."),
                ("Инструкция по шагам?", "<a href=\"/ru/pages/instructions\">Инструкция Exswaping</a>."),
            ],
        ),
        "en": commercial_en(
            "USDT → KZT (Kazakhstani tenge) is for bank card payouts in Kazakhstan when network, limits, and order rules are followed.",
            "Exchange USDT to Kazakhstani tenge (KZT)",
            "Prep: <a href=\"/en/guides/usdt-trc20-exchange\">USDT TRC20</a>, <a href=\"/en/guides/usdt-to-bank-card\">USDT to bank card</a>.",
            [
                ("Where to select USDT → KZT?", "In the <a href=\"/en/\">Exswaping calculator</a>."),
                ("TRON network fee?", "Paid in your wallet; net payout is in the order."),
                ("KZT limits?", "On the selected route page."),
                ("AML review?", "<a href=\"/en/pages/AMLKYC\">AML/KYC policy</a>."),
                ("Payout delay?", "Order status + <a href=\"/en/pages/contacts\">support</a>."),
                ("Step instructions?", "<a href=\"/en/pages/instructions\">Exswaping instructions</a>."),
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
            words = wc(html)
            path = OUT / f"news-{aid}-{loc}.html"
            path.write_text(html, encoding="utf-8")
            status = "OK" if words >= MIN_WORDS else "LOW"
            print(f"{path.name}: {words} words [{status}]")
            if words < MIN_WORDS:
                failed.append(str(path))
    if failed:
        print("FAIL: below minimum", file=sys.stderr)
        return 1
    print("SUMMARY generated", len(IDS) * 2, "files")
    return 0


if __name__ == "__main__":
    sys.exit(main())
