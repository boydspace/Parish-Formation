# Assessment architecture for 1.5.0

## Existing lifecycle

Assessment question blocks are synchronized to private `pf_question` posts.
Question configuration is stored in post meta. Assessment attempts and their
individual responses are stored in `pf_assessment_attempts` and
`pf_assessment_answers`. The response and snapshot columns are `LONGTEXT` and can
hold structured JSON without changing existing rows.

Existing question identifiers remain supported:

- `multiple_choice`
- `true_false`
- `acknowledgement`
- `reflection`

`reflection` remains the compatibility identifier for Reflection Response.
`reflection_response` maps to it, while the distinct instructor-reviewed
Paragraph Response uses `paragraph`.

## Phase 1 services

- `Parish_Formation_Question_Type_Registry`: canonical types, aliases,
  categories, capabilities, and implementation phase.
- `Parish_Formation_Question_Config`: normalized shared and type-specific
  configuration with legacy post-meta adapters.
- `Parish_Formation_Question_Grading_Service`: response validation, completion,
  automatic scoring, manual-review decisions, and structured feedback.
- `Parish_Formation_Question_Renderer`: accessible learner controls without
  exposing grading keys.
- `Parish_Formation_Question_Snapshot`: immutable prompt, configuration,
  choices, scoring, and version data for historical attempts.

## Storage plan

Phase 1 adds `_pf_question_config` post meta and continues writing legacy meta so
old code, old blocks, and existing questions remain readable. Stable child IDs
live inside the structured configuration. Structured learner responses are JSON
encoded in the existing answer column. No database migration is required.

Phase 4 may add response-review and uploaded-file relationship tables if the
review history and protected attachment lifecycle cannot be represented safely
in the current answer table. Any such migration will be additive and idempotent.

## Phase 2 structured responses

Multiple Select stores selected stable choice IDs as a JSON array in the
existing answer column. Short Answer stores the learner's original text exactly
as submitted and applies normalization only to an in-memory comparison value.
Neither addition requires a database migration. Multiple Select supports
all-or-nothing, partial-credit, and partial-credit-with-penalty grading; scores
are clamped between zero and the configured maximum.

## Planned implementation files

The five Phase 1 service classes are new. The question block, assessment
repository, frontend shortcodes, editor JavaScript/CSS, review screen, reports,
privacy integration, and automated tests will be adapted incrementally. Each
phase must leave existing question records and attempts readable.
