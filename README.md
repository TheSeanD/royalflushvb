# Royal Flush VB — local copy

Static local mirror of https://royal-flush-vb.webflow.io/ (Webflow staging site for royalflushvb.com — portable toilet / restroom trailer rentals, Virginia Beach; scraped 2026-07-03).

## Run locally

```sh
python3 -m http.server 8742
# open http://127.0.0.1:8742
```

Pages use absolute paths (`/css/...`, `/services`), so serve from this directory — opening `index.html` via `file://` won't work.

## Structure

- `index.html`, `404.html`, `about-us/`, `contact-us/`, `pricing/`, `services/` — one folder per page with `index.html`, matching the live URL paths
- `services/*` — five service detail pages (Webflow CMS items, captured as rendered HTML): standard/special-event portable toilets, restroom trailers, holding tanks, hitch-and-go trailer
- `back-bay-boxes/` — the Back Bay Boxes pages hosted inside this Webflow project
- `templates/`, `blog-posts/`, `product/`, `category/`, `posts-categories/`, `checkout/`, `paypal-checkout/`, `order-confirmation/`, `search/` — leftover wCopilot template + ecommerce pages, captured for fidelity (see Caveats)
- `css/`, `js/`, `images/`, `fonts/` — all Webflow CDN assets localized (filenames decoded; HTML still references them `%20`-encoded)
- `contact.php` — form handler; emails submissions to service@royalflushbbb.com
- `thank-you/` — post-submission confirmation page (forms redirect here)
- `robots.txt`, `sitemap.xml`, `llms.txt` — SEO / AI-search files (robots.txt allows AI search crawlers and blocks `/contact.php`)
- `.htaccess` — HTTPS + `www` redirect, gzip, caching, security headers, 404 page (Apache/cPanel)
- `css/site-enhancements.css`, `js/site-enhancements.js` — local additions (thank-you/404 button polish; reCAPTCHA v3 wiring)

## Forms

All quote/contact forms POST natively to `/contact.php`, which emails the submission to **service@royalflushbbb.com** (branded HTML email with plain-text alternative, Reply-To set to the submitter; optional Bcc via `contact-config.php` `'bcc'` or the `CONTACT_BCC` env var), sends the submitter a "We received your request" confirmation, and redirects to `/thank-you/`. Each form carries hidden `form_name`/`source_page` fields so the email identifies the lead source, plus a honeypot (`website`) for spam. The Webflow endpoint and Turnstile captcha are no longer used. reCAPTCHA v3: set `SITE_KEY` in `js/site-enhancements.js` and the secret in `contact-config.php` (copy `contact-config.example.php`; git-ignored, blocked by `.htaccess`). With no key/secret configured, verification is skipped.

Notes:
- Requires PHP (any cPanel host). Locally, `python3 -m http.server` serves the pages but won't execute PHP — use `php -S 127.0.0.1:8742` if PHP is installed.
- `contact.php` sends From: `forms@royalflushvb.com` — create that mailbox (or an SPF-valid equivalent) on the hosting account and adjust the `$from` constant if needed.

## Caveats

- **Google Fonts load at runtime** via `webfont.js` — requires internet, or self-host the fonts.
- `/search`, `/checkout`, `/paypal-checkout` and `/order-confirmation` are Webflow-dynamic features and do not function in a static mirror.
- The sitemap includes leftover "Electfy" template pages (electrical blog posts, product pages, style guide, etc.) and wCopilot promo markup; all captured for fidelity and can be pruned before deployment.
- The staging `noindex` robots meta was stripped from all pages.
- Only published, public pages were captured; any Webflow drafts or Editor-only content is not here.
