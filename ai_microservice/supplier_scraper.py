"""Web-scraping utilities for supplier discovery workflows."""
from __future__ import annotations

import logging
import os
import time
from dataclasses import dataclass, field
from html.parser import HTMLParser
from pathlib import Path
from typing import Any, Dict, List, Optional
from urllib.parse import urlparse, urljoin
from urllib.robotparser import RobotFileParser

import httpx
from jsonschema import Draft202012Validator, ValidationError

try:  # pragma: no cover - optional dependency load
    from google.api_core import exceptions as google_exceptions
    from google.cloud import discoveryengine_v1beta as discoveryengine
    from google.oauth2 import service_account
    from google.protobuf.json_format import MessageToDict
except ImportError:  # pragma: no cover - dependency not installed
    discoveryengine = None
    google_exceptions = None
    service_account = None
    MessageToDict = None

from ai_microservice.llm_provider import (
    DummyLLMProvider,
    LLMProvider,
    LLMProviderError,
    build_llm_provider,
)
from ai_microservice.schemas import SCRAPED_SUPPLIER_SCHEMA

try:  # pragma: no cover - optional dependency load
    from dotenv import load_dotenv
except ImportError:  # pragma: no cover - dependency not installed
    load_dotenv = None

if load_dotenv:
    env_path = Path(__file__).resolve().parents[1] / ".env"
    load_dotenv(env_path)

LOGGER = logging.getLogger(__name__)
SCRAPED_SUPPLIER_VALIDATOR = Draft202012Validator(SCRAPED_SUPPLIER_SCHEMA)
DEFAULT_USER_AGENT = os.getenv(
    "SUPPLIER_SCRAPER_USER_AGENT",
    "ElementsSupplyScraper/1.0 (+https://elements-supply.ai)",
)
VERTEX_SEARCH_PROJECT_ID = os.getenv("VERTEX_SEARCH_PROJECT_ID")
VERTEX_SEARCH_LOCATION = os.getenv("VERTEX_SEARCH_LOCATION", "global")
VERTEX_SEARCH_DATA_STORE_ID = os.getenv("VERTEX_SEARCH_DATA_STORE_ID")
VERTEX_SEARCH_SERVING_CONFIG = os.getenv("VERTEX_SEARCH_SERVING_CONFIG", "default_config")
VERTEX_SEARCH_SA_KEY_PATH = os.getenv("VERTEX_SEARCH_SA_KEY_PATH")
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
    vertex_project_id: Optional[str] = VERTEX_SEARCH_PROJECT_ID
    vertex_location: str = VERTEX_SEARCH_LOCATION
    vertex_data_store_id: Optional[str] = VERTEX_SEARCH_DATA_STORE_ID
    vertex_serving_config: str = VERTEX_SEARCH_SERVING_CONFIG
    vertex_sa_key_path: Optional[str] = VERTEX_SEARCH_SA_KEY_PATH
    max_page_chars: int = MAX_PAGE_CHARS
    request_timeout: float = SCRAPE_TIMEOUT_SECONDS
    inter_request_delay: float = SCRAPE_DELAY_SECONDS
    user_agent: str = DEFAULT_USER_AGENT

    def __post_init__(self) -> None:
        self._robots_cache: Dict[str, RobotFileParser] = {}
        self._vertex_client: Optional["discoveryengine.SearchServiceClient"] = None
        self._vertex_serving_config_path: Optional[str] = None

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
        return self._search_vertex(search_query, limit)

    def _search_vertex(self, search_query: str, limit: int) -> List[Dict[str, Any]]:
        if discoveryengine is None or service_account is None or google_exceptions is None:
            raise SupplierScraperError(
                "Vertex AI Search dependency is missing. Install google-cloud-discoveryengine."
            )
        if not self.vertex_project_id or not self.vertex_data_store_id:
            message = (
                "Supplier scrape search is not configured. Set VERTEX_SEARCH_PROJECT_ID "
                "and VERTEX_SEARCH_DATA_STORE_ID."
            )
            LOGGER.error(
                "supplier_scrape_search_disabled",
                extra={"reason": "missing_vertex_config", "provider": "vertex_ai_search"},
            )
            raise SupplierScraperError(message)
        if not self.vertex_sa_key_path:
            message = "Supplier scrape search is not configured. Set VERTEX_SEARCH_SA_KEY_PATH."
            LOGGER.error(
                "supplier_scrape_search_disabled",
                extra={"reason": "missing_vertex_credentials", "provider": "vertex_ai_search"},
            )
            raise SupplierScraperError(message)

        client = self._get_vertex_client()
        page_size = max(1, min(limit, 50))
        request = discoveryengine.SearchRequest(
            serving_config=self._vertex_serving_config_path,
            query=search_query,
            page_size=page_size,
            content_search_spec=discoveryengine.SearchRequest.ContentSearchSpec(
                snippet_spec=discoveryengine.SearchRequest.ContentSearchSpec.SnippetSpec(return_snippet=True)
            ),
        )
        hits: List[Dict[str, Any]] = []
        seen_urls: set[str] = set()
        try:
            response = client.search(request=request, timeout=self.request_timeout)
        except google_exceptions.PermissionDenied as exc:
            message = (
                "Vertex AI Search permission denied. Verify the service account has "
                "Discovery Engine access and the data store is reachable."
            )
            LOGGER.warning(
                "supplier_scrape_search_failed",
                exc_info=True,
                extra={"provider": "vertex_ai_search", "error": str(exc)},
            )
            raise SupplierScraperError(message) from exc
        except google_exceptions.ResourceExhausted as exc:
            message = "Vertex AI Search quota exceeded. Reduce request volume or raise quota."
            LOGGER.warning(
                "supplier_scrape_search_failed",
                exc_info=True,
                extra={"provider": "vertex_ai_search", "error": str(exc)},
            )
            raise SupplierScraperError(message) from exc
        except google_exceptions.GoogleAPICallError as exc:
            message = f"Vertex AI Search API error: {exc}"
            LOGGER.warning(
                "supplier_scrape_search_failed",
                exc_info=True,
                extra={"provider": "vertex_ai_search", "error": str(exc)},
            )
            raise SupplierScraperError(message) from exc

        for result in response.results:
            hit = self._vertex_result_to_hit(result)
            url = hit.get("url")
            if not url or url in seen_urls:
                continue
            seen_urls.add(url)
            hits.append(hit)
            if len(hits) >= limit:
                break
        return hits

    def _get_vertex_client(self) -> "discoveryengine.SearchServiceClient":
        if self._vertex_client is not None:
            return self._vertex_client
        if not self.vertex_sa_key_path or not os.path.exists(self.vertex_sa_key_path):
            raise SupplierScraperError(
                "Vertex AI Search credentials missing. Ensure VERTEX_SEARCH_SA_KEY_PATH points to a file."
            )
        credentials = service_account.Credentials.from_service_account_file(self.vertex_sa_key_path)
        location = (self.vertex_location or "global").strip() or "global"
        if location == "global":
            endpoint = "discoveryengine.googleapis.com"
        else:
            endpoint = f"{location}-discoveryengine.googleapis.com"
        self._vertex_client = discoveryengine.SearchServiceClient(
            credentials=credentials,
            client_options={"api_endpoint": endpoint},
        )
        self._vertex_serving_config_path = (
            f"projects/{self.vertex_project_id}/locations/{location}/dataStores/"
            f"{self.vertex_data_store_id}/servingConfigs/{self.vertex_serving_config}"
        )
        return self._vertex_client

    def _vertex_result_to_hit(self, result: Any) -> Dict[str, Any]:
        doc = getattr(result, "document", None)
        derived: Dict[str, Any] = {}
        if doc is not None:
            derived_struct = getattr(doc, "derived_struct_data", None)
            if isinstance(derived_struct, dict):
                derived = derived_struct
            elif derived_struct is not None:
                try:
                    derived = dict(derived_struct)
                except Exception:
                    if MessageToDict:
                        try:
                            derived = MessageToDict(derived_struct)
                        except Exception:
                            derived = {}
                    else:
                        derived = {}
        url = (
            getattr(doc, "uri", None)
            or derived.get("link")
            or derived.get("url")
            or derived.get("uri")
        )
        title = getattr(doc, "title", None) or derived.get("title")
        snippet = ""
        snippets = derived.get("snippets") or derived.get("snippet")
        if isinstance(snippets, list) and snippets:
            first = snippets[0]
            if isinstance(first, dict):
                snippet = str(first.get("snippet") or first.get("text") or "")
            else:
                snippet = str(first)
        elif isinstance(snippets, str):
            snippet = snippets
        return {
            "url": str(url or "").strip(),
            "name": title,
            "snippet": snippet or None,
        }

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
