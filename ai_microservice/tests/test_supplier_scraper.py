"""Unit tests for the supplier scraping workflow."""
from __future__ import annotations

from typing import Any, Dict, List

import pytest

from ai_microservice import supplier_scraper as supplier_scraper_module
from ai_microservice.supplier_scraper import DummyLLMProvider, SupplierScraper, SupplierScraperError


def _disable_sleep(monkeypatch: pytest.MonkeyPatch) -> None:
    """Prevent real sleep calls so tests run quickly."""
    monkeypatch.setattr(supplier_scraper_module.time, "sleep", lambda _seconds: None)


def test_scrape_suppliers_returns_normalized_profiles(monkeypatch: pytest.MonkeyPatch) -> None:
    scraper = SupplierScraper(llm_provider=DummyLLMProvider())
    _disable_sleep(monkeypatch)

    hits: List[Dict[str, Any]] = [
        {"url": "https://acme.test/about", "name": "Acme Fabrication", "snippet": "Top shops"},
        {"url": "https://zenparts.test/", "name": "Zen Parts", "snippet": "CNC"},
        {"url": "https://ignored.test", "name": "Ignored", "snippet": "Skip"},
    ]

    def fake_search(self: SupplierScraper, query: str, region: str | None, limit: int) -> List[Dict[str, Any]]:
        assert query.strip() == "cnc machining"
        assert region == "Austin"
        assert limit >= len(hits)
        return hits

    def always_allowed(self: SupplierScraper, url: str) -> bool:
        return True

    pages = {
        "https://acme.test/about": "<html><body>Precision machining for aerospace</body></html>",
        "https://zenparts.test/": "<html><body>Zen focus on CNC work</body></html>",
        "https://ignored.test": "<html>Should never be read</html>",
    }

    def fetch_page(self: SupplierScraper, url: str) -> str:
        return pages[url]

    def extract_profile(self: SupplierScraper, query: str, region: str | None, page_text: str, url: str) -> Dict[str, Any]:
        normalized_query = query.strip().title()
        return {
            "name": f"{normalized_query} Inc",
            "website": url,
            "confidence": 0.95,
            "metadata_json": {"llm": True},
            "product_summary": page_text[:120],
        }

    monkeypatch.setattr(SupplierScraper, "_search_web", fake_search)
    monkeypatch.setattr(SupplierScraper, "_is_allowed", always_allowed)
    monkeypatch.setattr(SupplierScraper, "_fetch_page", fetch_page)
    monkeypatch.setattr(SupplierScraper, "_extract_profile", extract_profile)

    results = scraper.scrape_suppliers("  cnc machining  ", "Austin", 2)

    assert len(results) == 2
    assert results[0]["name"] == "Cnc Machining Inc"
    assert {result["website"] for result in results} == {
        "https://acme.test/about",
        "https://zenparts.test/",
    }
    assert all(result["metadata_json"]["search_snippet"] for result in results)
    assert all(0.0 <= float(result["confidence"]) <= 1.0 for result in results)
    assert results[0]["product_summary"].startswith("Precision machining")


def test_scrape_suppliers_skips_disallowed_and_uses_fallback(monkeypatch: pytest.MonkeyPatch) -> None:
    scraper = SupplierScraper(llm_provider=DummyLLMProvider())
    _disable_sleep(monkeypatch)

    hits: List[Dict[str, Any]] = [
        {"url": "https://blocked.test", "name": "Blocked", "snippet": "Nope"},
        {"url": "https://alpha.test", "name": "Alpha Metals", "snippet": "Alpha"},
        {"url": "https://beta.test/info", "name": "Beta Composites", "snippet": "Beta"},
    ]

    def fake_search(self: SupplierScraper, query: str, region: str | None, limit: int) -> List[Dict[str, Any]]:
        assert query == "advanced composites"
        assert region is None
        return hits

    def selective_allowed(self: SupplierScraper, url: str) -> bool:
        return not url.startswith("https://blocked")

    def fetch_page(self: SupplierScraper, url: str) -> str | None:
        return "<html><body>Sample content for %s</body></html>" % url

    def extract_profile(self: SupplierScraper, query: str, region: str | None, page_text: str, url: str) -> Dict[str, Any]:
        if "alpha" in url:
            raise SupplierScraperError("llm failure")
        return {
            "name": None,
            "website": None,
            "confidence": 2.5,
            "metadata_json": {"source": "llm"},
        }

    monkeypatch.setattr(SupplierScraper, "_search_web", fake_search)
    monkeypatch.setattr(SupplierScraper, "_is_allowed", selective_allowed)
    monkeypatch.setattr(SupplierScraper, "_fetch_page", fetch_page)
    monkeypatch.setattr(SupplierScraper, "_extract_profile", extract_profile)

    results = scraper.scrape_suppliers("advanced composites", None, 3)

    assert len(results) == 2
    first, second = results
    assert first["name"].startswith("Alpha")  # falls back to hostname inference
    assert first["confidence"] == pytest.approx(0.2, rel=0.01)
    assert second["name"].startswith("Beta")
    assert second["confidence"] == 1.0  # clamped to 1.0 despite llm returning 2.5
    assert first["metadata_json"]["source"] == "heuristic"
    assert second["metadata_json"]["search_snippet"] == "Beta"