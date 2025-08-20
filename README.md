# Woo Laser Photo Mockup

## Overview
Woo Laser Photo Mockup is a custom WooCommerce plugin that enables customers to upload and customize photos for **laser-engraved wooden mockups**. It supports per-variation mockup areas, mandatory uploads, live previews, and persistence through **cart → checkout → order → admin**. Admins can later manage and purge uploads securely.

---

## Key Features
- **Variation-specific mockups**
  - Base image (wood blank) per variation.
  - Optional mask PNG with alpha channel.
  - Placement bounds (x, y, width, height, rotation).
  - Aspect ratio + minimum resolution requirement.
  - DPI setting for high-quality composites.

- **Frontend (Customer)**
  - Mandatory image upload (JPEG/PNG/WebP).
  - Interactive canvas editor: move/scale/rotate image inside bounds.
  - Cropping locked to admin-defined aspect ratio.
  - Live preview with base + uploaded photo clipped to mockup area.
  - “Add to Cart” disabled until valid upload + fit.

- **Persistence**
  - Server generates composite + thumbnail on add-to-cart.
  - All images and transform JSON stored in order meta.
  - Preview visible in cart, checkout, order details, and emails.

- **Admin (Backend)**
  - Variation editor: base image, mask with preview, placeholders, removal, and placement editor (drag/resize box).
  - Configure bounds, aspect ratio, min resolution, DPI.
  - Order view: preview thumbnail, open full image, re-render, purge uploads.
  - Auto-purge files N days after order is marked completed.

- **Security**
  - Strict file validation (`finfo`, `getimagesize`).
  - Re-encode images, strip EXIF/GPS metadata.
  - Randomized filenames, SHA-256 integrity checks.
  - Nonces + capability checks on all actions.
  - Private storage with signed URLs or handler endpoints.

---

## Data Model

### Variation Meta
- `_llp_base_image_id`
- `_llp_mask_image_id`
- `_llp_bounds` (JSON)
- `_llp_aspect_ratio` (string)
- `_llp_min_resolution` (JSON)
- `_llp_output_dpi` (int)

### Order/Cart Item Meta
- `_llp_asset_id` (UUID v4)
- `_llp_transform` (JSON)
- `_llp_original_url`
- `_llp_composite_url`
- `_llp_thumb_url`
- `_llp_original_sha256`
- `_llp_processor`
- `_llp_variation_snapshot` (JSON)

### Global Options
- `llp_settings` array (allowed mimes, max file size, retention days, storage mode, etc.)

---

## File Structure
woo-laser-photo-mockup/
woo-laser-photo-mockup.php
includes/
class-llp-plugin.php
class-llp-settings.php
class-llp-variation-fields.php
class-llp-frontend.php
class-llp-renderer.php
class-llp-rest.php
class-llp-order.php
class-llp-storage.php
class-llp-security.php
class-llp-cron.php
traits/trait-singleton.php
templates/
single-product/customizer.php
emails/line-item-preview.php
assets/
css/frontend.css
css/admin.css
js/frontend.js
js/admin-variation.js
img/admin-icons.svg
languages/
llp-en_US.po
uninstall.php
readme.txt


## Hooks

### Product & Cart
- `woocommerce_product_after_variable_attributes` → render variation fields.
- `woocommerce_save_product_variation` → save fields.
- `woocommerce_before_add_to_cart_button` → render customizer.
- `woocommerce_add_to_cart_validation` → block add-to-cart without valid upload.
- `woocommerce_add_cart_item_data` → attach LLP meta.
- `woocommerce_get_item_data` → show thumbnail in cart/checkout.
- `woocommerce_get_cart_item_from_session` → restore LLP data.

### Order
- `woocommerce_checkout_create_order_line_item` → persist LLP meta.
- `woocommerce_email_after_order_table` → embed thumbnails.

### REST
- `/wp-json/llp/v1/upload` → temp upload.
- `/wp-json/llp/v1/finalize` → generate composite.
- `/wp-json/llp/v1/file/...` → serve with signed token.
- `/wp-json/llp/v1/order/{id}/purge` → purge assets.
- `/wp-json/llp/v1/order/{id}/rerender` → re-render.

### Cron
- Daily purge of expired assets.

---

## Image Rendering Pipeline
1. Validate + re-encode user upload.
2. Auto-orient and strip metadata.
3. Apply crop + scale + rotation as per transform JSON.
4. Composite into base image with optional mask.
5. Save **composite.png** and **thumb.jpg** into asset folder.

---

## Storage
/wp-content/uploads/llp/
/private/{asset_id}/
original.png
composite.png
thumb.jpg
meta.json


- Private mode blocks direct access.
- Signed URLs (HMAC + expiry) or capability checks.

---

## Security
- Nonces for all AJAX/REST calls.
- `manage_woocommerce` required for variation settings.
- `edit_shop_orders` required for admin purge/re-render.
- Only `jpg`, `jpeg`, `png`, `webp` allowed.
- Max file size configurable (default 15 MB).

---

## Uninstall
- Removes plugin options.
- Uploads directory not deleted unless “Delete all assets on uninstall” option is enabled.

---

## Acceptance Criteria
- Add-to-cart blocked until valid upload finalized.
- Per-variation bounds respected in editor + server composite.
- Thumbnail shown in cart, checkout, order, and email.
- Admin can purge uploads; URLs expire.
- Auto-purge runs via WP-Cron after order completion + N days.
- All images sanitized, metadata stripped, GPS removed.

---

## Manual Testing

1. **Customer upload & finalize**
   - Log in as a user with the `customer` role.
   - Obtain a REST nonce from the storefront (e.g., `wpApiSettings.nonce`).
   - `POST /wp-json/llp/v1/upload` with the nonce and a valid image file.
   - `POST /wp-json/llp/v1/finalize` using the returned `asset_id` and a variation ID.
   - Both requests should return `200` responses.
2. **Unauthorized requests**
   - Repeat the above steps while logged out or without a nonce.
   - Requests to `/upload` or `/finalize` should return `401`/`403` errors.

---

## License
MIT – Internal project for WooCommerce customization.
