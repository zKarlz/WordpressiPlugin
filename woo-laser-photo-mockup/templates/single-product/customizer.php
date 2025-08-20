<?php
/**
 * Frontend customizer template.
 */
?>
<div id="llp-customizer" class="llp-customizer">
    <div class="llp-drop-container">
        <label for="llp-file" id="llp-drop-zone" class="llp-drop-zone">
            Drop image or click to upload
            <input type="file" id="llp-file" accept="image/*" style="display:none;" />
        </label>
    </div>
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
