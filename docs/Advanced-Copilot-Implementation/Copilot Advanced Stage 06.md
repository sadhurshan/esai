1. [x] Add an Intent Planner Using OpenAI Function‑Calling

Prompt:

"Create a new Python module ai_microservice/intent_planner.py.
It should define a plan_action_from_prompt(prompt: str, context: List[dict]) -> dict function that calls OpenAI’s ChatCompletion API with the current user message and a list of JSON function specs representing our available tools (e.g., build_rfq_draft, build_supplier_message, build_award_quote, etc.).
Use OpenAI’s function‑calling to let the model decide when to return a tool_name and its arguments. If no tool fits, return {'tool': None, 'message': assistant_reply}.
Store the OpenAI API key in an environment variable (e.g. OPENAI_API_KEY) and handle errors gracefully (returning a fallback message if the API fails)."

2. [x] Wire the Planner Into the Chat Service

Prompt:

"Modify app/Services/Ai/ChatService.php::sendMessage. After a user message is saved but before resolving workspace tools, call the microservice’s /v1/ai/intent-plan endpoint (to be created) with the user’s prompt and chat context.
If it returns a tool_name, call the corresponding plan action with the extracted arguments. If no tool is returned, continue with the existing reply flow.
Create the intent-plan endpoint in AiController that invokes plan_action_from_prompt and returns JSON with tool, args, and optionally a reply field."

3. [x] Extend build_rfq_draft to Accept More Title Fields

Prompt:

"Update build_rfq_draft in ai_microservice/tools_contract.py so that it checks inputs for keys rfq_title, rfq_name, name, or title, in that order, when deriving rfq_title.
Keep the existing fallback using today’s date if none of these are present.
Write a unit test to assert that passing {'name': 'Rotar Blades'} results in 'rfq_title': 'Rotar Blades' in the returned payload."

4. [x] Define Function Specs for Each Action

Prompt:

"In intent_planner.py, create a FUNCTION_SPECS list with one spec per tool. Each spec should include name, description, and parameters following OpenAI function‑calling schema.
For example, build_rfq_draft should have parameters like { 'type': 'object', 'properties': { 'rfq_title': { 'type': 'string', 'description': 'Title of the RFQ' }, 'scope_summary': { ... } }, 'required': ['rfq_title'] }.
Do this for at least the core actions: build_rfq_draft, compare_quotes, build_award_quote, draft_purchase_order, build_invoice_draft, and get_help.
These specs will help the LLM know how to format arguments."

5. [x] Add Clarifying Questions When Inputs Are Missing

Prompt:

"Enhance plan_action_from_prompt so that if the model chooses a function but leaves required arguments empty, it returns a response with { 'tool': 'clarification', 'missing_args': ['rfq_title'], 'question': 'What should be the title of the RFQ?' }.
Modify ChatService.php to detect this clarification type and send the assistant’s question back to the user via chat. When the user answers, merge the answer into the original call and retry the planned action.
Write a feature test to simulate sending ‘Draft an RFQ’ and assert that the assistant asks for a title."

6. [x] Update UI to Display LLM Replies and Clarification Prompts

Prompt:

"In resources/js/components/ai/CopilotChatPanel.tsx, add rendering logic for a new assistant response type clarification.
Use a simple form input inside the chat bubble allowing the user to answer the follow‑up question. On submit, pass the answer back through useAiChatSend with a flag linking it to the pending action.
Also ensure that plain LLM replies (when no tool is triggered) stream like other assistant messages."

7. [x] Write End‑to‑End Tests for RFQ Drafting via NLU

Prompt:

"Create a Pest test tests/Feature/Ai/NluDraftRfqTest.php.
Send the message ‘Draft an RFQ named Rotar Blades for sourcing custom blades.’ via the chat API and assert that the resulting assistant message includes a draft action with rfq_title = ‘Rotar Blades’.
Send another message ‘Draft an RFQ’ and assert that a clarification question is returned asking for the RFQ title.
After answering ‘Call it Test RFQ’, assert that a draft action is produced with rfq_title = ‘Test RFQ’."

8. [x] Fine‑Tune the System Prompt for Better Language Understanding

Prompt:

"In intent_planner.py, add a SYSTEM_PROMPT string describing the role of the LLM:
‘You are a procurement assistant. Your job is to understand the user’s intent and choose the best tool to execute it. Always produce JSON tool calls when possible. Ask clarifying questions when required information is missing. Avoid hallucination; only call tools defined in FUNCTION_SPECS.’
Prepend this system prompt to all conversations sent to OpenAI.
Test that ambiguous messages like ‘Draft an RFQ tomorrow’ still trigger a tool call and that the LLM doesn’t invent non‑existent actions."



