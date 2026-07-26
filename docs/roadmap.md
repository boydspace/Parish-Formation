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

### 1.0.0 — Enrollment access

- Public course catalog. **Implemented**
- Course-level open self-enrollment. **Implemented**
- Safe login and registration handoff. **Implemented**
- Course access codes with expiration and usage limits. **Implemented**
- Secure invitation links with optional email restrictions. **Implemented**
- Enrollment source recording. **Implemented**
- Comprehensive immutable access-event history remains part of production
  hardening.

### 1.0.1 — Participant accounts (completed)

- Configurable login and registration shortcodes. **Implemented**
- Automatic usernames and extended participant profile fields. **Implemented**
- Dedicated participant administration. **Implemented**
- Passwordless magic-link login and optional one-time passcodes. **Implemented**

### 1.1.0 — Staff communication and notes

- Private, access-controlled staff notes with immutable edit/removal history. **Implemented**
- Manual participant reminders using notification templates. **Implemented**
- Note and reminder audit history. **Implemented**

### 1.2.0 — Acknowledgements and responses

- Assessment acknowledgement mode with one immutable, non-graded submission
  per participant course run. **Implemented**
- Acknowledgement and reflection question blocks using the existing assessment
  editor and course curriculum. **Implemented**
- Submission-based course progression without score or passing requirements.
  **Implemented**
- Mode-aware participant and staff email notifications. **Implemented**
- Mode-aware assessment history, course reports, detailed responses, and CSV
  exports. **Implemented**
- Acknowledgements excluded from graded certificate requirements. **Implemented**

### 1.3.0 — Certificate branding (completed)

- Reusable certificate designs assignable to multiple courses. **Implemented**
- Configurable logo, logo scale, colors, wording, signer, and paper orientation.
  **Implemented**
- Immutable design snapshots for newly issued certificates. **Implemented**
- Private, reduced-resolution, PDF-only signature storage with
  certificate-specific verification stamping. **Implemented**
- PDF-only certificate distribution, public verification, revocation, and
  replacement issuance. **Implemented**

## Planned milestones

### 1.4.0 — Production hardening (next)

- WordPress personal-data export and privacy erasure integration. **Implemented**
- Administrative retention-period controls and scheduled cleanup for email
  activity and inactive invitations. **Implemented**
- Accessibility and multisite lifecycle review. **Implemented**
- Performance, upgrade, and release-package testing.
- System status and scheduled-task diagnostics. **Implemented**
- Automated repository, authorization, grading, progression, certificate, and
  upgrade tests.
- Immutable enrollment-access and security event history.

## Versioning

- The plugin release version tracks user-facing feature milestones.
- The database schema version changes only when tables or stored schema must be
  upgraded.
- Release 1.3.0 currently uses database schema 1.0.2.

## Development rules

- Support PHP 8.3 and follow WordPress coding and security practices.
- Sanitize input, escape output, verify nonces, and check narrow capabilities.
- Build and browser-test one meaningful checkpoint at a time.
- Lint edited PHP files and validate JavaScript before milestone approval.
- Do not commit a milestone until its browser test is approved.
