# Decision log

The canonical record of **why** this repository is the way it is: the reasoning and the **rejected alternatives** behind decisions that have closed. It is the guard against silently relitigating a settled decision.

**Consult it before acting.** Before proposing a change, reopening a question, or picking an approach, check here first: the path under consideration may already be a decided-and-recorded one, or an explicitly rejected one. If it is, follow the record (or make the case to revisit it); do not quietly re-decide.

**When a seam closes, record the verdict in both places.** Capture the *what*, *why* and rejected alternatives **here** (the cross-ticket index) **and** in the GitHub issue's `## Decision` section (the per-ticket record), keeping the two in sync. This file does not replace the issue tracker, which stays the system of record for a single ticket's status.

See the [`design-decision-forks`](../.claude/rules/design-decision-forks.md) rule for how a fork is surfaced and driven to resolution before its verdict lands here.

## How to add an entry

Oldest first, one entry per closed decision, so **a new entry is appended to the end of the file**. Keep it terse; the issue carries the depth. Each entry is preceded by exactly one blank line, which `scripts/check-decision-log-order.mjs` enforces along with the date order. The file is marked `merge=union` in `.gitattributes`, so two branches that each append an entry merge without a conflict.

```markdown
## YYYY-MM-DD: <short decision title> (<#issue or area>)

**Decision.** <what was decided, in a sentence or two>

**Why.** <the reasoning that settled it>

**Rejected.** <the alternatives considered, and why each lost>

**Companion.** <link to the GitHub issue holding the full reasoning>
```

---

## 2026-10-14: Started from the add-on template (repository setup)

**Decision.** This repository started from `UAMS-Web/statamic-addon-template`. It carries no synced files (`.agents/`, `.claude/`, `.cursor/`) and no `LICENSE`, and `composer.json` has no `license` field.

**Why.** The shared rules and skills reach a repository by onboarding it to the sync in `UAMS-Web/uams-claude-skills`, not by copying a snapshot that goes stale. The license waits on the general decision from UAMS legal in `UAMS-Web/uams-statamic#261`.

**Rejected.** Snapshotting the synced files into the template: every new add-on would begin with a stale copy that conflicts with its first sync pull request. Copying a license from an existing add-on: legal has not decided which license applies.

**Companion.** `UAMS-Web/uams-statamic#261`.

---

## 2026-11-01: Keep the config file as `config/aether-search.php`

**Decision.** The add-on keeps the template's placeholder config file, renamed `config/aether-search.php`, with the publish tag `aether-search-config`. It holds no settings yet.

**Why.** A search add-on will have settings (indexes, result limits and the like), and keeping the file keeps the handle, the publish tag and `tests/Integration/AddonServiceProviderTest.php` in step from the first release. Removing it now would mean adding it back when the search code moves in.

**Rejected.** Deleting the file and its test until a setting exists: it churns the handle and tag twice for no gain.

**Companion.** `UAMS-Web/aether-search#2`.

---

## 2026-11-01: The search move lands as views, tag and controller in this package, with settings for what the host decides

**Decision.** The shell, listing, result row and generic cards ship in this package under the `aether-search` view namespace. The tag is `aether_search`. The suggestions controller moves with two seams (`EntryVisibility`, `SuggestionPresenter`). The type-ahead script has one home, this package, and the host imports it. The host's frames, tabs, labels, scoped mode and always-on scope are settings in `config/aether-search.php`. The host overrides only type cards, through `resources/views/vendor/aether-search/cards/`.

**Why.** The decision is S1 (V4 with a generic scoped mode) in `UAMS-Web/uams-statamic`. Three mechanics came out of building it. Antlers pairs a closing `partial` tag with the nearest `partial`, so two dynamic `partial` pairs cannot nest in one document: each frame is a view of its own with a static name and one dynamic pair inside. A namespaced partial needs a subdirectory (`listing/results`), because Statamic derives the underscore-prefixed file name from the last dot-separated segment. The tag reads the query, index and scope from the live request, not the view cascade's `get`, which a long-lived process or test host holds stale.

**Rejected.** Frames as a list setting nested in the shell: it needs nested same-name pairs. Reading `get:` in the view: it fails in a host that reuses one cascade across requests. A type-ahead script in each of the application and the package: two copies drift.

**Companion.** `UAMS-Web/aether-search#3`.

---

## 2026-11-01: External results are a provider contract with a response-allowance hook

**Decision.** The external results scope (Google Programmable Search first) is behind `ExternalResultsProvider`: a provider says whether it is configured, names the view that renders its results, and declares what it needs. A registry (`ExternalResultsProviders`) holds the providers by name, `google` built in; a host adds one from a provider's `boot()`. The scope is off by default, and is offered only when it is enabled and the provider is configured. An unknown provider name fails when the application boots. A `ResponseAllowance` contract, a no-op by default, is the hook an application implements to raise its content security policy; the package calls it only when it renders a provider's results.

**Why.** The application's policy lives in the application, and the package must not name its middleware. A contract with a declared list keeps the package ignorant of the policy and the application ignorant of the provider. Failing at boot on an unknown name turns a typo into an error with the valid names, where a silent fallback would hide it. The registry is a singleton bound in `register()` so a host's `boot()` reaches the same instance the check later reads.

**Rejected.** A config callback for the hook: a closure in config breaks `config:cache`. Naming the application's middleware in the package. A Google-only scope: a second provider would have to rewrite the tag and the shell.

**Companion.** `UAMS-Web/aether-search#6`.

---

## 2026-11-02: The search scopes are one registry, and "This site" shows the tabs its site can fill

**Decision.** The type tabs, the `?index=` allow-list on the page and on the suggestions endpoint, and the result-type labels read from one class, `SearchScopes`, fed by `tabs`, `tab_collections` and `labels` in the config. Under "This site" a tab shows only when the site carries one of its collections; "All results", tabs with no `tab_collections` entry, and every tab under a wider scope always show. A scope link keeps the current tab only when the target scope shows it. A host test can assert `unconfiguredIndexes()` is empty.

**Why.** Six hand-written copies of one list meant a miss showed only at runtime, and tabs that cannot return a result were offered on sites that do not carry their collections. A site's collections come from Statamic's configuration, so the address a site answers on does not change the tabs. The rule asks only which sites the search covers, so it holds whether "This site" on a parent sub-site later covers its children or not; `SearchScopes::visibleTabs()` takes the site handle to change in that case.

**Rejected.** Control-panel-configurable scopes, decided against in `UAMS-Web/uams-statamic#162`. Deriving a tab's collections from the index configuration: `searchables` can be a wildcard or a custom searchable, so the map is stated.

**Companion.** `UAMS-Web/aether-search#12`, `UAMS-Web/uams-statamic#475`.
