"""Prompt templates for the AI microservice."""
from __future__ import annotations

from typing import Any, Dict, List, Sequence

Message = Dict[str, str]

SYSTEM_INSTRUCTION = (
    "You are Elements Supply's procurement copilot. Use ONLY the provided sources. "
    "If the sources are insufficient, state that plainly. Cite doc_id and chunk_id in every fact. "
    "Never invent data, numbers, or documents."
)
DEVELOPER_INSTRUCTION = (
    "Output must be VALID JSON that exactly matches the provided schema. "
    "Do not add Markdown fences, explanations, or commentary outside the JSON body."
)


def build_answer_messages(query: str, hits: Sequence[Dict[str, Any]]) -> List[Message]:
    """Return the chat messages for grounded answer generation."""

    source_lines: List[str] = []
    for hit in hits:
        line = (
            f"- title={hit.get('title') or 'Untitled'} | doc_id={hit.get('doc_id')} | "
            f"doc_version={hit.get('doc_version')} | chunk_id={hit.get('chunk_id')} | "
            f"snippet={str(hit.get('snippet') or '').strip()}"
        )
        source_lines.append(line[:600])
    if not source_lines:
        source_lines.append("(no sources available)")

    user_message = (
        f"Question: {query}\n\n"
        "Sources:\n"
        + "\n".join(source_lines)
    )

    return [
        {"role": "system", "content": SYSTEM_INSTRUCTION},
        {"role": "developer", "content": DEVELOPER_INSTRUCTION},
        {"role": "user", "content": user_message},
    ]


__all__ = ["build_answer_messages", "SYSTEM_INSTRUCTION", "DEVELOPER_INSTRUCTION"]
