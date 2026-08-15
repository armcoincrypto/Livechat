#!/usr/bin/env python3
"""P1 v3 — Blog meta via exsApplySeo6Schema + UK/KA fallback slug normalize."""
from __future__ import annotations

import sys
from pathlib import Path

DIST_ROOT = Path("/var/www/exswaping_co_usr/data/www/exswaping.com/dist/exchanger/server")
LANGS = ["ru", "en", "uk", "ka"]
MARKER = "EXS_SEO_P1_META_COVERAGE_V3"

OLD = "if(headline){let graphBlog=["
NEW = "if(headline){this.exsSetMetaDesc(D,desc);let graphBlog=["

BLOG_OLD = "bl=(bp[loc]||bp.en)[slug];"
BLOG_NEW = (
    "bl=(bp[loc]||bp.en)[slug];"
    "if(!bl){let _nid=slug.match(/-(\\d+)$/),_ns=_nid?slug.replace(/-\\d+$/,''):slug;"
    "bl=(bp[loc]||bp.en)[_nid?(_nid[1]+'-'+_ns):slug]||(bp.en||{})[_nid?(_nid[1]+'-'+_ns):slug];}"
    "if(!bl&&(loc===\"uk\"||loc===\"ka\"||loc===\"zh\")){"
    "let p1u={"
    '"kak-vygodno-i-bezopasno-obmeniat-usdt-na-rubli-v-2025-godu-11":"USDT to rubles on Exswaping: risks, method comparison, and a link to the official USDT to RUB exchange guide.",'
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
    "};"
    'bl=p1u[slug]||p1u[_ns];'
    'if(bl&&loc==="ka")bl=bl.replace(/Exswaping/g,"Exswaping (GE)");'
    "}"
    "bl&&this.exsSetMetaDesc(D,bl)"
)


def patch_chunk(text: str) -> tuple[str, list[str]]:
    if MARKER in text:
        return text, []
    changes: list[str] = []
    if OLD in text and NEW not in text:
        text = text.replace(OLD, NEW, 1)
        changes.append("schema_blog_meta")
    if BLOG_OLD in text and "p1u[slug]" not in text:
        # only replace first blog lookup if not already v1 patched
        if "if(!bl&&(loc===\"uk\"" in text:
            changes.append("blog_already_v1")
        else:
            text = text.replace(BLOG_OLD, BLOG_NEW, 1)
            changes.append("blog_slug_normalize")
    elif "if(!bl&&(loc===\"uk\"" in text and "p1u[_ns]" not in text:
        pass
    if changes or MARKER not in text:
        if changes:
            text = text.rstrip() + f"\n/* {MARKER} */\n"
    return text, changes


def main() -> int:
    errors: list[str] = []
    for lang in LANGS:
        path = DIST_ROOT / lang / "chunk-3RSX4ZSH.mjs"
        text = path.read_text(encoding="utf-8")
        if MARKER in text:
            print(f"[INFO] {lang}: already v3")
            continue
        new_text, changes = patch_chunk(text)
        if not changes:
            # still try schema patch only
            if OLD in text:
                new_text = text.replace(OLD, NEW, 1)
                changes = ["schema_blog_meta"]
            else:
                errors.append(f"{lang}: no anchors")
                continue
        path.write_text(new_text, encoding="utf-8")
        print(f"[OK] {lang}: {', '.join(changes)}")
    if errors:
        for e in errors:
            print(f"[ERROR] {e}", file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
