# Profile schema

Profiles are immutable definitions compiled from PHP arrays. The same
vocabulary is reserved for the future Symfony YAML adapter.

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

`strategy` and `embedded` are reserved for the extension and JSON work in a
later iteration.
