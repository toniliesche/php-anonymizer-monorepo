# Changelog

## Unreleased

- Added `SymfonySerializerExtension` for registering object anonymization with
  the core builder.
- Removed the legacy `SymfonyAnonymizer` and `SymfonyObjectAnonymizer`
  dispatchers; object normalization and denormalization now live in the
  builder object adapter.

## 1.3.0

- Added contextual object anonymization and contextual report methods while
  preserving source-object immutability.

## 1.2.0

- Updated the adapter’s core dependency compatibility for contextual
  anonymization.

## 1.0.0

- Initial stable Symfony Serializer integration for immutable object handling.
