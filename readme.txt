=== Parish Formation ===
Contributors: fatherboyd
Tags: formation, courses, assessments, certificates, parish
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.3
Stable tag: 1.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Deliver and track parish formation courses, assessments, enrollment, participant feedback, and completion certificates.

== Description ==

Parish Formation provides focused online formation tools for parishes. It can
support sacramental preparation, ministry training, catechist formation,
compliance education, and similar parish programs.

Features include:

* Block-editor courses, lessons, assessments, and questions.
* Sequential course progression with required and optional sections.
* Open enrollment, access codes, hidden courses, and invitations.
* Automatic grading, staff review, feedback, and resubmission workflows.
* Multiple question types, partial credit, and protected file uploads.
* Participant registration, password login, and passwordless login.
* Editable participant and staff email notifications.
* Course, assessment, participant, and detailed-response reports.
* Reusable PDF certificate designs with public verification.

An SMTP plugin is recommended for reliable production email delivery.

== Installation ==

1. Upload the `parish-formation` folder to `/wp-content/plugins/`, or install the release ZIP from the WordPress Plugins screen.
2. Activate Parish Formation.
3. Create pages for the desired plugin shortcodes as described in `README.md`.
4. Configure account, email, certificate, privacy, and retention settings.
5. Create a course and arrange its lessons and assessments.

== Frequently Asked Questions ==

= Does Parish Formation sell courses or process payments? =

No. It is focused on parish formation delivery and tracking rather than
commerce, subscriptions, commissions, or marketplaces.

= Should the site use an SMTP plugin? =

Yes. An SMTP plugin such as FluentSMTP is recommended for production email.

= Are uploaded assessment files public? =

No. Assessment uploads use protected, permission-checked access rather than
public attachment URLs.

== Changelog ==

= 1.5.1 =

* Added WordPress.org-compatible plugin metadata and release readme.
* Improved request validation, output escaping, translation context, and filesystem compatibility.
* Documented intentional custom-table queries and established post-meta relationship queries for Plugin Check.
* Cleaned the release package so development tests, tools, Git metadata, and prior archives are not distributed.

= 1.5.0 =

* Initial public release.
* Course, lesson, enrollment, account, and participant administration.
* Expanded automatic and staff-reviewed assessment system.
* Feedback, resubmission, notifications, and detailed reporting.
* Verifiable PDF completion certificates.
