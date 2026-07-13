# Audit of the current site (collegesailing.org)

Findings from a live crawl on 13 Jul 2026 — 17 pages on the national site plus
8 subdomains (MAISA, NEISA, MCSA, NWICSA, PCCSC, SAISA, SEISA, Scores,
TechScore). Full report: see the published artifact from the audit session.
Kept here so the reasoning behind decisions in this rebuild is traceable.

## Critical

- **Broken TLS on 4 of 7 conference subdomains.** MAISA, NEISA, MCSA, and
  NWICSA (plus `nationals.collegesailing.org`) serve a hosting-provider
  default certificate that doesn't match their hostname — browsers show a
  full security warning.
- **No forced HTTPS, no HSTS** on the main domain; session cookies lack the
  `Secure` flag.
- **Every page shares one `<title>` and a blank meta description.**
  Confirmed identical across all 17 pages sampled — a template-level bug.
- **No `robots.txt` or `sitemap.xml`** anywhere on the main domain or any
  subdomain.

## Moderate

- Duplicate `<head>`/`<body>` tags on every page (three copies on Media
  pages) — a broken shared header partial.
- Informative images (news photos, sponsor logos) carry empty `alt` text
  sitewide (News: 15/18 images, Resources: 7/10).
- Live data (schedules, rankings, team database) lives in linked Google
  Sheets rather than real, indexable pages.
- Platform stack is 10+ years stale: Bootstrap 3.3.7 (2016), jQuery 2.1.1
  (2014), Font Awesome 4.1.0 (2014), IE8 polyfills still shipping.
- Flat 10-item top-level nav with no audience grouping; Resources alone
  fans out to 10 sub-items.
- The "conference ecosystem" is six different implementations: two run a
  matching legacy Bootstrap theme, four run an older Bootstrap build behind
  broken TLS, SEISA is a bare Google Sites page, SAISA lives on a fully
  separate domain with no ICSA branding.
- Site search posts to the homepage instead of a linkable results page.

## What's working (worth keeping)

- Real institutional depth: championship rules, hall of fame records,
  governance docs are all present and comprehensive.
- Active, regularly updated news.
- `scores.collegesailing.org` is a modern, well-built app — a reasonable
  design reference point.
- No layout tables, a real viewport meta tag, Bootstrap's grid already in
  place — the responsive foundation isn't the problem.

## Rebuild roadmap (from the audit)

0. Fix the 4 broken certificates (hosting-provider ticket, not code)
1. Fix per-page SEO/meta, alt text, markup validity, sitemap/robots.txt
2. Rebuild the platform + IA (this repo's work)
3. Unify all seven conference sites onto one system
