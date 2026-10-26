# Manual steps after a deploy (things a file upload can't do)

This is new/lighter than TheUnframer's equivalent checklist — RedRocksToffee
has no deploy history yet, so this will grow as real post-deploy steps come up.
Seed it with what's already known to matter from this session's own work:

- **`_rrt_min_qty` on the two real WooCommerce products.** Once Pete creates
  "Custom Label Order — 8oz" and "— 4oz" in wp-admin, each needs its own
  `_rrt_min_qty` custom field set (`2` for 8oz, `3` for the 4oz twin-pack —
  **not 6**, per Pete's own correction this session: each SKU = 2 boxes).
  `inc/woocommerce-min-qty.php` is a no-op until these exist; this isn't a
  blocker for deploying the code itself, just a reminder it's still pending.
- **Flush Cache** (GoDaddy dashboard or WordPress toolbar) after every deploy,
  then re-run `4-verify.sh <id> --after-flush --record`.
- Add a line here (and to `probes.txt`) for any new manual step or page that
  should never silently break, as they come up.
