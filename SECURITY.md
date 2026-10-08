# Security policy

## Reporting a vulnerability

Please **do not open a public issue** for security problems.

- Email **security@ctrlcheese.de**, or
- use GitHub's private reporting: **Security → Report a vulnerability** on this repository.

Include the CtrlField version, WordPress and PHP versions, the steps to reproduce and, if you have one, a proof of concept. Reports in English, German or Portuguese are welcome.

## What happens next

- We confirm receipt within **3 working days**.
- We assess the report and keep you updated at least once a week.
- Fixes for confirmed issues are released as soon as they are ready; critical issues get a release of their own.
- After the fix is out, we publish an advisory and credit you (unless you prefer not to be named).

Please give us a reasonable time to release a fix (90 days at most) before disclosing details publicly.

## Supported versions

Security fixes are made for the **latest release** of CtrlField and CtrlField Pro. Please update before reporting.

| Requirement | Supported |
|---|---|
| PHP | 8.2 and newer |
| WordPress | 6.5 and newer |

## Scope

In scope: the CtrlField plugin (Free and Pro) — for example XSS, CSRF, SQL injection, privilege escalation, data exposure through the REST API or admin screens.

Out of scope: issues that need an administrator account to exploit (administrators can already run arbitrary code in WordPress), vulnerabilities in WordPress core or other plugins, and reports from automated scanners without a working proof.
