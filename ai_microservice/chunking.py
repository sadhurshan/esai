"""Utilities for splitting large documents into retrievable chunks."""
from __future__ import annotations

from typing import List


_BOUNDARIES: tuple[str, ...] = (
    "\r\n\r\n",
    "\n\n",
    "\r\n",
    "\n",
    ". ",
    "? ",
    "! ",
    ".\n",
    "?\n",
    "!\n",
)


def _find_preferred_stop(text: str, start: int, hard_stop: int, window: int) -> int:
    """Locate a natural boundary close to the hard stop if one exists."""

    search_start = max(start + 1, hard_stop - window)
    best_match = -1
    for delimiter in _BOUNDARIES:
        idx = text.rfind(delimiter, search_start, hard_stop)
        if idx != -1:
            candidate_end = idx + len(delimiter)
            if candidate_end > best_match:
                best_match = candidate_end
    return best_match


def chunk_text(text: str, max_chars: int = 1500, overlap_chars: int = 200) -> List[dict[str, object]]:
    """Split ``text`` into overlapping chunks suitable for embedding.

    Args:
        text: Input string to chunk.
        max_chars: Maximum characters per chunk before overlap is applied.
        overlap_chars: Shared characters between sequential chunks to preserve context.

    Returns:
        List of chunk dictionaries (chunk_index, text, char_start, char_end).
    """

    if max_chars <= 0:
        raise ValueError("max_chars must be greater than zero")
    if overlap_chars < 0:
        raise ValueError("overlap_chars cannot be negative")
    if overlap_chars >= max_chars:
        raise ValueError("overlap_chars must be smaller than max_chars")
    if not text:
        return []

    total_length = len(text)
    start = 0
    chunk_index = 0
    chunks: List[dict[str, object]] = []
    lookback_window = max(100, min(max_chars // 2, 600))

    while start < total_length:
        hard_stop = min(start + max_chars, total_length)
        stop = hard_stop
        if hard_stop < total_length:
            boundary_stop = _find_preferred_stop(text, start, hard_stop, lookback_window)
            if boundary_stop > start:
                stop = boundary_stop

        chunk_text_value = text[start:stop]
        chunk_record: dict[str, object] = {
            "chunk_index": chunk_index,
            "text": chunk_text_value,
            "char_start": start,
            "char_end": stop,
        }
        chunks.append(chunk_record)

        if stop >= total_length:
            break

        next_start = stop - overlap_chars
        if next_start <= start:
            next_start = stop
        start = next_start
        chunk_index += 1

    return chunks


__all__ = ["chunk_text"]
