import json

import intent_planner


def _register_workspace_search_spec(monkeypatch):
    monkeypatch.setitem(
        intent_planner._FUNCTION_SPEC_MAP,
        "workspace.search_rfqs",
        {
            "name": "workspace.search_rfqs",
            "parameters": {"type": "object", "properties": {}, "required": []},
        },
    )


def test_plan_action_handles_ambiguous_prompt(monkeypatch):
    captured = {}

    def fake_call(messages):
        captured["messages"] = messages
        return {
            "choices": [
                {
                    "message": {
                        "content": "",
                        "function_call": {
                            "name": "build_rfq_draft",
                            "arguments": json.dumps({"rfq_title": "Tomorrow RFQ"}),
                        },
                    }
                }
            ]
        }

    monkeypatch.setattr(intent_planner, "_call_openai", fake_call)

    result = intent_planner.plan_action_from_prompt("Draft an RFQ tomorrow", [])

    assert result["tool"] == "build_rfq_draft"
    assert result["args"]["rfq_title"] == "Tomorrow RFQ"
    assert captured["messages"][0]["role"] == "system"
    assert captured["messages"][0]["content"] == intent_planner.SYSTEM_PROMPT
    assert captured["messages"][-1]["content"] == "Draft an RFQ tomorrow"


def test_plan_action_blocks_unknown_tools(monkeypatch):
    def fake_call(messages):
        return {
            "choices": [
                {
                    "message": {
                        "content": "I would try something else.",
                        "function_call": {"name": "invented_tool", "arguments": "{}"},
                    }
                }
            ]
        }

    monkeypatch.setattr(intent_planner, "_call_openai", fake_call)

    result = intent_planner.plan_action_from_prompt("Draft an RFQ tomorrow", [])

    assert result["tool"] is None
    assert "try something else" in result["message"].lower()


def test_draft_guard_redirects_search_tool(monkeypatch):
    _register_workspace_search_spec(monkeypatch)

    def fake_call(messages):
        return {
            "choices": [
                {
                    "message": {
                        "content": "",
                        "function_call": {"name": "workspace.search_rfqs", "arguments": json.dumps({})},
                    }
                }
            ]
        }

    monkeypatch.setattr(intent_planner, "_call_openai", fake_call)

    result = intent_planner.plan_action_from_prompt("Draft an RFQ for fasteners", [])

    assert result["tool"] == "clarification"
    assert result["target_tool"] == "build_rfq_draft"
    assert "rfq_title" in result.get("missing_args", [])


def test_guard_allows_show_queries(monkeypatch):
    _register_workspace_search_spec(monkeypatch)

    expected_args = {"statuses": ["draft"], "limit": 5}

    def fake_call(messages):
        return {
            "choices": [
                {
                    "message": {
                        "content": "",
                        "function_call": {
                            "name": "workspace.search_rfqs",
                            "arguments": json.dumps(expected_args),
                        },
                    }
                }
            ]
        }

    monkeypatch.setattr(intent_planner, "_call_openai", fake_call)

    result = intent_planner.plan_action_from_prompt("Show my draft RFQs", [])

    assert result["tool"] == "workspace.search_rfqs"
    assert result["args"] == expected_args


def test_guard_allows_find_queries(monkeypatch):
    _register_workspace_search_spec(monkeypatch)

    expected_args = {"query": "RFQ Rotar Blades"}

    def fake_call(messages):
        return {
            "choices": [
                {
                    "message": {
                        "content": "",
                        "function_call": {
                            "name": "workspace.search_rfqs",
                            "arguments": json.dumps(expected_args),
                        },
                    }
                }
            ]
        }

    monkeypatch.setattr(intent_planner, "_call_openai", fake_call)

    result = intent_planner.plan_action_from_prompt("Find RFQ Rotar Blades", [])

    assert result["tool"] == "workspace.search_rfqs"
    assert result["args"] == expected_args


def test_plan_action_allows_invoice_mismatch_resolution(monkeypatch):
    def fake_call(messages):
        return {
            "choices": [
                {
                    "message": {
                        "content": "",
                        "function_call": {
                            "name": "resolve_invoice_mismatch",
                            "arguments": json.dumps({"invoice_id": "INV-55"}),
                        },
                    }
                }
            ]
        }

    monkeypatch.setattr(intent_planner, "_call_openai", fake_call)

    result = intent_planner.plan_action_from_prompt("Resolve mismatches on INV-55", [])

    assert result["tool"] == "resolve_invoice_mismatch"
    assert result["args"]["invoice_id"] == "INV-55"


def test_plan_action_handles_multi_step_plan(monkeypatch):
    def fake_call(messages):
        return {
            "choices": [
                {
                    "message": {
                        "content": "I can handle both tasks.",
                        "function_call": {
                            "name": intent_planner.PLAN_FUNCTION_NAME,
                            "arguments": json.dumps(
                                {
                                    "steps": [
                                        {
                                            "tool": "build_rfq_draft",
                                            "args": {"rfq_title": "Rotor Blades"},
                                        },
                                        {
                                            "tool": "workspace.search_suppliers",
                                            "args": {"limit": 3},
                                        },
                                    ]
                                }
                            ),
                        },
                    }
                }
            ]
        }

    monkeypatch.setattr(intent_planner, "_call_openai", fake_call)

    result = intent_planner.plan_action_from_prompt("Draft an RFQ and add suppliers", [])

    assert result["tool"] == "plan"
    assert len(result["steps"]) == 2
    assert result["steps"][0]["tool"] == "build_rfq_draft"
    assert result["steps"][1]["tool"] == "workspace.search_suppliers"
