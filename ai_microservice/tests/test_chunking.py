"""Unit tests for the chunking utility."""
from __future__ import annotations

import math

import pytest

from ai_microservice.chunking import chunk_text


def test_chunk_text_returns_metadata_and_respects_max_chars() -> None:
    paragraph = "Paragraph one with enough content to force chunking. " * 5
    text = (paragraph + "\n\n" + paragraph) * 3

    chunks = chunk_text(text, max_chars=300, overlap_chars=50)

    assert chunks, "Expected at least one chunk to be generated"
    assert chunks[0]["chunk_index"] == 0
    assert chunks[0]["char_start"] == 0
    assert chunks[-1]["char_end"] == len(text)
    for idx, chunk in enumerate(chunks):
        assert len(chunk["text"]) <= 300
        assert chunk["char_end"] - chunk["char_start"] == len(chunk["text"])
        assert chunk["chunk_index"] == idx


def test_chunk_text_maintains_requested_overlap_when_no_boundaries() -> None:
    text = "x" * 700
    max_chars = 200
    overlap = 40

    chunks = chunk_text(text, max_chars=max_chars, overlap_chars=overlap)

    assert len(chunks) == math.ceil((700 - max_chars) / (max_chars - overlap)) + 1
    for idx in range(1, len(chunks)):
        prev = chunks[idx - 1]
        current = chunks[idx]
        assert current["char_start"] == prev["char_end"] - overlap
    assert chunks[-1]["char_end"] == len(text)


def test_chunk_text_validates_arguments() -> None:
    with pytest.raises(ValueError):
        chunk_text("hello", max_chars=0)
    with pytest.raises(ValueError):
        chunk_text("hello", max_chars=10, overlap_chars=-1)
    with pytest.raises(ValueError):
        chunk_text("hello", max_chars=10, overlap_chars=10)


def test_chunk_text_handles_empty_input() -> None:
    assert chunk_text("") == []
