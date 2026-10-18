# Label configurator → product page, cart & checkout integration
# (+ in-house print-production pipeline + launch marketing/page plan
# + saved designs / customer accounts)

## Status: requirements-gathering checkpoint — not a final plan, nothing built

Research is complete (see below) and several key business decisions are
locked in, but the actual implementation design (Phase 2 of the standard
planning process — concrete file-by-file changes) has **not been done
yet**, and a few open questions remain (both listed below). Pete asked to
save this and come back to it in about a week, once the configurator
itself is "built and flawless." **Nothing in this plan has been
implemented** — no files changed, no product created, no code written.

## Context (why this work)

The `rrt-label-configurator` plugin (built earlier this same session) is a
fully working self-service label designer, live at `[rrt_label_configurator]`
on the `/co-branding/` page — a customer can upload a logo, add text, and
now also drop in pre-made art (27-piece Noto Emoji + 3 flags library) onto
a live preview of the real 8oz/4oz label template. But today that tool
only **submits a design request by email to Pete** — there's no checkout,
no payment, no WooCommerce product behind it at all. Pete now wants the
configurator wired into the real purchase flow: a customer designs their
label AND pays for it, in one flow, on the product page / in the cart —
not a design-then-wait-for-Pete's-email process.

**Minimum quantity rule — CORRECTED 2026-10-18, size-specific**: Pete's
first phrasing ("a min of 2") got clarified via AskUserQuestion as "2+
total custom items, designs can differ" (not a per-product qty≥2 rule) —
**that answer was then corrected by Pete directly**, mid-turn, to the
real rule:
- **8oz custom label: minimum 2**
- **4oz custom label: minimum 6**

Still unconfirmed: does "designs can differ" still apply WITHIN each size
bucket (2 different 8oz designs OK, but 8oz and 4oz minimums don't mix/
combine toward each other), or did that nuance stop applying now that the
rule is size-specific? Given the existing 4oz line already sells as "x2
bundles," a min-of-6 is a notably bigger ask than 8oz's min-of-2 — worth
double-checking this number is intentional, not another "2" slipping in,
before building cart-validation logic around it.

## Other decisions confirmed via AskUserQuestion (2026-10-18)

- **Product structure**: build NEW dedicated product(s) for "build your
  own custom label toffee" — do NOT bolt this onto the 9 existing flavor
  products. Still open: one product (size chosen inside the configurator,
  which already has its own 8oz/4oz selector) vs. two separate products
  (one per size) — not yet decided, needs design-phase reasoning given
  the now-confirmed per-size minimums above (two separate products, each
  with its own min-qty rule, may now be the cleaner fit).
- **Manual review**: "No, straight to fulfillment" — no pending-approval
  order-hold gate after checkout. Pete still needs the composited design
  delivered to him somehow for production/printing purposes (a
  fulfillment necessity, not a review gate) — design must cover this.

## What's already true (from this session, no further research needed)

- **Configurator's data contract** (`inc/handle-submission.php`,
  `assets/label-configurator.js`): on submit, the JS posts `size`
  (8oz/4oz), `name`/`email`/`phone`/`message`, and a `layers` JSON array
  (logo/art layers with `pos`/`scale`/`art_key` or uploaded file via
  `logo_files[]`; text layers with `pos`/`text`/`font`/`font_size`/
  `color`/`outline`/`shadow`/`shadow_intensity`) to the
  `rrt_label_submit` AJAX action. The backend (`rrt_label_composite()`)
  flattens all of that onto the real label template with GD and emails
  Pete the result — this compositing logic is almost certainly reusable
  as-is for a cart/order flow, just triggered at a different point (add-
  to-cart or checkout) instead of immediately emailing.
- **Nothing WooCommerce-related has been touched yet** by this plugin —
  it's entirely decoupled from the shop today.
- Site stack (confirmed earlier this session): WordPress + WooCommerce +
  Elementor/Elementor Pro, theme is stock `hello-elementor` (no child
  theme). `woocommerce-gateway-stripe` plugin is present in
  `wp-content/plugins/` (not yet confirmed active/configured — in the
  research scope below).

## Research findings (complete, 2026-10-18)

1. **WooCommerce**: active, v11.2.1. No custom mu-plugin or theme-level
   WooCommerce customization layer exists anywhere — confirmed greenfield.
2. **Existing products**: all toffee products are `simple` type (no
   variable products). 8oz boxes ~$22.79-$24.79. 4oz boxes are already
   sold as **pre-packaged "x 2" bundles** (e.g. "4 oz. Original Almond
   Toffee x 2", $27.98) — i.e. the existing pattern for the min-2 problem
   on 4oz is "one SKU = 2 boxes," not "quantity ≥ 2 of one box." No
   existing "custom"/"label" product. **The configurator shortcode isn't
   placed on any page in this DB at all yet** — no `/co-branding/` page
   exists; the plugin is installed/active but has no front-end home yet.
3. **No existing cart/checkout customization anywhere** — every hit for
   `woocommerce_add_cart_item_data` / `woocommerce_get_item_data` /
   `woocommerce_add_to_cart_validation` / `woocommerce_before_calculate_totals`
   is inside WooCommerce core or vendored library code in other plugins,
   never site-specific. `rrt-label-configurator` itself has zero
   WooCommerce references today (pure standalone AJAX-to-email flow). No
   child theme exists, `hello-elementor` is unmodified stock — new
   cart-item-data/pricing/validation code will need to live inside (or
   alongside) `rrt-label-configurator`.
4. **Payment**: `woocommerce-gateway-stripe` is active **with live keys
   configured** (`pk_live_...`/`sk_live_...`, webhook pointed at
   `localhost:8081` in this dev DB copy — will need re-pointing for live),
   ranked first in gateway order, Optimized Checkout (UPE) + card/link
   enabled. PayPal and WooPayments plugins are installed but inactive.
   **This store takes real live Stripe payments already** — any change
   touching checkout needs real care, same as every other "this changes
   something live" moment this project has had.
5. **Product page template**: no Elementor Theme Builder template targets
   `singular/product` or any WC condition, no `woocommerce/` override
   folder in the theme — single-product pages render via **stock
   WooCommerce core templates**. Any product-page UI work goes through
   standard WC action hooks (`woocommerce_before_add_to_cart_button`,
   `woocommerce_single_product_summary`) or a new template override file,
   not an existing Elementor design.
6. **Minimum quantity**: confirmed **fully greenfield** — no plugin, no
   snippet, nothing. WooCommerce has no native per-product min-qty.
   Needs `woocommerce_quantity_input_args` (set the input's min/default)
   + `woocommerce_add_to_cart_validation` (hard server-side enforcement)
   written specifically for this feature.

## Remaining open question (not yet asked)

- **Editing after adding to cart** — can a customer go back and change
  their design before checkout, or is it locked in once added to cart?

## Phase 2: in-house print-production pipeline (plan now, build after the configurator + checkout are solid)

Pete, 2026-10-18: "we'll still have to work out transitioning the files of
the label with the added user made canvas area onto a single label, then
to a print ready file that is 8.5 x 11", so there's 2 for the 8 oz, and 3
for the 4 oz, and we can print inhouse and cut inhouse and wrap the
boxes. This has to be worked out and ready to implement once we get the
user label design module built and flawless." — i.e. build order is
(1) finish/polish the configurator itself [already far along], (2) build
the cart/checkout integration from Phase 1 above, (3) **then** this
print-imposition pipeline. Documented now so it isn't forgotten, not
because it's next up — Pete also said (same message) to save this and
come back to it in about a week.

**The numbers Pete gave match the existing template config exactly** —
confirms this is sound, not a rough guess. From `rrt_label_sizes()` in
`rrt-label-configurator.php`:
- 8oz template: 4.127" × 11" at 300 DPI. 8.5" sheet ÷ 4.127" = 2 fit side
  by side (2 × 4.127" = 8.254", ~0.25" margin) → matches "2 for the 8oz."
- 4oz template: 2.627" × 11" at 300 DPI. 8.5" sheet ÷ 2.627" = 3 fit side
  by side (3 × 2.627" = 7.881", ~0.6" margin) → matches "3 for the 4oz."
- Both templates are already exactly 11" tall — a full sheet-height
  column, zero vertical cutting needed, only side-by-side horizontal
  tiling.

**This almost certainly explains the size-specific minimums** from Phase 1
above: 8oz min 2 = exactly 1 full print sheet; 4oz min 6 = exactly 2 full
sheets (3 per sheet × 2 sheets) — the minimums appear designed so that
even the smallest allowed order prints as whole, non-wasteful sheets.
Worth confirming this reasoning directly with Pete, since it implies
order quantities ABOVE the minimum may also need to stay in clean
multiples (8oz in steps of 2, 4oz in steps of 3) for the same reason —
not yet confirmed, ask when this phase is picked back up.

### Open questions for this phase (no rush — resolve when picked back up)

1. **Mixed designs per sheet**: if one order has 2 different 8oz designs,
   do they share ONE sheet (1 of each design, both slots filled) or does
   each distinct design get its own dedicated sheet (wasting the 2nd
   slot)? Sharing is more paper-efficient and is the likely answer, but
   confirm — this is the core logic of the imposition algorithm.
2. **Cross-order batching**: does sheet-filling happen per order only, or
   does Pete want a daily/batch view combining small orders from
   different customers onto shared sheets to minimize waste? More
   efficient but more complex — probably a v2 enhancement, not needed for
   initial launch.
3. **Output format**: a true "print-ready file" at a fixed 8.5×11" most
   naturally means a **PDF** (what any office printer/print software
   expects), not a flat JPEG. GD (what the plugin uses today for
   compositing) can't produce PDF directly. Two paths: (a) PHP's Imagick
   extension, if enabled on the server, can wrap composited images into a
   PDF; (b) a lightweight pure-PHP PDF library with no external
   dependencies (e.g. FPDF) that places the already-composited label
   JPEGs onto an 8.5×11 PDF page at the correct tiled positions — fits
   this plugin's existing "plain PHP, no build step" philosophy better
   than depending on a possibly-absent server extension. Check Imagick
   availability first (ImageMagick itself is already confirmed installed
   on the dev machine), fall back to bundling FPDF.
4. **Trigger point**: generate the print-ready sheet automatically the
   moment an order is placed (attached alongside the per-label composite
   on the new-order notification), or as an on-demand admin
   action/button Pete triggers when he's ready to run a print batch (e.g.
   once a day)? "We can print inhouse and cut inhouse" sounds like a
   batch production workflow — on-demand/batched is the likely better
   fit, but confirm.
5. **Cut-line guides**: the generated sheet should almost certainly
   include visible crop/cut marks (thin lines or corner marks between the
   2 or 3 label slots) to guide manual in-house cutting — flag as a
   near-certain requirement to build, not really an open question.

## DECIDED 2026-10-18: ship the simple manual-bridge first, not full automation

Asked directly, Pete chose the simpler path: **keep today's working
design → email-Pete flow exactly as it is** (nothing to change there —
it already works), and separately sell a plain WooCommerce product so
customers can pay. Pete manually matches each payment to its emailed
design request for now; full cart/checkout automation (compositing at
add-to-cart time, cart-item-data carrying the design, etc.) is deferred
to later, not needed for v1 launch.

**This shrinks Phase 1 down to one real piece of new code**: per-product
MINIMUM QUANTITY enforcement, since that business rule (8oz min 2, 4oz
min 6 — see above) is still real and still needed regardless of which
checkout approach is used. Concretely:
- Two new simple WooCommerce products Pete creates in wp-admin (not code)
  — e.g. "Custom Label Order — 8oz" and "Custom Label Order — 4oz" — each
  priced however Pete wants, each flagged with a small custom field
  (e.g. `_rrt_min_qty` = 2 or 6) so the enforcement code below knows each
  product's minimum without hardcoding product IDs.
- **BUILT 2026-10-18** (`inc/woocommerce-min-qty.php` — written, linted,
  staged with git, deliberately NOT required/activated by the plugin's
  bootstrap yet, so it has zero effect on the running site until Pete
  creates the two products and flips it on — see the file's own header
  comment for the exact 3-step activation sequence):
  - `woocommerce_quantity_input_args` filter — reads `_rrt_min_qty` off
    the product, sets the quantity input's `min_value`/`input_value`.
  - `woocommerce_add_to_cart_validation` filter — hard server-side
    rejection if the submitted quantity is below that product's minimum.
  - Generic/reusable — reads a `_rrt_min_qty` custom field per product,
    not hardcoded to specific product IDs, so it works for any product
    Pete tags this way, not just the two described here.
- Nothing else about checkout, cart item data, or compositing changes —
  this really is just "add a minimum-quantity rule to two otherwise
  completely normal WooCommerce products."
- The `/co-branding/` configurator page either gets a plain link/note
  pointing customers to the right payment product after they submit
  their design, or Pete handles that connection by hand for now — low
  priority, not core to getting this shippable.

Full automation (fully-automated cart/checkout, moving compositing to
add-to-cart time, cart-item-data carrying the design end-to-end) stays
documented as a future upgrade but is explicitly NOT required for launch.

## Phase 3: launch marketing & web/page changes (not researched or planned yet)

Pete, 2026-10-18: "we'll have to coordinate with a plan to market the
snot outta this, web changes, I need planning, page changes etc." —
getting this feature live isn't just the technical build; it also needs
a plan for how it's marketed/announced and what page changes support
that (e.g. the `/co-branding/` page itself, home page callouts, product
listing pages, any email/social announcement). **Nothing has been
scoped here yet** — this needs its own requirements-gathering pass
(what channels, what pages, timing relative to the technical launch)
once picked back up. Treat as a third, parallel-but-separate workstream
alongside Phase 1 (cart/checkout) and Phase 2 (print production), not
yet started.

## Phase 4: saved designs / customer accounts (not researched or planned yet)

Pete, 2026-10-18: "we'll also need to allow for someone to SAVE their
art/design, create an account, for business needs, ongoing events[s]" —
a business customer who orders the same custom label repeatedly (e.g.
for a recurring event/sponsorship) shouldn't have to redesign from
scratch every time; they should be able to save a design to an account
and reuse/re-order it later. **Nothing has been scoped here yet.**

What's already true that's relevant: WordPress/WooCommerce already have
a full user-account system (WooCommerce's own "My Account" pages, native
customer registration at checkout) — account creation itself is likely
just "turn on WooCommerce's existing registration option," not new code.
The genuinely new piece is **persisting a design**: today, a design only
exists as in-memory JS state in `assets/label-configurator.js` until
submit time — nothing about a design is saved anywhere retrievable after
that (the composited JPEG and any uploaded logo files live in a temp
uploads folder, not tied to any user). Saving a design for later reuse
needs real new storage (most likely a custom post type or user-meta
entry holding the `layers` JSON + size + a preview thumbnail, keyed to
the logged-in user) plus UI for "save this design" / "load a saved
design" on the configurator itself.

Open questions for whenever this is picked back up:
- Is an account REQUIRED to save a design, or can a guest save-and-return
  via an emailed link (no account) too? Pete's phrasing ("create an
  account") suggests account-required, but worth confirming.
- How many saved designs per account — unlimited, or some cap?
- Does "save" happen automatically as they design, or only via an
  explicit "Save Design" action?
- Does a saved design need to track which SIZE (8oz/4oz) it was made
  for, and can it be reused across different order sizes, or is it
  locked to the size it was created under?

## Immediate next step

1. ~~Build the min-qty enforcement code~~ — **done 2026-10-18**
   (`inc/woocommerce-min-qty.php`, inert/not yet activated).
2. Pete creates the two WooCommerce products in wp-admin whenever he's
   ready to actually go live with this (not done by Claude — that's the
   "put it into local" step being deliberately deferred).
3. Confirm the print-production open questions (5 items, Phase 2) — no
   rush, that phase is sequenced after Phase 1 regardless.
4. Scope Phase 3 (marketing/page plan) — ask Pete what channels/pages/
   timing he has in mind; nothing researched yet.
5. Scope Phase 4 (saved designs/accounts) — resolve the open questions
   above with Pete; nothing researched or built yet.
5. Full cart/checkout automation (deferred, see "DECIDED" section) stays
   a documented future upgrade, not needed for launch.
