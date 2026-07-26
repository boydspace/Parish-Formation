# Parish Formation

Parish Formation is a focused WordPress plugin for delivering and tracking
parish formation courses. It supports sacramental preparation, ministry
training, catechist formation, and similar parish programs without commercial
LMS features such as sales, subscriptions, commissions, or marketplaces.

## Requirements

- WordPress 6.0 or later
- PHP 8.3 or later
- Pretty permalinks are recommended
- An SMTP plugin such as FluentSMTP is recommended for production email

## Current features

- Block-editor courses, lessons, assessments, and questions
- Drag-and-drop curriculum ordering
- Required, optional, and sequential course sections
- Manual enrollment, expiration, unenrollment, reopening, and course runs
- Public course catalog with open self-enrollment, hidden courses, and protected access codes
- Secure course invitations with optional email restriction, expiration, use limits, and invited-user registration
- Configurable participant login and registration forms with automatic usernames and profile requirements
- Single-use passwordless login by emailed magic link or six-digit code
- Searchable participant administration with profile, enrollment, and account-security details
- Responsive front-end My Formation experience with AJAX navigation
- Automatic and staff-reviewed assessments with configurable passing rules
- Non-graded acknowledgement and reflection assessments
- Staff progress, assessment, course, and CSV reporting
- Reusable certificate designs with private PDF-only signature assets
- Verifiable, revocable, letter-size PDF completion certificates
- Certificate revocation and replacement issuance
- Editable branded participant and staff email notifications
- WordPress new-user account email replacement
- Custom Formation Participant, Coordinator, and Administrator permissions

## Installation

1. Copy the `parish-formation` folder into `wp-content/plugins/`.
2. Activate **Parish Formation** in WordPress.
3. Create and publish a page containing `[parish_formation_my_courses]`.
4. Create and publish a page containing `[parish_formation_courses]` for the
   open course catalog.
5. Create and publish a page containing `[formation-certificate]` for public
   certificate verification.
6. Configure **Parish Formation → Email Notifications**.
7. Configure an SMTP transport plugin for production delivery.

Database upgrades run automatically and are designed to be repeatable. Plugin
data is preserved when the plugin is deactivated or uninstalled.

## Shortcodes

- `[parish_formation_my_courses]` — participant course dashboard and learning
  interface.
- `[parish_formation_courses]` — public catalog of courses enabled for open
  self-enrollment.
- `[parish_formation_login]` — participant login form.
- `[parish_formation_registration]` — configurable participant registration form.
- `[formation-certificate]` — public certificate verification form.
- `[parish_formation_certificate_verification]` — compatibility alias for the
  certificate verification form.

- `[parish_formation_account_button]` displays a context-aware login or logout button.

## Roles and capabilities

- **Formation Participant** accesses front-end formation.
- **Formation Coordinator** manages courses, enrollments, assessments, grading,
  and reports.
- **Formation Administrator** additionally manages plugin-wide settings, roles,
  and privileged overrides.

The plugin checks custom capabilities rather than relying on role names.

## Email delivery

Parish Formation creates recipients, templates, branding, and event history,
then sends through WordPress `wp_mail()`. SMTP plugins remain responsible for
authentication, transport, provider responses, and final delivery diagnostics.
An **Accepted by Mailer** status means WordPress handed the message to the
configured mail system; it does not guarantee inbox delivery.

## Privacy

Parish Formation should contain only course enrollment, progress, assessment,
response, completion, and administrative information. It is not designed for
counseling records, tribunal information, psychological or medical information,
full sacramental records, or other sensitive pastoral case files.

Successful email bodies are not retained. Failed bodies are retained only while
needed for an administrator-initiated retry.

Parish Formation integrates with WordPress **Export Personal Data** and **Erase
Personal Data** tools. Approved erasure requests remove participant responses,
private-note content, contact metadata, notification details, and certificate
identity. Enrollment, progress, scoring, and audit records are retained only in
an anonymized form for operational reporting; affected certificates are revoked.

Administrators can configure retention periods for successful email metadata,
failed email records, and inactive course invitations under **Parish Formation
→ Settings → Privacy & Retention**. A daily WordPress scheduled event performs
cleanup, and administrators can also run it manually.

## Multisite

Parish Formation keeps courses, settings, custom tables, roles, rewrite rules,
and scheduled tasks scoped to each WordPress site. Network activation installs
the required state on every existing site, new network sites are initialized
automatically, and network deactivation clears each site's scheduled events.

## Local verification

Run `composer test` from the plugin directory to perform the read-only integration
smoke test and the isolated behavioral suite against the local WordPress
installation. Together they check plugin versions, custom tables, roles, post
types, shortcodes, privacy hooks, multisite setup, scheduled events, authorization,
self-enrollment, sequential lesson progression, assessment grading and attempt
limits, manual review, certificate issuance/revocation/reissue, completion, and
unenrollment. Behavioral fixtures are uniquely named and removed when the suite
finishes.

Run `composer build` to create a versioned, installable archive in `dist/`. The
builder verifies version consistency and required production files, bundles the
installed Composer dependencies, and excludes repository, test, tooling, and
other development-only files.

## Development

The public plugin release version and internal database schema version are
maintained separately. A feature release does not change the database version
unless its schema changes.
Source changes should follow WordPress security practices, support PHP 8.3, and
be linted and browser-tested before commit.

The current feature release is **1.3.0**. The current database schema version is
**1.0.2**.

See [docs/roadmap.md](docs/roadmap.md) for completed and planned milestones.

## Author and license

Created by [Father Andrew M. Boyd](https://fatherboyd.com). Plugin information:
[fatherboyd.com/plugins](https://fatherboyd.com/plugins).

Licensed under GPL-2.0-or-later.
