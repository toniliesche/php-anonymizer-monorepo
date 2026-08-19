# Graphify + Codex installation playbook

This playbook installs the Graphify CLI once per machine and configures its
Codex integration per repository. It is written for the `new-anonymizer`
workspace, but the repository steps can be repeated for another checkout.

Graphify's default Codex integration uses an `../_AGENTS.md` section and a local
Codex skill. It does not require an MCP server. The graph itself is generated
per repository.

## 1. Install the CLI once per machine

On Linux or macOS, install `uv` if it is not already available:

```sh
curl -LsSf https://astral.sh/uv/install.sh | sh
exec "$SHELL" -l
```

Install Graphify as an isolated user-level tool:

```sh
uv tool install graphifyy
graphify --version
```

If `graphify` is not found, refresh the `uv` tool path and start a new shell:

```sh
uv tool update-shell
exec "$SHELL" -l
graphify --version
```

## 2. Configure a repository

Run this from the root of the repository. For this workspace:

```sh
cd /path/to/toniliesche_de/new-anonymizer
graphify codex install
```

The installer adds or updates only its own integration section and creates the
Codex skill and hook files. Review the result before staging anything:

```sh
git status --short
git diff -- _AGENTS.md .codex
```

Keep the generated graph local by adding this entry to `.gitignore` once:

```sh
rg -qxF 'graphify-out/' .gitignore || printf '\n# Local Graphify knowledge graph.\ngraphify-out/\n' >> .gitignore
```

For a shared team setup, commit the Graphify section in `../_AGENTS.md`, the
`.codex/` integration files, and the `.gitignore` entry. Do not stage unrelated
working-tree changes.

## 3. Build and use the graph

Start a new Codex session with the repository as its working directory. In the
Codex conversation (not in the shell), run:

```text
$graphify .
```

If no separate LLM API key is configured, use the code-only variant instead:

```text
$graphify . --code-only
```

Code-only extraction builds the local AST/code graph without sending
documentation to a model. It is the recommended first run for this project.
If the Codex skill does not pass the flag through, the equivalent terminal
command is:

```sh
graphify extract . --code-only
```

This creates the local `graphify-out/` directory containing:

- `graph.json` — the machine-readable graph;
- `GRAPH_REPORT.md` — an architecture summary;
- `graph.html` — an interactive visualization.

From the shell, verify that the graph can answer a structural question:

```sh
graphify query "what connects AnonymizerFactory to traversal?"
graphify path "AnonymizerFactory" "ArrayTraversal"
graphify explain "AnonymizerFactory"
```

After substantial code changes, update only changed portions from a new Codex
session:

```text
$graphify . --update
```

Without a semantic-extraction key, use:

```text
$graphify . --update --code-only
```

## 3a. Optional semantic extraction

The full command also indexes documentation, but those files require a
separately configured Graphify backend/API key. A Codex or ChatGPT login does
not automatically provide one; a local Codex session is not an API endpoint
that Graphify can use as its extraction backend. Configure a supported backend
according to Graphify's current documentation, then rerun `$graphify .` if
document-level concepts are needed. Do not put the key in the repository or in
`../_AGENTS.md`.

ChatGPT Plus does not include API usage. ChatGPT subscriptions and API usage
are billed separately, so Plus cannot be converted into the
`OPENAI_API_KEY` required by Graphify's OpenAI backend.

For a local model backend, Graphify supports Ollama. With Ollama running on its
default local endpoint, use:

```sh
graphify extract . --backend ollama --model <installed-ollama-model>
```

On a Linux machine, the complete setup is:

```sh
curl -fsSL https://ollama.com/install.sh | sh
```

Start Ollama in one terminal and verify it from another:

```sh
ollama serve
curl http://127.0.0.1:11434/api/tags
```

Download a local model, for example:

```sh
ollama pull qwen3:8b
```

Then run semantic extraction with one concurrent request at a time:

```sh
cd /path/to/toniliesche_de/new-anonymizer
graphify extract . --backend ollama --model qwen3:8b --max-concurrency 1
```

If the machine has limited memory, use a smaller Ollama model such as
`qwen3:4b` instead.

For a 32 GB Linux notebook with an Intel Core Ultra 7 165U, `qwen3:8b` is the
recommended quality/speed starting point. `qwen3:14b` may improve semantic
quality but will be noticeably slower on a low-power U-series CPU. Avoid the
30B+ variants for this machine.

If you use an OpenAI-compatible local server (for example LM Studio or
llama.cpp), configure `OPENAI_BASE_URL` and `OPENAI_MODEL`, then use
`--backend openai`. These are separate model-server setups; neither uses the
Codex ChatGPT login.

## 4. Optional extraction speed-up

Graphify can use Codex parallel extraction when the feature is enabled. Merge
the following into the user-level `~/.codex/config.toml`; do not overwrite an
existing `[features]` table:

```toml
[features]
multi_agent = true
```

This is a user-level Codex setting, not a repository requirement.

## 5. Make targets

The project Makefile provides repeatable wrappers for the common operations:

```sh
make graphify-check
make graphify-code-only
make graphify-extract
make graphify-update
make graphify-query QUESTION="what connects AnonymizerFactory to traversal?"
make graphify-path FROM="AnonymizerFactory" TO="ArrayTraversal"
make graphify-explain NODE="AnonymizerFactory"
```

`graphify-extract` defaults to Ollama with `qwen3:8b` and one concurrent
request. Override the model or backend when needed:

```sh
make graphify-extract GRAPHIFY_MODEL=qwen3:14b
make graphify-extract GRAPHIFY_BACKEND=ollama GRAPHIFY_MODEL=qwen3:8b
```

The Ollama backend requires Graphify's optional client dependency. Install it
once per machine with:

```sh
make graphify-install-ollama
```

This runs `uv tool install "graphifyy[ollama]" --force`. The local Ollama
server does not require a real API key; the `OLLAMA_API_KEY` warning can be
ignored when the endpoint is `http://localhost:11434`.

## 6. Repeat on another machine or repository

Install `uv` and Graphify once on the new machine, then repeat only the
repository steps from section 2 for each repository. Each repository needs its
own integration files and its own locally generated `graphify-out/` graph.

Serena and Graphify MCP are intentionally not part of this first setup. Add
them only after evaluating whether the Graphify skill and CLI are sufficient.

References:

- [Graphify + Codex integration](https://graphify.com/integrations/codex)
- [OpenAI Docs: Codex MCP](https://developers.openai.com/codex/mcp)
