<?php
/**
 * Frontend customizer template.
 */
?>
<div id="llp-customizer" class="llp-customizer">
    <p>
        <input type="file" id="llp-file" accept="image/*" />
    </p>
    <div id="llp-editor" class="llp-editor" style="display:none;">
        <img id="llp-canvas" src="" alt="" />
        <p><button type="button" id="llp-finalize" class="button">Finalize</button></p>
    </div>
    <div id="llp-preview" class="llp-preview" style="display:none;">
        <img src="" alt="" />
    </div>
    <input type="hidden" name="llp_asset_id" id="llp-asset-id" />
    <input type="hidden" name="llp_thumb_url" id="llp-thumb-url" />
    <input type="hidden" name="llp_transform" id="llp-transform" />
</div>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" integrity="sha512-cyzxRvewl+FOKTtpBzYjW6x6IAYUCZy3sGP40hn+DQkqeluGRCax7qztK2ImL64SA+C7kVWdLI6wvdlStawhyw==" crossorigin="anonymous" referrerpolicy="no-referrer" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js" integrity="sha512-6lplKUSl86rUVprDIjiW8DuOniNX8UDoRATqZSds/7t6zCQZfaCe3e5zcGaQwxa8Kpn5RTM9Fvl3X2lLV4grPQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<?php
// Expose bounds for each variation to JS.
global $product;
$bounds_map = [];
if ( $product && $product->is_type( 'variable' ) ) {
    foreach ( $product->get_children() as $variation_id ) {
        $bounds = get_post_meta( $variation_id, '_llp_bounds', true );
        $bounds_map[ $variation_id ] = $bounds ? json_decode( $bounds, true ) : [];
    }
}
?>
<script>
window.llpBounds = <?php echo wp_json_encode( $bounds_map ); ?>;
</script>
