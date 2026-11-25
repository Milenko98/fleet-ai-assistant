# Fleet AI Assistant

An AI agent for fleet management. Users ask questions in natural language, and the agent **decides on its own** which tools to call: queries over trips, fuel consumption and GPS alarms, or a search through internal company policies (RAG).

> The UI, sample data and policy documents are in **Croatian**, because the app targets a Croatian fleet operator. The code and this README are in English.

**Example questions** (translated):
- *Which vehicles drove more than 200 km yesterday?*
- *Did the driver of truck ZG-5521-KM drive longer than allowed without a break in the last 14 days?* (combines a policy document with trip data)
- *Compare the fuel consumption of ST-3307-DE over the last 2 weeks with the month before. Is the deviation within company rules?*

**Stack:** Laravel 13 · PHP 8.4 · Vue 3 · Tailwind CSS 4 · PostgreSQL 17 + pgvector · Anthropic Claude API · Voyage AI embeddings · Docker

## What this project demonstrates

| Concept | Where |
|---|---|
| LLM API integration (Claude) | `app/Ai/FleetAgent.php` |
| Tool / function calling | `app/Ai/FleetTools.php` (tool definitions + execution) |
| Agent loop, hand-written (multi-step) | `FleetAgent::run()` |
| RAG: chunking, embeddings, vector search | `app/Ai/DocumentIndexer.php`, `DocumentSearch.php`, `Embeddings.php` |
| Full-text vs. semantic retrieval | `DocumentSearch` (both modes, comparable in the UI) |
| Evaluation | `php artisan fleet:eval`: test set with ground truth computed from the database |
| Guardrails | step limit, validation of every tool input, the model never writes SQL, similarity threshold |
| Agent transparency | the UI shows every step: tool, model-generated input, output, duration |

## How the agent works

```
 User             Laravel (FleetAgent)               Claude API            Tools / DB
   │── question ────►│                                  │                       │
   │                 │── system + tools + conversation ►│                       │
   │                 │◄── stop_reason: tool_use ────────│  model picks tools    │
   │                 │── execute tools ─────────────────────────────────────────►│
   │                 │◄── results ──────────────────────────────────────────────│
   │                 │── conversation + tool_result ───►│                       │
   │                 │◄── stop_reason: end_turn ────────│  model writes answer  │
   │◄── answer ──────│                                  │                       │
```

1. Laravel sends the system prompt, the **tool definitions** (`name`, `description`, `input_schema`) and the full conversation.
2. The model doesn't run any code. It returns a `tool_use` block: *"call `pretrazi_voznje` with `{datum_od: ..., registracija: ...}`"*.
3. `FleetTools::execute()` validates the input and runs a predefined query.
4. The result goes back to the model as a `tool_result` (linked by `id`), and the loop repeats.
5. When the model has enough data, it returns a text answer (`end_turn`).

The model chooses tools purely from their **descriptions**, so the descriptions in `FleetTools::definitions()` are effectively part of the prompt. The API is stateless, so the whole conversation is sent on every step.

## RAG: full-text vs. semantic search

Documents (`database/docs/*.md` plus anything added through the UI) are split into chunks. Each chunk stores:

- `tsv`: a PostgreSQL full-text index (matches **words**)
- `embedding`: a 1024-dimensional Voyage AI vector (matches **meaning**; pgvector + HNSW index)

| Query (Croatian → English) | Full-text | Semantic |
|---|---|---|
| "stanka odmor vozača" (*break, rest, driver*) | ✅ found | ✅ found |
| "kad moram stati s vožnjom" (*when do I have to stop driving*) | ❌ no shared words | ✅ finds the break rule |
| "break rules for truck drivers" (English query) | ❌ | usually found (embeddings work across languages) |

Without `VOYAGE_API_KEY` the app falls back to full-text search. The **Dokumenti (RAG) → Testiraj pretragu** tab lets you compare retrieval before and after adding the key.

## Evaluation

```bash
docker compose exec app php artisan fleet:eval              # all cases
docker compose exec app php artisan fleet:eval --list       # show cases and expected answers
docker compose exec app php artisan fleet:eval --category=rag --repeat=3
```

10 test questions in 4 categories:

| Category | What it checks |
|---|---|
| `podaci` (data) | correct numbers and vehicles from tool calls |
| `rag` | answers grounded in policy documents |
| `kombinirano` (combined) | multi-tool reasoning: policy + data |
| `guardrail` | the agent admits a vehicle doesn't exist or the documents don't cover a topic, instead of guessing |

Grading is **deterministic** (no extra LLM call). A case passes only if:
1. the agent called every expected tool, and
2. the answer contains the expected facts (vehicle plates, numbers in any common format, policy values).

Expected answers for data questions are **computed from the database at run time**, so the eval stays correct after re-seeding. Each run prints accuracy per category, tool accuracy, average latency and token usage, and saves a full JSON report (including answers) to `storage/app/private/eval/`. Agents are non-deterministic, so use `--repeat` to measure stability.

## Getting started

Only Docker is required.

```bash
cp .env.example .env
# Add your keys to .env:
#   ANTHROPIC_API_KEY=sk-ant-...   required — https://console.anthropic.com
#   VOYAGE_API_KEY=pa-...          optional — https://www.voyageai.com (semantic search)

docker compose run --rm app composer install
docker compose run --rm app php artisan key:generate
docker compose up -d
docker compose exec app php artisan migrate:fresh --seed
```

App: **http://localhost:8090** (Vite dev server with hot reload runs on port 5174).

If you add `VOYAGE_API_KEY` later, re-index the documents so they get embeddings:

```bash
docker compose exec app php artisan docs:index database/docs --fresh
```

## Useful commands

```bash
# Ask the agent from the terminal (prints every step)
docker compose exec app php artisan fleet:ask "Koje vozilo ima najviše alarma ovaj mjesec?"

# Index your own documents (a .md/.txt file or a directory)
docker compose exec app php artisan docs:index storage/app/my-docs

# Production frontend build (instead of the Vite dev server)
docker compose run --rm vite npm run build
```

## API

| Method | Route | Description |
|---|---|---|
| POST | `/api/chat` | `{messages: [{role, content}]}` → `{answer, steps, usage}` |
| GET | `/api/fleet/summary` | stats, vehicles, AI service status |
| GET | `/api/documents` | indexed documents |
| POST | `/api/documents` | `{naslov, sadrzaj}` → index a new document |
| GET | `/api/documents/search?q=` | retrieval step only, without the agent |

## Project structure

```
app/Ai/
  FleetAgent.php        agent loop (Claude API)
  FleetTools.php        tools: definitions + execution + input validation
  DocumentIndexer.php   chunking + embeddings + storage
  DocumentSearch.php    semantic and full-text retrieval
  Embeddings.php        Voyage AI client
  Eval/                 test cases + runner for fleet:eval
app/Http/Controllers/Api/   REST API for the UI
app/Console/Commands/       fleet:ask, fleet:eval, docs:index
database/docs/              sample internal policies (Croatian)
database/seeders/           fictional fleet (60 days of trips and alarms)
resources/js/               Vue 3 UI
```

## Sample data

`FleetSeeder` generates 8 vehicles and 60 days of trips, with built-in scenarios for testing the agent:

- **ZG-5521-KM** (truck): sometimes drives longer than 4 h 30 min without a break
- **ST-3307-DE**: fuel consumption ~20 % above normal in the last 2 weeks
- **RI-8840-TT**: many speeding alarms
- **ZG-1290-AC**: overdue for service

The policy documents in `database/docs` are fictional examples. The driving-time policy is based on EU Regulation (EC) No 561/2006.

## Roadmap

- [ ] Streaming responses (show text while the model generates it)
- [ ] Prompt caching for the system prompt and tool definitions (lower cost and latency)
- [ ] PHPUnit tests for tools and the agent loop with a fake API client
- [ ] LLM-as-judge grading for open-ended answers in `fleet:eval`
- [ ] GitHub Actions (lint + tests)
