# Parish Formation Roadmap

## Purpose

Parish Formation is a focused WordPress system for parish formation courses. It
is not a commercial LMS, marketplace, social network, or academic gradebook.

The plugin supports parish programs such as sacramental preparation, liturgical
minister training, catechist formation, and volunteer compliance education.

## Technical direction

- Use WordPress users for participant accounts.
- Use custom post types and the block editor for formation content.
- Use custom tables for relational enrollment, progress, assessment,
  certificate, and notification records.
- Use custom capabilities so parish responsibilities can be delegated safely.
- Keep participant workflows on front-end pages.
- Use AJAX or REST where it meaningfully improves the interface.
- Bundle participant-interface dependencies locally.
- Preserve data on deactivation and uninstall unless a future administrator
  deliberately confirms deletion.

## Privacy boundary

Store only information needed for enrollment, formation progress, responses,
staff administration, and completion. Do not store counseling records,
psychological evaluations, tribunal information, sensitive pastoral
disclosures, prenuptial investigation documents, full sacramental records, or
medical information.

## Completed milestones

### 0.1.0 — Plugin foundation

- Bootstrap, versioned upgrades, custom roles and capabilities.
- Parish Formation administration menu and conservative uninstall behavior.

### 0.2.0 — Courses and lessons

- Course and lesson post types with block-editor content.
- Required and optional lessons, assignment, and ordering.

### 0.3.0 — Enrollment

- Manual enrollment, expiration, unenrollment, and re-enrollment.

### 0.4.0 — Participant experience

- My Formation front end, sequential progression, optional skipping, progress,
  completion, AJAX navigation, and course reset.

### 0.5.0 — Staff visibility

- Participant progress, filters, detail, completion overrides, and reopening.

### 0.6.0–0.7.0 — Assessments and reporting

- Block-based questions and ordered course assessments.
- Automatic and manual grading, passing rules, attempt limits, progression
  gates, staff review, audit history, and CSV reports.

### 0.8.0 — Certificates

- Course certificate settings, automatic/manual issuance, verification codes,
  public verification, letter-size PDF generation, certificate administration,
  revocation, reissuance, and CSV reporting.

### 0.9.0 — Email notifications

- Branded HTML design and editable AJAX-loaded templates.
- Participant, staff, certificate, assessment, expiration, and WordPress
  new-user notifications.
- Global and course-level controls, WordPress cron reminders, duplicate
  protection, activity logs, and failed-message retry.
- Compatible with SMTP transport plugins through WordPress `wp_mail()`.

## Planned milestones

### 1.0.0 — Enrollment access (in progress)

- Public course catalog. **Implemented**
- Course-level open self-enrollment. **Implemented**
- Safe login and registration handoff. **Implemented**
- Course access codes with expiration and usage limits. **Implemented**
- Secure invitation links with optional email restrictions. **Implemented**
- Enrollment source and access audit history.

### 1.0.1 — Participant accounts (in progress)

- Configurable login and registration shortcodes. **Implemented**
- Automatic usernames and extended participant profile fields. **Implemented**
- Dedicated participant administration.
- Passwordless magic-link login and optional one-time passcodes.

### 1.1.0 — Staff communication and notes

- Private, access-controlled staff notes.
- Manual participant reminders using notification templates.
- Note and reminder audit history.

### 1.2.0 — Acknowledgements and responses

- Dedicated participant acknowledgements.
- Response review and exports independent of graded assessments.

### 1.3.0 — Certificate branding

- Optional signature image and additional certificate design controls.

### 1.4.0 — Production hardening

- Privacy export/erasure integration and retention controls.
- Accessibility and multisite review.
- Performance, upgrade, and release-package testing.

## Development rules

- Support PHP 8.3 and follow WordPress coding and security practices.
- Sanitize input, escape output, verify nonces, and check narrow capabilities.
- Build and browser-test one meaningful checkpoint at a time.
- Lint edited PHP files and validate JavaScript before milestone approval.
- Do not commit a milestone until its browser test is approved.
