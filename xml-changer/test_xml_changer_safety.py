#!/usr/bin/env python3
"""Unit tests for xml-changer rate-mutation safety (no network, no production writes)."""
from __future__ import annotations

import importlib.util
import os
import sys
import tempfile
import unittest
import xml.etree.ElementTree as ET
from pathlib import Path


def load_module(path: Path, mutate: str = "0"):
    os.environ["EXSWAPING_XML_CHANGER_MUTATE_RATES"] = mutate
    # Ensure fresh import each time
    mod_name = f"xml_changer_under_test_{mutate}"
    spec = importlib.util.spec_from_file_location(mod_name, path)
    assert spec and spec.loader
    mod = importlib.util.module_from_spec(spec)
    # Patch paths before exec by injecting after load via attributes set in main —
    # instead, call functions directly after load.
    spec.loader.exec_module(mod)
    return mod


SAMPLE = """<?xml version='1.0' encoding='utf-8'?>
<rates>
  <item><from>BTC</from><to>CARDGEL</to><in>1</in><out>167000.00</out></item>
  <item><from>USDTTRC20</from><to>CARDGEL</to><in>1</in><out>2.58</out></item>
  <item><from>BTC</from><to>CASHUSD</to><in>1</in><out>64000</out></item>
</rates>
"""


class XmlChangerSafetyTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.script = Path(__file__).resolve().parent / "main.py"

    def test_mutation_disabled_preserves_out(self):
        mod = load_module(self.script, "0")
        root = ET.fromstring(SAMPLE)
        before = {
            (i.findtext("from"), i.findtext("to")): (i.findtext("in"), i.findtext("out"))
            for i in root.findall(".//item")
        }
        # Even if legacy helpers are invoked accidentally, main path must not call them when flag off.
        self.assertFalse(mod.MUTATE_RATES)
        mod.add_url_tags(root)
        after = {
            (i.findtext("from"), i.findtext("to")): (i.findtext("in"), i.findtext("out"))
            for i in root.findall(".//item")
        }
        self.assertEqual(before, after)
        for item in root.findall(".//item"):
            self.assertIsNotNone(item.findtext("url"))

    def test_legacy_bump_would_inflate_by_three_percent(self):
        from decimal import Decimal
        mod = load_module(self.script, "0")
        root = ET.fromstring(SAMPLE)
        mod.bump_out_values(root, Decimal("0.03"))
        btc = next(
            i for i in root.findall(".//item") if i.findtext("from") == "BTC" and i.findtext("to") == "CARDGEL"
        )
        self.assertEqual(btc.findtext("out"), "172010.00")

    def test_opt_in_mutation_flag(self):
        mod = load_module(self.script, "1")
        self.assertTrue(mod.MUTATE_RATES)


if __name__ == "__main__":
    unittest.main()
