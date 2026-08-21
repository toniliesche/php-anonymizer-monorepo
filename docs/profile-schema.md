# Profile schema

Profiles are immutable definitions compiled from PHP arrays. The same
vocabulary is reserved for the future Symfony YAML adapter.

## Governance metadata and reusable fragments

Profiles may include optional governance metadata. The map may contain any
subset of `schema_version`, `profile_version`, `owner`, `status`, and `purpose`;
unknown keys and empty values are rejected. `status` is `active` or
`deprecated`. Metadata is inspectable through the profile inspector and does
not affect anonymization or the behavior fingerprint. Do not put secrets in
metadata.

Reusable fragments are local to one profile and are declared as an ordered
list. This preserves deterministic expansion and duplicate-name diagnostics:

```php
'fragments' => [
    [
        'name' => 'contact',
        'rules' => [['field' => 'email'], ['field' => 'phone']],
    ],
],
'rules' => [
    ['use' => 'contact'],
],
```

`use` entries may appear in top-level rules, nested `children`, and list-form
`each` rules. Fragments may reference other fragments, but references cannot
cross profiles and `global_rules` cannot use fragments. Expansion occurs at
startup: nested references are expanded depth-first, the first fragment rule
wins a fragment-to-fragment field collision, and a direct profile rule replaces
a fragment rule selecting the same field at that level. Duplicate direct rules,
missing references, duplicate names, and cycles are invalid.

```php
$profile = ProfileDefinition::fromArray('customer', [
    'mode' => 'redact', // redact or preserve
    'rules' => [
        ['field' => 'email'],
        [
            'field' => 'address',
            'children' => [['field' => 'street']],
            'on_scalar' => 'preserve',
        ],
        ['field' => 'orders', 'each' => [['field' => 'reference']]],
        ['field' => 'tags', 'each' => true],
    ],
]);
```

Profiles target map documents by default. Set `root: list` explicitly for a
document whose root is a list of associative records; list roots are never
inferred from arbitrary arrays:

```php
$profile = ProfileDefinition::fromArray('records', [
    'mode' => 'redact',
    'root' => 'list',
    'rules' => [['field' => 'email']],
]);
```

### Key normalization and global rules

Set `key_normalization: true` to match equivalent ASCII identifier spellings
such as `email_address`, `email-address`, and `emailAddress`. Matching never
renames keys: the original spelling is retained in output and report paths.
Exact spellings win normalized collisions; otherwise rules use declaration
order.

`global_rules` applies scalar rules at every map depth, including maps inside
lists. Global rules are local to the profile and do not cross embedded payload
boundaries. A profile may contain only global rules, but must contain at least
one selector list overall:

```php
$profile = ProfileDefinition::fromArray('customer', [
    'mode' => 'redact',
    'key_normalization' => true,
    'global_rules' => [
        ['field' => 'email_address', 'strategy' => 'star_mask'],
        ['field' => 'tags', 'each' => true],
    ],
]);
```

Global rules are scalar leaves or `each: true` string-list selectors. They may
use conditions, strategies, transformations, and contextual key IDs, but may
not contain `children`, nested `each`, `embedded`, or `on_scalar`.

`redact` applies rules as a selection of string values. `preserve` applies
rules as an allow-list: selected strings stay unchanged and every other
string in the input tree becomes `[REDACTED]`. Numbers, booleans, nulls, and
unselected containers retain their values.

Rules may contain one traversal form, either `children` for an associative
map or `each` for a list. `each: true` selects every string in a list;
`each: [rules]` selects fields in every map element. A traversing rule can use
`on_scalar: redact` or `on_scalar: preserve` when that field is instead a
string. Without `on_scalar`, the shape mismatch is an error.

## Conditions

`when` is a deliberately small, data-only predicate. Its path is relative to
the containing map and uses slash-separated segments with RFC 6901 escaping:

```php
['field' => 'email', 'when' => ['path' => 'kind', 'equals' => 'person']]
['field' => 'email', 'when' => ['path' => 'metadata/type', 'in' => ['person', 'admin']]]
```

Only one of `equals` and `in` is allowed. Values are compared strictly.
Missing paths and non-map intermediate values do not match. Conditions inspect
the original sibling values before transformations at that level, so rule
order cannot change their result. A non-matching rule is skipped without a
report entry.

## Replacement strategies

A leaf may name a registered string replacement strategy. Redact-mode leaves
default to `redact`, which returns `[REDACTED]`. The core also provides
`star_mask`, which emits one `*` per UTF-8 character. Preserve-mode leaves stay
unchanged unless they explicitly name a strategy.

```php
['field' => 'email', 'strategy' => 'star_mask']
```

Contextual strategies may additionally require a non-secret key identifier:

```php
['field' => 'email', 'strategy' => 'pseudonymize', 'key_id' => 'customer-v1']
```

Typed leaf transformations use a named map and are mutually exclusive with
`strategy` and `embedded`:

```php
['field' => 'birthDate', 'transform' => [
    'type' => 'date_generalize',
    'options' => ['unit' => 'month'],
]]
```

Core transformations include `nullify`, `partial_mask`,
`date_generalize`, and `numeric_bucket`. The optional pseudonymization package
adds contextual `date_shift`, which requires `key_id`, `max_days`, and a
`scope_attribute`; call the contextual array or object API when using it.

Use `ContextualAnonymizerInterface` with an `AnonymizationContext` containing
canonical string attributes such as `tenant` and `scope`. Key material is
resolved by the application and never belongs in profiles or logs.

Strategies are leaf-only and cannot be combined with `children`, `each`, or
`embedded`.

## Embedded payloads

An embedded leaf decodes a string, applies another profile, and encodes the
result again. The named profile must exist and references may not form cycles.
The core JSON codec is registered as `json`.

```php
[
    'field' => 'metadata',
    'embedded' => ['codec' => 'json', 'profile' => 'metadata'],
]
```

Embedded report paths are prefixed by the containing field, so a nested change
to `/email` is reported as `/metadata/email`. Embedded payloads and top-level
payload methods require codecs to produce associative maps; JSON lists and
scalar roots are rejected.

The top-level API accepts a profile, codec ID, and encoded payload:

```php
$payload = $anonymizer->anonymizePayload('customer', 'json', $json);
$result = $anonymizer->anonymizePayloadWithReport('customer', 'json', $json);
```

## Execution limits and batches

Core execution limits are enabled by default and can be overridden with
`AnonymizationLimits`. The defaults are 10 MiB input payloads, depth 64,
100,000 visited nodes, 50,000 entries per collection, and embedded depth 8.
Limit failures identify the profile and current JSON Pointer.

`BatchAnonymizerInterface::anonymizeRecords()` accepts an iterable of map
records and yields transformed maps lazily. Records are processed independently
without reports; iteration stops at the first invalid or failing record.

`AuditAnonymizerInterface` is array-only. Its aggregate entries identify the
profile, compiled-profile fingerprint, path, action, handler, and count. Audit
results never contain input or replacement values.

## Document and NDJSON APIs

`DocumentAnonymizerInterface` accepts codecs implementing
`DocumentPayloadCodecInterface` and preserves either a map or an explicitly
declared list root. The core JSON codec and Bundle YAML codec support both
roots; the existing `anonymizePayload*()` methods remain map-only.

`NdjsonAnonymizerInterface` accepts an iterable of logical JSON lines and
returns a lazy result. NDJSON profiles must use `root: list`; each line must be
a JSON object. Consume `lines()` to process records, then call `audit()` for
the aggregate safe audit. Stream audit paths use a wildcard record prefix such
as `/*/email` and are unavailable after partial or failed consumption.
