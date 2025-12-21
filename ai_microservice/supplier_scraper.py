"""Web-scraping utilities for supplier discovery workflows."""
from __future__ import annotations

import logging
import os
import time
from dataclasses import dataclass, field
from html.parser import HTMLParser
from typing import Any, Dict, List, Optional
from urllib.parse import urlparse, urljoin
from urllib.robotparser import RobotFileParser

import httpx
from jsonschema import Draft202012Validator, ValidationError

from ai_microservice.llm_provider import (
    DummyLLMProvider,
    LLMProvider,
    LLMProviderError,
    build_llm_provider,
)
from ai_microservice.schemas import SCRAPED_SUPPLIER_SCHEMA

LOGGER = logging.getLogger(__name__)
SCRAPED_SUPPLIER_VALIDATOR = Draft202012Validator(SCRAPED_SUPPLIER_SCHEMA)
DEFAULT_USER_AGENT = os.getenv(
    "SUPPLIER_SCRAPER_USER_AGENT",
    "ElementsSupplyScraper/1.0 (+https://elements-supply.ai)",
)
SEARCH_ENDPOINT = os.getenv(
    "SUPPLIER_SCRAPER_SEARCH_ENDPOINT",
    "https://api.bing.microsoft.com/v7.0/search",
)
SEARCH_API_KEY = os.getenv("SUPPLIER_SCRAPER_SEARCH_KEY")
MAX_PAGE_CHARS = int(os.getenv("SUPPLIER_SCRAPER_MAX_PAGE_CHARS", "8000"))
SCRAPE_TIMEOUT_SECONDS = float(os.getenv("SUPPLIER_SCRAPER_HTTP_TIMEOUT", "12"))
SCRAPE_DELAY_SECONDS = float(os.getenv("SUPPLIER_SCRAPER_DELAY_SECONDS", "1.0"))


class SupplierScraperError(RuntimeError):
    """Raised when the supplier scraping workflow fails."""


class _HTMLTextExtractor(HTMLParser):
    """Minimal HTML -> text helper that skips script/style blocks."""

    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self._skip_depth = 0
        self._buffer: List[str] = []

    def handle_starttag(self, tag: str, attrs):  # type: ignore[override]
        if tag in {"script", "style", "noscript"}:
            self._skip_depth += 1

    def handle_endtag(self, tag: str):  # type: ignore[override]
        if tag in {"script", "style", "noscript"} and self._skip_depth:
            self._skip_depth -= 1

    def handle_data(self, data: str):  # type: ignore[override]
        if self._skip_depth:
            return
        text = data.strip()
        if text:
            self._buffer.append(text)

    def text(self) -> str:
        return " ".join(self._buffer)


@dataclass
class SupplierScraper:
    """Coordinates search -> fetch -> LLM extraction for suppliers."""

    llm_provider: LLMProvider = field(default_factory=lambda: build_llm_provider(os.getenv("AI_LLM_PROVIDER", "dummy")))
    search_endpoint: str = SEARCH_ENDPOINT
    search_api_key: Optional[str] = SEARCH_API_KEY
    max_page_chars: int = MAX_PAGE_CHARS
    request_timeout: float = SCRAPE_TIMEOUT_SECONDS
    inter_request_delay: float = SCRAPE_DELAY_SECONDS
    user_agent: str = DEFAULT_USER_AGENT

    def __post_init__(self) -> None:
        self._robots_cache: Dict[str, RobotFileParser] = {}

    def scrape_suppliers(self, query: str, region: Optional[str], max_results: int) -> List[Dict[str, Any]]:
        if not query.strip():
            raise SupplierScraperError("query cannot be empty")
        max_results = max(1, min(max_results, 25))
        search_hits = self._search_web(query, region, max_results * 2)
        results: List[Dict[str, Any]] = []
        for hit in search_hits:
            if len(results) >= max_results:
                break
            url = hit.get("url")
            if not url or not self._is_allowed(url):
                continue
            html = self._fetch_page(url)
            if not html:
                continue
            page_text = self._extract_text(html)
            if not page_text:
                continue
            try:
                profile = self._extract_profile(query, region, page_text, url)
            except SupplierScraperError:
                profile = self._build_fallback_profile(hit, page_text, url)
            normalized = self._finalize_profile(profile, hit, url)
            results.append(normalized)
            time.sleep(self.inter_request_delay)
        return results

    # --- search helpers ---------------------------------------------------------------------
    def _search_web(self, query: str, region: Optional[str], limit: int) -> List[Dict[str, Any]]:
        search_query = query.strip()
        if region:
            search_query = f"{search_query} {region.strip()}"
        if not self.search_api_key:
            LOGGER.warning("supplier_scrape_search_disabled", extra={"reason": "missing_api_key"})
            return []
        headers = {
            "Ocp-Apim-Subscription-Key": self.search_api_key,
            "User-Agent": self.user_agent,
        }
        params = {"q": search_query, "count": max(5, min(limit, 30)), "responseFilter": "Webpages"}
        try:
            response = httpx.get(
                self.search_endpoint,
                params=params,
                headers=headers,
                timeout=self.request_timeout,
            )
            response.raise_for_status()
        except httpx.HTTPError as exc:
            LOGGER.warning("supplier_scrape_search_failed", exc_info=True, extra={"error": str(exc)})
            return []
        data = response.json()
        web_pages = (data.get("webPages") or {}).get("value") or []
        hits: List[Dict[str, Any]] = []
        seen_urls: set[str] = set()
        for item in web_pages:
            url = str(item.get("url") or "").strip()
            if not url or url in seen_urls:
                continue
            seen_urls.add(url)
            hits.append(
                {
                    "url": url,
                    "name": item.get("name"),
                    "snippet": item.get("snippet"),
                }
            )
            if len(hits) >= limit:
                break
        return hits

    # --- http/robots helpers ----------------------------------------------------------------
    def _is_allowed(self, url: str) -> bool:
        parsed = urlparse(url)
        if not parsed.scheme or not parsed.netloc:
            return False
        base = f"{parsed.scheme}://{parsed.netloc}"
        parser = self._robots_cache.get(base)
        if parser is None:
            parser = RobotFileParser()
            robots_url = urljoin(base, "/robots.txt")
            try:
                parser.set_url(robots_url)
                parser.read()
            except Exception:
                LOGGER.debug("supplier_scrape_robots_unavailable", extra={"robots_url": robots_url})
            self._robots_cache[base] = parser
        return parser.can_fetch(self.user_agent, url)

    def _fetch_page(self, url: str) -> Optional[str]:
        headers = {"User-Agent": self.user_agent, "Accept": "text/html,application/xhtml+xml"}
        try:
            response = httpx.get(url, headers=headers, timeout=self.request_timeout, follow_redirects=True)
            response.raise_for_status()
            content_type = response.headers.get("Content-Type", "")
            if "text/html" not in content_type:
                return None
            return response.text
        except httpx.HTTPError as exc:
            LOGGER.debug("supplier_scrape_fetch_failed", extra={"url": url, "error": str(exc)})
            return None

    def _extract_text(self, html: str) -> str:
        parser = _HTMLTextExtractor()
        parser.feed(html)
        text = parser.text()
        if len(text) > self.max_page_chars:
            return text[: self.max_page_chars]
        return text

    # --- LLM extraction ---------------------------------------------------------------------
    def _extract_profile(
        self,
        query: str,
        region: Optional[str],
        page_text: str,
        url: str,
    ) -> Dict[str, Any]:
        if isinstance(self.llm_provider, DummyLLMProvider):
            return self._build_fallback_profile({}, page_text, url)
        context = [
            {
                "doc_id": url,
                "doc_version": "webpage",
                "chunk_id": 0,
                "score": 1.0,
                "title": query,
                "snippet": page_text[: self.max_page_chars],
            }
        ]
        prompt = (
            "Extract a supplier profile JSON payload with the required fields. "
            "If a field is not present in the page, return null for that field. "
            f"Primary search query: {query}. Region preference: {region or 'n/a'}. "
            "Focus on company identity, offerings, and contact information."
        )
        try:
            payload = self.llm_provider.generate_answer(prompt, context, SCRAPED_SUPPLIER_SCHEMA)
        except LLMProviderError as exc:
            LOGGER.warning("supplier_scrape_llm_failure", extra={"url": url, "error": str(exc)})
            return self._build_fallback_profile({}, page_text, url)
        try:
            SCRAPED_SUPPLIER_VALIDATOR.validate(payload)
            return payload
        except ValidationError as exc:
            LOGGER.debug(
                "supplier_scrape_validation_error",
                extra={"url": url, "error": exc.message},
            )
            return self._build_fallback_profile({}, page_text, url)

    @staticmethod
    def _build_fallback_profile(hit: Dict[str, Any], page_text: str, url: str) -> Dict[str, Any]:
        snippet = " ".join(page_text.split())[:500]
        return {
            "name": hit.get("name") or SupplierScraper._infer_name_from_url(url),
            "website": url,
            "description": snippet,
            "industry_tags": None,
            "address": None,
            "city": None,
            "state": None,
            "country": None,
            "phone": None,
            "email": None,
            "contact_person": None,
            "certifications": None,
            "product_summary": snippet,
            "source_url": url,
            "confidence": 0.2,
            "metadata_json": {"source": "heuristic", "excerpt": snippet[:200]},
        }

    def _finalize_profile(self, profile: Dict[str, Any], hit: Dict[str, Any], url: str) -> Dict[str, Any]:
        normalized: Dict[str, Any] = {}
        for key in SCRAPED_SUPPLIER_SCHEMA["properties"].keys():
            normalized[key] = profile.get(key)
        normalized["name"] = normalized.get("name") or hit.get("name") or self._infer_name_from_url(url)
        normalized["website"] = normalized.get("website") or url
        normalized["source_url"] = url
        normalized["confidence"] = self._coerce_confidence(normalized.get("confidence"))
        metadata = normalized.get("metadata_json") or {}
        if not isinstance(metadata, dict):
            metadata = {"note": "invalid_metadata_overridden"}
        metadata.setdefault("search_snippet", hit.get("snippet"))
        normalized["metadata_json"] = metadata
        if isinstance(normalized.get("industry_tags"), list):
            normalized["industry_tags"] = [
                str(tag).strip() for tag in normalized["industry_tags"] if str(tag).strip()
            ] or None
        if isinstance(normalized.get("certifications"), list):
            normalized["certifications"] = [
                str(cert).strip() for cert in normalized["certifications"] if str(cert).strip()
            ] or None
        return normalized

    @staticmethod
    def _infer_name_from_url(url: str) -> str:
        hostname = urlparse(url).netloc or url
        hostname = hostname.split(":")[0]
        if hostname.startswith("www."):
            hostname = hostname[4:]
        return hostname.title()

    @staticmethod
    def _coerce_confidence(value: Any) -> float:
        try:
            confidence = float(value)
        except (TypeError, ValueError):
            confidence = 0.4
        return max(0.0, min(confidence, 1.0))


__all__ = ["SupplierScraper", "SupplierScraperError"]
