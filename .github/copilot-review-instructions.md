# Copilot Review Selection Instructions — Elements Supply AI

When asked to review code or PRs:
- Focus on correctness, security, and tenant isolation.
- Check for:
  - company_id scoping on all queries
  - API envelope structure
  - Pagination & validation rules
  - RBAC (policy & middleware)
  - Queue usage where expected
- Style: Tailwind v4 + shadcn/ui; no inline CSS.
- Flag missing audit logs or tests.
- Be polite, concise, and constructive:
  - ✅ for correct patterns
  - ⚠️ for minor improvements
  - ❌ for critical issues
