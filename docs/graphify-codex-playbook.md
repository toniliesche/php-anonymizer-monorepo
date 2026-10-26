# Graphify + Codex installation playbook

This playbook installs the Graphify CLI once per machine and configures its
Codex integration per repository. It is written for the `new-anonymizer`
workspace, but the repository steps can be repeated for another checkout.

Graphify's Codex integration uses an `AGENTS.md` section and a local Codex
skill. It does not require an MCP server. The graph itself is generated per
repository.

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

The installer adds or updates its Graphify section in `AGENTS.md` and writes
the Codex skill and hook files. Review the result before staging anything:

```sh
git status --short
git diff -- AGENTS.md .codex
```

Keep the generated graph local by adding this entry to `.gitignore` once:

```sh
rg -qxF 'graphify-out/' .gitignore || printf '\n# Local Graphify knowledge graph.\ngraphify-out/\n' >> .gitignore
```

For a shared team setup, commit the Graphify section in `AGENTS.md`, the
`.codex/` integration files, and the `.gitignore` entry. Do not stage unrelated
working-tree changes.

## 3. Build and use the graph

Start a new Codex session with the repository as its working directory. The
`$graphify` skill builds the graph using the active Codex session; source code
is parsed locally, while documentation can be analyzed by the model in that
session. In the Codex conversation (not in the shell), run:

```text
$graphify .
```

Use code-only extraction when you want to omit documentation and other
non-code files from the graph:

```text
$graphify . --code-only
```

Code-only extraction builds the local AST/code graph without analyzing
documentation. If the skill does not pass the flag through, the equivalent
terminal command is:

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

To update only the code graph and skip semantic analysis of documentation, use:

```text
$graphify . --update --code-only
```

## 3a. Headless semantic extraction

The shell command `graphify extract .` runs Graphify's headless extraction
pipeline. Unlike the `$graphify .` skill, semantic extraction through this
command uses a separately configured backend and its credentials. Use
`--code-only` to skip semantic extraction and its backend requirement. Configure
the backend using the [Graphify CLI reference](https://docs.graphify.com/reference/overview);
do not put backend credentials in the repository or `AGENTS.md`.

For the OpenAI backend, use an API key from the OpenAI API platform. ChatGPT
subscription billing is separate from API billing; a ChatGPT subscription
does not provide an `OPENAI_API_KEY` for the headless command. See [OpenAI's
billing guide](https://help.openai.com/en/articles/9039756-managing-billing-for-chatgpt-and-the-api-platform).

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

Choose an installed model that fits the machine's available memory. The Make
target defaults to `qwen3:8b`; override `GRAPHIFY_MODEL` for a different local
model.

For other OpenAI-compatible local servers, check the installed Graphify CLI
reference for the supported backend variables and options. These are separate
model-server setups; they do not use the Codex ChatGPT session.

## 4. Optional parallel extraction

Graphify's Codex integration recommends enabling parallel extraction. The
current Codex configuration reference lists `features.multi_agent` as stable
and on by default; an existing user or managed setting can still disable it.
If you need to turn it on explicitly, merge this into the user-level
`~/.codex/config.toml`; do not overwrite an existing `[features]` table:

```toml
[features]
multi_agent = true
```

This is a user-level Codex setting, not a repository requirement. See the
[Codex configuration reference](https://developers.openai.com/codex/config-reference/)
and [Graphify's Codex integration guide](https://graphify.com/integrations/codex).

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

This runs `uv tool install "graphifyy[ollama]" --force`. Confirm available
commands and options with `graphify --help`; Graphify flags can differ by
installed version.

## 6. Repeat on another machine or repository

Install `uv` and Graphify once on the new machine, then repeat only the
repository steps from section 2 for each repository. Each repository needs its
own integration files and its own locally generated `graphify-out/` graph.

Serena and Graphify MCP are intentionally not part of this first setup. Add
them only after evaluating whether the Graphify skill and CLI are sufficient.

References:

- [Graphify + Codex integration](https://graphify.com/integrations/codex)
- [OpenAI Docs: Codex MCP](https://developers.openai.com/codex/mcp)
