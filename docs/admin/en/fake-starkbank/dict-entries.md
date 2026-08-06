---
title: DICT
icon: heroicon-o-key
order: 4
---

# DICT

The PIX key registry of this fake — the only place where a cash-out beneficiary exists, and where the BR Code preview finds the receiver tax id the consuming application checks before funding.

Two keys are seeded on every start: the reference beneficiary and the cross-fake funding receiver. Both come from configuration, so re-seeding rewrites them instead of duplicating them.

## Registering a key

**Registrar chave** opens the form. You give the key, its type, the holder (name, tax id, natural person or legal entity) and the fictional bank. The opaque branch and account blobs are **not** typed: they are derived from the key, exactly as the provider emits them.

Registering the same key again corrects the holder — a DICT record is declarative state, never a second owner for the same key.
