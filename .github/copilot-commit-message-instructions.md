# Copilot Commit Message Instructions — Elements Supply AI

Goal: generate concise, conventional commits that describe *what* changed and *why*.

Style:
- Use **Conventional Commits** format: <type>(scope): <description>
- Types: feat, fix, refactor, chore, docs, test, perf, build, ci.
- Keep subject ≤ 72 characters.
- First line: imperative mood (e.g., "add", "update", "fix").
- Optional body: short reasoning or impact.

Examples:
feat(rfq): add RFQ publishing endpoint with validation
fix(supplier): correct rating filter bug in Supplier directory
refactor(api): apply ApiResponse envelope to all controllers
