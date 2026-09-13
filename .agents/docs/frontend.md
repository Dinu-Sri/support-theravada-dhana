# Frontend

No bundler. Edit files in `assets/` and hard-refresh. Cache-bust only if a file is already versioned in its `<script>`/`<link>` tag.

## CSS

| File | Used by |
| --- | --- |
| `style.css` | donor pages, login, shared controls |
| `admin.css` | admin shell |
| `booking.css`, `booking-steps.css` | reservation form |
| `calendar.css` | donor calendar |
| `payment.css` | payment / receipt |
| `pricing-table.css` | admin pricing |
| `analytics.css` | admin analytics |
| `review-enhancements.css` | booking review step |
| `supervisor-styles.css` | supervisor-restricted chrome |

Theme: gold/brown `#d4822a` / `#b8860b`, cream monk-callout `#fff8e1`. Login background: `uploads/bck.webp` (this image is tracked; receipts are not).

Sinhala: Noto Sans Sinhala from Google Fonts on dashboard-like pages. Keep `lang="en"` unless you are implementing a real language switch.

Icons: Font Awesome 6 CDN. Reuse existing `<i class="fas ...">` names.

## JS

| File | Notes |
| --- | --- |
| `booking-steps.js` | current 4-step form; lots of `safeGetElement` null guards |
| `booking-steps-minimal.js` | reduced copy — do not load both |
| `booking.js` | older form logic |
| `calendar.js` | donor calendar |
| `payment.js` | receipt upload UX |
| `auth.js` | login/register tabs |
| `dashboard.js` | donor dashboard |
| `admin.js` | admin dashboard |
| `analytics.js` | Chart usage on analytics page |
| `pricing-table.js` | inline price edits |
| `date-utils.js` | shared date helpers |

Prefer extending the file the page already includes. Do not introduce jQuery or a SPA.

## PHP in templates

Escape output with `htmlspecialchars(...)`. Keep POST handlers at the top of the same `.php` file as the form (existing style). JSON endpoints belong in `api/` or `admin/get-*.php`, not mixed into HTML pages unless that is already how that feature works.
