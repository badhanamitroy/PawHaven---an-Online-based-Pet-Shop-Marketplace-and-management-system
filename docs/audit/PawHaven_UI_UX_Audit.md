# PawHaven UI/UX Audit

**Audit date:** 2026-11-07  
**Observed site:** Local browser at `http://127.0.0.1:8000/`. Findings distinguish rendered behavior from code-based expectations.

## Reference review

The requested references were used for high-level patterns, not visual copying. Readable pages were fetched from [AmarPet](https://amarpet.com/), [PET.COM.BD](https://pet.com.bd/), [Pet Zone BD](https://petzonebd.com/), and [zooplus](https://www.zooplus.com/). Petco and Pet Supermarket returned HTTP 403 to the research fetcher, so their current pages were not independently inspected.

Useful transferable patterns: pet-type/category entry points and strong product taxonomy (the Bangladesh pet-store references); clear merchandising category tiles and purposeful promotional grouping (zooplus); consistent brand shell and legible product hierarchy. PawHaven should retain its own identity and give commerce, adoption/rescue, and learning distinct task-oriented layouts.

## Current experience

### Homepage

**Verified:** The page is a long, multi-domain landing page with sections for pet categories, featured pet listings, product cards, adoption/rescue highlights, breeds, how-it-works and trust/community. The hero has a primary “Explore Pets” path and shop/adoption CTAs. Header links are mostly in-page anchors (`#find-pets`, `#pet-shop`, `#adoption-rescue`, `#breeds`, `#community`); the cart icon also points to `#pet-shop`, not a cart.

**Strengths:** One recognizable green brand palette; Instrument Sans is used; an H1 and nested section headings exist; the home data is server-rendered; images inspected had alternative text; the homepage's tested buttons had a visible or accessible label. Product cards are not the same content as pet/adoption cards.

**Issues:** The browser title and rendered logo spell the name “PawHeaven” while the project/default settings use “PawHaven.” Homepage discovery currently carries multiple user intents in one long scroll; the shop is only featured items rather than a complete catalog. At the ~772 CSS px desktop-nav breakpoint, the header's login/sign-up group overflows the page by roughly 95 px; “Sign Up” is clipped in the measured harness. The green/coral values are partly tokenized in `resources/css/app.css`, but Blade templates also contain literal color values and one-off sizes.

### Marketplace browse and detail

**Verified:** `/listings` renders a distinct browse page with category, breed, type, keyword and sort filters. The backend supports pagination, latest and price sorting. Detail loads a seller and related listings. Filter labels, keyboard behavior, empty-results states and narrow mobile form interactions were not fully exercised in this audit.

### Shop/product journey

The homepage shows featured shop products and category labels, but there is no dedicated public product listing/detail/cart route. The cart affordance communicates “upcoming” behavior. The order form is a direct buy request, not the interaction model customers usually expect from cart/checkout; product cards should not imply a completed checkout until the workflow is designed.

### Adoption/rescue and education

Adoption/rescue is represented as a listing type and homepage section, with a customer inquiry path. It needs a more trust-first, humane flow distinct from purchase cards: animal profile facts, source/safety indicators, inquiry expectations, status and next steps. General rescue-community posts, comments and user reports do not exist. Educational content has no dedicated reading route, editorial template or navigation path.

### Customer/seller/admin workspaces

Authenticated customer and seller dashboards use separate data/workflows but share the app/Flux shell. Seller navigation covers listings and requests; admin navigation is grouped today as “Overview,” “My Website,” and “My Business,” with a long list of individual destinations. Admin list pages are functional but the navigation model is not yet the proposed main tab → subtab → workspace hierarchy. Role distinctions and the admin-only sections are present in code; actual live workspace rendering was not tested with credentials.

## Accessibility and responsive evidence

- At the initial 617×408 browser viewport, the document did not horizontally overflow. At the desktop navigation activation point (~772 CSS px as reported by this browser harness), the page width exceeded its visible width; responsive sign-off should cover the breakpoint transition rather than only common phone/desktop sizes.
- Homepage inspection found no `<img>` without `alt` and no unlabeled empty `<button>` among the buttons checked. This is a limited static DOM check, not a WCAG audit.
- Heading structure and primary navigation labels were present. Keyboard-only operation, focus order, focus visibility, color contrast, screen-reader announcements, forms/error association, reduced motion and zoom to 200% were not verified.
- Loading/empty/error/success states exist in parts of admin/auth workflows, but visual consistency and assistive announcements across all flows remain unverified.

## Design direction

Keep the initial palette and Instrument Sans as a starting point, not a blanket override. Define semantic tokens and shared interaction rules, then create three page families:

1. **Marketplace/shop:** task-first search/filter/sort, product comparison, explicit availability/pricing, honest cart/order states.
2. **Adoption/rescue:** profile and trust context first, clear inquiry/status steps, moderation/reporting controls when those capabilities are approved.
3. **Education:** readable long-form layout, clear topic hierarchy and references, no forced product-grid presentation.

All families should share PawHaven identity, accessible navigation, typography and core controls. See [International Design System](../design/PawHaven_International_Design_System.md) and [Redesign Roadmap](../design/PawHaven_Redesign_Roadmap.md) for semantic tokens, information architecture and acceptance gates.
