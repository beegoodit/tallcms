# Security Policy

## Supported versions

Security fixes are provided for the latest stable TallCMS release. Before
reporting a vulnerability, please confirm that it is reproducible on the latest
release. Users of older releases should upgrade before requesting a security
fix.

This policy covers both the standalone application in `tallcms/tallcms` and the
core package published as `tallcms/cms`.

## Reporting a vulnerability

Please do not open a public issue, discussion, or pull request for a suspected
vulnerability.

Use [GitHub private vulnerability reporting][private-report] to send the report
confidentially. If GitHub private reporting is unavailable, email
[hello@tallcms.com](mailto:hello@tallcms.com) with the subject
`[SECURITY] TallCMS vulnerability report`.

Include as much of the following as possible:

- The affected TallCMS version and whether it is a standalone or package install
- The relevant PHP, Laravel, Filament, browser, and operating-system versions
- A description of the vulnerability and its potential impact
- Reproduction steps or a minimal proof of concept
- Any known mitigations or workarounds
- Whether the vulnerability has been disclosed elsewhere
- A secure way to contact you for follow-up

Do not include production credentials, personal data, or other third-party
secrets in a report or proof of concept.

## What to expect

We aim to:

- Acknowledge a complete report within three business days
- Provide an initial assessment within seven business days
- Send progress updates at least every fourteen days while remediation is active

These are response targets rather than guarantees. Resolution time depends on
severity, complexity, and the need to coordinate fixes with upstream projects.

After validating a report, we will work with the reporter on remediation and a
coordinated disclosure date. When appropriate, we will publish a GitHub security
advisory, request a CVE, release fixed versions, and credit the reporter with
their consent. Please keep the report confidential until we confirm that users
have had a reasonable opportunity to update.

## Safe harbor

We support good-faith security research performed within applicable law that
avoids privacy violations, service disruption, data destruction, and access to
data beyond what is necessary to demonstrate the issue. We will not pursue
legal action against researchers who follow this policy, make a good-faith
effort to avoid harm, and give us reasonable time to remediate before public
disclosure.

[private-report]: https://github.com/tallcms/tallcms/security/advisories/new
