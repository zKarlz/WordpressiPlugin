<?php
/**
 * Email/order item preview and meta output.
 *
 * @var string $thumb_url      Thumbnail URL.
 * @var string $asset_id       Asset identifier.
 * @var string $composite_url  Composite image URL.
 * @var string $original_url   Original upload URL.
 * @var string $transform_json Transform parameters JSON.
 */
?>
<?php if ( ! empty( $thumb_url ) ) : ?>
    <p><img src="<?php echo esc_url( $thumb_url ); ?>" alt="" style="max-width:80px;" /></p>
<?php endif; ?>
<?php if ( ! empty( $asset_id ) ) : ?>
    <p><?php printf( esc_html__( 'Asset ID: %s', 'llp' ), esc_html( $asset_id ) ); ?></p>
<?php endif; ?>
<?php if ( ! empty( $original_url ) || ! empty( $composite_url ) ) : ?>
    <p>
        <?php if ( ! empty( $original_url ) ) : ?>
            <a href="<?php echo esc_url( $original_url ); ?>"><?php esc_html_e( 'Original', 'llp' ); ?></a>
        <?php endif; ?>
        <?php if ( ! empty( $original_url ) && ! empty( $composite_url ) ) : ?>
            |
        <?php endif; ?>
        <?php if ( ! empty( $composite_url ) ) : ?>
            <a href="<?php echo esc_url( $composite_url ); ?>"><?php esc_html_e( 'Composite', 'llp' ); ?></a>
        <?php endif; ?>
    </p>
<?php endif; ?>
<?php if ( ! empty( $transform_json ) ) : ?>
    <p><?php esc_html_e( 'Transform JSON:', 'llp' ); ?></p>
    <pre style="white-space:pre-wrap;"><?php echo esc_html( $transform_json ); ?></pre>
<?php endif; ?>
