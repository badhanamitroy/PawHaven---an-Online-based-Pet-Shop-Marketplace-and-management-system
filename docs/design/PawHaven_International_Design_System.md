# PawHaven International Design System — Foundation Proposal

**Status:** Proposed audit baseline; no application styles or behavior changed.  
**Brand goal:** Premium, warm, trustworthy, modern and accessible. Keep one PawHaven identity, but avoid forcing commerce, adoption/rescue and educational content into one page template.

## Brand foundations

Use the supplied values as the initial palette and current app tokens as implementation evidence:

| Token | Initial value | Intended semantic use |
|---|---|---|
| Forest Green | `#245C45` | Primary action, links, selected navigation and brand emphasis |
| Deep Green | `#173F32` | High-emphasis/inverse surfaces, strong headings and dark brand areas |
| Warm Ivory | `#FAF8F2` | Warm page canvas and low-contrast brand surfaces |
| Soft Coral | `#F28C75` | Restrained accent, highlights and decorative/emphasis surfaces |
| Dark Text | `#202820` | Primary text |
| Muted Text | `#69736B` | Secondary text, metadata and supporting labels |
| White | `#FFFFFF` | Raised/card surfaces and inverse text where contrast is validated |

The current CSS already defines these colors as `--color-brand-*`; preserve and reconcile those tokens rather than reapplying a duplicate palette. Do not assume coral is suitable for small text or that every green/ivory pairing meets contrast requirements. Validate every semantic foreground/background pair against WCAG 2.2 AA (4.5:1 normal text; 3:1 large text and non-text UI where applicable).

## Semantic token contract

Expose design meaning rather than use raw palette values throughout Blade:

- `color.text.primary`, `color.text.secondary`, `color.text.inverse`, `color.text.link`
- `color.surface.page`, `color.surface.raised`, `color.surface.subtle`, `color.surface.inverse`
- `color.border.default`, `color.border.strong`, `color.focus.ring`
- `color.action.primary.{default,hover,active,disabled}`, `color.action.secondary.*`
- `color.status.{success,warning,error,info}.{foreground,background,border}`
- `color.adoption.trust` and `color.shop.promotion` only where distinct semantics need a separate approved token

Map those semantic roles to the palette in one theme layer. Keep text/background pairings documented and contrast-tested. Avoid direct hex values in templates except an intentional, documented data visualization/photo treatment.

## Type and spacing

- **Family:** Instrument Sans, already bundled locally. Keep one UI family until international glyph/locale coverage is verified; include a tested system fallback stack.
- **Scale:** semantic roles for display, page title, section title, card title, body, supporting text, label and caption. Use a restrained modular scale and comfortable line-height; do not set arbitrary one-off sizes in each view.
- **Body:** target readable 16px-equivalent body sizing, generous line-height, and constrained long-form measure. Validate zoom/reflow and translated text expansion.
- **Spacing:** 4px base increments; favor an 8px rhythm with named steps such as 4, 8, 12, 16, 24, 32, 40, 48 and 64.
- **Radii:** 8px control/small-card, 12px common card, 16px prominent panel; avoid excessive pill-shaped containers outside a clear interaction.
- **Elevation:** restrained `shadow-sm` for controls/cards and one stronger overlay/elevated-menu level. Use border and surface tone before adding shadows.

## Components and states

Every shared component needs explicit default, hover, active, keyboard-focus, disabled, loading, success and error behavior as relevant. Use visible focus rings; associate labels/help/errors with form controls; preserve server validation and accessible announcements. Respect reduced-motion preference and avoid decorative motion on data/status changes.

Core shared components: site header and role-aware nav, breadcrumbs, buttons/links, form fields, alerts/toasts, pagination, badges/status chips, cards, dialogs, tables, empty states, skeleton/loading states and image placeholders. Design touch targets for comfortable use (aim for 44×44 CSS px where layout permits); never rely on color alone for meaning.

## Three intentionally different experience families

### 1. Shop and pet marketplace

Prioritize discovery and comparison: clear search/filter/sort, category context, seller/source identity, image crop consistency, price/currency, stock or listing status, delivery/fulfillment and truthful cart/request states. Separate shop product cards from animal listings. Ensure direct-request paths are not visually presented as a completed payment checkout.

### 2. Adoption and rescue

Prioritize the animal profile, humane imagery, location/context, trust/safety information, availability/status, clear inquiry expectations, privacy-aware contact and next-step guidance. Use a distinct profile and application/inquiry treatment rather than a product card with a different label. Do not invent verification badges or moderation promises until the underlying capability exists.

### 3. Educational content

Use a reading-first template with stable heading hierarchy, accessible long-form width, optional table of contents, citations/updated date and meaningful related links. Product recommendations can be contextual and subordinate; avoid turning every article into a catalog grid.

All three share PawHaven's logo, core typography, colors, navigation behavior and accessible primitives.

## Responsive and international behavior

Use content-driven layouts and breakpoints that are tested at 320, 360, 375, 390, 768, 1024 and 1440 CSS px, plus 200% zoom. The current header overflows around the desktop-nav activation point; do not ship a new breakpoint without an intermediate-width regression test. Support text expansion, locale-specific number/date/address formats, RTL where required, translated accessible names, currency precision and non-US postal/phone formats. Locale, initial launch market, currencies, tax, shipping, legal text and payment provider are **product decisions**, not design-token assumptions.

## Asset and imagery rules

Prefer optimized, locally controlled responsive images for production-critical hero/content assets, with defined aspect-ratio containers and responsive `srcset`/sizes where supported. Use `loading=lazy` below the fold and avoid lazy-loading the true LCP image. Keep meaningful alt text and decorative images empty-alt. Audit logo variants, dimensions and the current PawHeaven/PawHaven spelling before producing replacements. Any remote image provider needs privacy, reliability, licensing and fallback review.

## Adoption and acceptance

1. Consolidate current brand values into semantic CSS tokens in `resources/css/app.css`; migrate hard-coded template colors in measured, reviewable increments.
2. Validate every token contrast pair; record outcomes before component implementation.
3. Review Instrument Sans coverage/licensing/weights and avoid duplicate font requests.
4. Capture desktop, tablet, 320–390px mobile, keyboard, screen-reader and 200% zoom baselines for the three page families.
5. Treat token or component changes as visual behavior changes; test representative public/auth/customer/seller/admin pages and retain existing routes/roles.

See the [UI/UX audit](../audit/PawHaven_UI_UX_Audit.md) and [redesign roadmap](PawHaven_Redesign_Roadmap.md).
