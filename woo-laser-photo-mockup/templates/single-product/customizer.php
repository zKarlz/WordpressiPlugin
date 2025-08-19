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
        <canvas id="llp-canvas"></canvas>
        <p><button type="button" id="llp-finalize" class="button">Finalize</button></p>
    </div>
    <div id="llp-preview" class="llp-preview" style="display:none;">
        <img src="" alt="" />
    </div>
    <input type="hidden" name="llp_asset_id" id="llp-asset-id" />
    <input type="hidden" name="llp_thumb_url" id="llp-thumb-url" />
    <input type="hidden" name="llp_transform" id="llp-transform" />
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.2.4/fabric.min.js" integrity="sha512-4xXHzkwmo7aX6ixkmKuuNHYsYvwdivEafgAvFp8ZUBKbjDg7sWXBJgp7wa9u0edPFsKnz03Wx/ju0RduCMsZ/Q==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
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
