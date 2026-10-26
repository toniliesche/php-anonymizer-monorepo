# Profile schema

Profiles are immutable definitions compiled from PHP arrays. The same
vocabulary is reserved for the future Symfony YAML adapter.

## Quick reference

A profile is registered under a unique name and must contain `mode` plus at
least one of `rules` or `global_rules`. The profile name must match
`^[A-Za-z][A-Za-z0-9_.-]*$`.

```php
$profile = ProfileDefinition::fromArray('customer.public', [
    'mode' => 'redact',             // required: redact or preserve
    'root' => 'map',                // optional: map (default) or list
    'key_normalization' => false,   // optional: false (default) or true
    'rules' => [/* ordered rules */],
    'global_rules' => [/* optional ordered rules */],
    'fragments' => [/* optional reusable local rules */],
    'metadata' => [/* optional governance strings */],
]);
```

Top-level keys are closed: unknown keys are rejected. `rules` and
`global_rules`, when present, must be non-empty lists. A profile may contain
only `global_rules`, but it must contain at least one selector list.

Each ordinary rule has this shape:

```php
[
    'field' => 'email',             // required non-empty string
    'when' => [/* optional condition */],

    // Choose at most one rule body:
    'children' => [/* map rules */],
    'each' => true,                 // every string in a list
    'each' => [/* rules per map item */],
    'strategy' => 'star_mask',      // string leaf replacement
    'transform' => [/* typed leaf */],
    'embedded' => [/* encoded leaf */],

    'key_id' => 'customer-v1',      // optional contextual strategy/transform ID
    'on_scalar' => 'preserve',      // only with children or each
]
```

The rule body determines the expected input shape:

| Rule body | Expected value | Effect |
| --- | --- | --- |
| no body | string | Apply the mode’s default: redact in `redact` mode, pass through in `preserve` mode. |
| `strategy` | string | Resolve a registered replacement strategy. |
| `transform` | supported typed value | Resolve a registered value transformation. |
| `embedded` | encoded string | Decode, apply another profile, and encode again. |
| `children` | associative map | Recursively apply nested rules to map fields. |
| `each: true` | list of strings | Replace every string list item. |
| `each: [...]` | list of associative maps | Apply nested rules to every map item. |

Shape mismatches are errors unless a traversing rule supplies `on_scalar` and
the value is a string. Rules do not rename fields or create missing fields;
report-enabled operations include missing profile-local selector fields in
`missingPaths()` when their conditions match. Global rules do not report
missing fields because they do not declare a fixed field set. With key
normalization enabled, presence uses the same exact-match-first and normalized
lookup as rule selection. A field found through a normalized alias is not
reported as missing; when normalized spellings collide, only the rule resolved
for the existing input key is considered present. Changed paths retain the
input key's original spelling.

## Governance metadata and reusable fragments

Profiles may include optional governance metadata. The map may contain any
subset of `schema_version`, `profile_version`, `owner`, `status`, and `purpose`;
unknown keys and empty values are rejected. `status` is `active` or
`deprecated`. Metadata can be inspected through the framework integration's
`ProfileInspectorInterface` service; the standalone core builder does not
provide that service. Metadata does not affect anonymization or the behavior
fingerprint. Do not put secrets in metadata.

The metadata keys are all optional non-empty strings. `status` accepts only
`active` or `deprecated`:

```php
'metadata' => [
    'schema_version' => '2',
    'profile_version' => '2026-08',
    'owner' => 'privacy',
    'status' => 'active',
    'purpose' => 'support exports',
],
```

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

Fragments can compose nested rules as well as leaf rules:

```php
'fragments' => [
    [
        'name' => 'address',
        'rules' => [[
            'field' => 'address',
            'children' => [
                ['field' => 'street'],
                ['field' => 'postcode'],
            ],
        ]],
    ],
    [
        'name' => 'contact',
        'rules' => [['field' => 'email'], ['field' => 'phone']],
    ],
],
'rules' => [
    ['use' => 'address'],
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

<!-- executable -->
```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

$profile = ProfileDefinition::fromArray('customer', [
    'mode' => 'redact',
    'fragments' => [['name' => 'contact', 'rules' => [['field' => 'email'], ['field' => 'phone']]]],
    'rules' => [['use' => 'contact']],
]);
$safe = AnonymizerBuilder::create()->withProfile($profile)->build()->anonymize('customer', [
    'email' => 'ada@example.test',
    'phone' => '+44 20 0000 0000',
]);

if ($safe !== ['email' => '[REDACTED]', 'phone' => '[REDACTED]']) {
    throw new RuntimeException('The fragment example did not expand as expected.');
}
```

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

Global rules can be run against nested maps and lists:

```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

$profile = ProfileDefinition::fromArray('customer', [
    'mode' => 'redact',
    'key_normalization' => true,
    'global_rules' => [['field' => 'email_address'], ['field' => 'tags', 'each' => true]],
]);
$safe = AnonymizerBuilder::create()->withProfile($profile)->build()->anonymize('customer', [
    'email-address' => 'ada@example.test',
    'orders' => [['emailAddress' => 'grace@example.test', 'tags' => ['private', 'vip']]],
]);

if ($safe['email-address'] !== '[REDACTED]' || $safe['orders'][0]['emailAddress'] !== '[REDACTED]' || $safe['orders'][0]['tags'] !== ['[REDACTED]', '[REDACTED]']) {
    throw new RuntimeException('The global-rule example did not match normalized keys.');
}
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

<!-- executable -->
```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

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

### Traversal rules in detail

`children` selects fields inside one associative map. Its value is an ordered,
non-empty rule list, and those rules can themselves contain `children` or
`each`, so traversal can be nested to any supported execution depth:

```php
[
    'field' => 'account',
    'children' => [
        ['field' => 'email'],
        [
            'field' => 'address',
            'children' => [
                ['field' => 'street'],
                ['field' => 'postcode', 'strategy' => 'star_mask'],
            ],
        ],
    ],
]
```

`each: [...]` selects a list of associative maps and applies the nested rule
list independently to every item. The item paths include their numeric index:

```php
[
    'field' => 'orders',
    'each' => [
        ['field' => 'reference'],
        ['field' => 'shipping', 'children' => [
            ['field' => 'address', 'children' => [['field' => 'street']]],
        ]],
    ],
]
```

`each: true` is the short form for a list containing only strings:

```php
['field' => 'tags', 'each' => true]
```

The forms are mutually exclusive: one rule cannot contain both `children` and
`each`, and a traversal rule cannot also contain `strategy`, `transform`, or
`embedded`. Nested sibling fields must be unique. A `use` fragment entry is a
rule entry, so fragments can be placed inside `children` and list-form `each`
as well as at the profile root.

When a field sometimes has a map/list value and sometimes a string value, use
`on_scalar` on the traversal rule:

```php
[
    'field' => 'address',
    'children' => [['field' => 'street']],
    'on_scalar' => 'preserve', // preserve a scalar address; redact would replace it
]
```

`on_scalar` accepts only `redact` or `preserve`, and is invalid on leaf rules.
It applies only to strings; numbers, booleans, nulls, and other unsupported
shapes still raise an unsupported-value error. In `preserve` mode, selected
strings are allowed through and unselected strings encountered during
recursive traversal are redacted.

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

### Core transformation options

Transformation options are always a map with string keys. The built-in
transformation contracts are:

| Type | Supported input | Options | Result |
| --- | --- | --- | --- |
| `nullify` | supported scalar or temporal value | none | `null` |
| `partial_mask` | string | optional non-negative `prefix`, `suffix`, and non-empty `character` | Same-length string; defaults to hiding all but the last four characters. Strings of four or fewer characters remain unchanged with the defaults. |
| `date_generalize` | RFC 3339 string or `DateTimeInterface` | required `unit`: `year`, `month`, `day`, or `hour` | String input stays a string; date objects return as `DateTimeImmutable`, rounded to the unit. |
| `numeric_bucket` | integer or float | required positive `size`; optional numeric `origin` | Same numeric type rounded down to the bucket boundary relative to `origin`. |

For example, `partial_mask` is a transformation, not a replacement strategy.
This complete example rounds a numeric value down to its bucket boundary:

<!-- executable -->
```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

$profile = ProfileDefinition::fromArray('customer', [
    'mode' => 'redact',
    'rules' => [['field' => 'income', 'transform' => [
        'type' => 'numeric_bucket',
        'options' => ['size' => 100, 'origin' => 0],
    ]]],
]);
$result = AnonymizerBuilder::create()->withProfile($profile)->build()->anonymize('customer', ['income' => 245]);

if ($result !== ['income' => 200]) {
    throw new RuntimeException('The numeric bucket transformation example failed.');
}
```

Other transformation forms include:

```php
['field' => 'name', 'transform' => [
    'type' => 'partial_mask',
    'options' => ['prefix' => 1, 'suffix' => 2, 'character' => '·'],
]]
['field' => 'birthDate', 'transform' => [
    'type' => 'date_generalize',
    'options' => ['unit' => 'month'],
]]
['field' => 'internalNote', 'transform' => ['type' => 'nullify']]
```

Transformation IDs and options are validated when `AnonymizerBuilder::build()`
assembles the façade, including rules that may not match a particular input.
The transformation's supported runtime type is checked when a matching rule
is applied. Unknown IDs, invalid options, or unsupported values fail; they do
not silently fall back to redaction.

Pass an `AnonymizationContext` to the unified façade method when contextual
execution is required. It contains canonical string attributes such as
`tenant` and `scope`. Key material is resolved by the application and never
belongs in profiles or logs.

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

For a list document, declare the root and use the document API explicitly:

<!-- executable -->
```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

$profile = ProfileDefinition::fromArray('records', [
    'mode' => 'redact',
    'root' => 'list',
    'rules' => [['field' => 'email']],
]);
$payload = AnonymizerBuilder::create()->withProfile($profile)->build()->anonymizeDocument(
    'records',
    'json',
    '[{"email":"one@example.test"},{"email":"two@example.test"}]',
);

if ($payload !== '[{"email":"[REDACTED]"},{"email":"[REDACTED]"}]') {
    throw new RuntimeException('The list document example did not preserve its root shape.');
}
```

## Execution limits and batches

Core execution limits are enabled by default and can be overridden with
`AnonymizationLimits`. The defaults are 10 MiB input payloads, depth 64,
100,000 visited nodes, 50,000 entries per collection, and embedded depth 8.
Input size is measured in encoded bytes and checked before decoding. Traversal
starts at depth zero and counts each map value, list item, and scalar leaf once;
unselected branches are included, and a container's size is checked whenever
it is visited. Embedded payloads share the enclosing execution budget, while
batch records use independent budgets. Limit failures identify the profile and
current JSON Pointer.

`AnonymizerInterface::anonymizeRecords()` accepts an iterable of map records and
yields transformed maps lazily. Records are processed independently without
reports; iteration stops at the first invalid or failing record.

`AnonymizerInterface::anonymizeWithAudit()` returns aggregate entries that
identify the profile, compiled-profile fingerprint, path, action, handler, and
count. Audit results never contain input or replacement values.

## Document and NDJSON APIs

`AnonymizerInterface::anonymizeDocument()` accepts codecs implementing
`DocumentPayloadCodecInterface` and preserves either a map or an explicitly
declared list root. The core JSON codec and Bundle YAML codec support both
roots; the existing `anonymizePayload*()` methods remain map-only. JSON
operations preserve original object containers, including `{}`, separately
from arrays, including `[]`, through payload, document, and embedded JSON
encoding.

`AnonymizerInterface::anonymizeNdjson()` accepts an iterable of logical JSON
lines and returns a lazy result. NDJSON profiles must use `root: list`; each
line must be a JSON object. The input-byte limit and traversal budgets apply to
each record independently after its trailing line ending is removed; there is
no total-stream byte limit. Records retain their own JSON object and array
shapes. Consume `lines()` to process records, then call `audit()` for the
aggregate safe audit. Stream audit paths use a wildcard record prefix such as
`/*/email` and are unavailable after partial or failed consumption.

## Composition and matching precedence

Profile processing has two phases:

1. `ProfileDefinition::fromArray()` validates the profile shape and expands
   fragments. Provider results are collected when `AnonymizerBuilder::build()`
   runs.
2. `build()` validates registered strategy, codec, and transformation IDs and
   transformation options in both scoped and global rules. Runtime values are
   checked when the corresponding operation reaches each rule.

The resulting behavior follows these rules:

- Fragment expansion is depth-first. A fragment may use another fragment, but
  references stay within the same profile.
- When two fragment expansions select the same field at one level, the first
  fragment rule wins.
- A direct rule at that level replaces a fragment rule selecting the same
  field. Duplicate direct rules remain invalid.
- A local rule is considered before `global_rules`. If a local rule matches,
  it wins; if its condition does not match, the global rule may still apply.
- With `key_normalization: true`, an exact field spelling wins. If no exact
  spelling exists, the first declaration whose normalized spelling matches is
  selected. Output keys retain their original spelling.
- Conditions inspect a snapshot of the containing map before rules at that map
  are applied. Reordering transformations cannot change a sibling condition.
- Embedded profile references are validated across all nested rules. A profile
  may not embed itself directly or through a cycle.

For example, the direct profile rule replaces the fragment rule for
`email_address`. For a `person`, the local transformation wins. For a
`company`, its condition does not match and the global rule applies. Key
normalization lets both rules match the input spelling `emailAddress`; output
keeps that original key:

<!-- executable -->
```php
use PhpAnonymizer\Anonymizer\Application\Anonymization\AnonymizerBuilder;
use PhpAnonymizer\Anonymizer\Domain\Profile\ProfileDefinition;

$profile = ProfileDefinition::fromArray('customer', [
    'mode' => 'redact',
    'key_normalization' => true,
    'fragments' => [[
        'name' => 'contact',
        'rules' => [['field' => 'email_address', 'strategy' => 'star_mask']],
    ]],
    'rules' => [
        ['use' => 'contact'],
        ['field' => 'email_address', 'when' => ['path' => 'kind', 'equals' => 'person'], 'transform' => [
            'type' => 'partial_mask',
            'options' => ['prefix' => 1, 'suffix' => 2],
        ]],
    ],
    'global_rules' => [['field' => 'email_address', 'strategy' => 'star_mask']],
]);
$anonymizer = AnonymizerBuilder::create()->withProfile($profile)->build();
$person = $anonymizer->anonymize('customer', ['kind' => 'person', 'emailAddress' => 'ada@example.test']);
$company = $anonymizer->anonymize('customer', ['kind' => 'company', 'emailAddress' => 'ada@example.test']);

if (!str_starts_with($person['emailAddress'], 'a') || !str_ends_with($person['emailAddress'], 'st') || $company['emailAddress'] !== str_repeat('*', mb_strlen('ada@example.test', 'UTF-8'))) {
    throw new RuntimeException('The local, fragment, and global rule precedence example failed.');
}
```

## Validation checklist

The following definitions are invalid:

```php
// Unknown profile key.
['mode' => 'redact', 'rules' => [], 'unknown' => true]

// A rule must have a non-empty string field.
['mode' => 'redact', 'rules' => [['field' => '']]]

// Traversal and a leaf action cannot be combined.
['mode' => 'redact', 'rules' => [[
    'field' => 'address',
    'children' => [['field' => 'street']],
    'strategy' => 'star_mask',
]]]

// A list-of-maps rule must use a non-empty nested rule list.
['mode' => 'redact', 'rules' => [['field' => 'orders', 'each' => []]]]

// Global rules cannot traverse or embed.
['mode' => 'redact', 'global_rules' => [[
    'field' => 'metadata',
    'children' => [['field' => 'email']],
]]]
```

Validation also rejects duplicate sibling fields, duplicate fragment names,
missing fragment references, fragment cycles, malformed conditions, invalid
transformation options, unknown strategies/codecs/transformations, and
contextual rules executed without the required context. These failures are
reported as profile or operation errors and never cause a permissive fallback.
