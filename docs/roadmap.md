# Parish Formation Roadmap

## Purpose

Parish Formation is a focused WordPress system for parish formation courses. It is
not intended to become a commercial learning management system, marketplace,
social network, or academic gradebook.

## Technical direction

- Use WordPress users for participant accounts.
- Use custom post types for courses and lessons.
- Use the block editor for lesson content.
- Use custom tables only for data that needs relational querying, such as
  enrollments and lesson progress.
- Use custom capabilities instead of checking role names.
- Keep the plugin version separate from the database schema version.
- Make activation and upgrade routines safe to run more than once.
- Provide participant features on front-end pages; participants should not need
  access to WordPress administration screens.
- Add AJAX or REST endpoints only when they materially improve the participant
  experience.
- Bundle UIkit locally for participant-interface styling and pin its version.

## Data and privacy boundaries

The plugin may store information needed for enrollment, formation progress,
participant responses, staff notes about formation status, and completion.

The plugin must not be designed to store counseling records, psychological
evaluations, tribunal information, sensitive pastoral disclosures, prenuptial
investigation documents, full sacramental records, or medical information.

Deactivation must preserve plugin data. Uninstall must also preserve data unless
a future administrator-facing deletion option is deliberately enabled and
confirmed. Any future deletion process must account for WordPress multisite.

## Milestones

### 0.1.0 — Plugin foundation

- Bootstrap and constants.
- Versioned activation and upgrade structure.
- Formation roles and custom capabilities.
- Parish Formation admin menu and status dashboard.
- Conservative uninstall behavior.
- Internal roadmap.

### 0.2.0 — Courses and lessons (complete)

- Course and lesson custom post types.
- Block-editor lesson content.
- Course assignment and lesson ordering.
- Required and optional lessons.
- Course introduction and completion content.
- Course lesson summary for staff.

### 0.3.0 — Enrollment (complete)

- Enrollment database table with appropriate indexes and uniqueness rules.
- Manual enrollment by authorized parish staff.
- Enrollment status and important dates.
- Optional enrollment expiration.
- Reversible unenrollment and re-enrollment.

### 0.4.0 — Participant course experience (complete)

- Front-end My Formation page.
- Enrolled-course and lesson views.
- Sequential progression through required lessons.
- Participant lesson-completion action with nonce and authorization checks.
- Explicit optional-lesson skipping.
- Progress percentages, course completion, and completion dates.
- Responsive UIkit learning interface with persistent course navigation.
- Staff course reset for testing and reopening a participant's course.

### 0.5.0 — Staff progress visibility

- Participant and course progress views.
- Detailed lesson completion and response visibility.
- Staff participant search and status filters.
- Completion overrides and reopening completed courses.

### Later releases

- Formation questions and acknowledgements.
- Private staff notes.
- CSV exports.
- Reminder emails and WordPress cron integration.
- Completion certificates and certificate numbering.
- Access codes and invitation links.

Quizzes, certificates, reminder emails, couples, access codes, and advanced
reporting are intentionally excluded from the first usable release.

## Development rules

- Support PHP 8.3 and follow WordPress coding and security practices.
- Sanitize input, escape output, verify nonces, and check the narrowest suitable
  capability.
- Store PHP files as UTF-8 without a byte-order mark.
- Build and test one meaningful feature at a time.
- Lint every edited PHP file.
- Do not commit a feature until its browser test is approved.
