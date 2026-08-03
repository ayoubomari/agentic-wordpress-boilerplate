<?php
/**
 * Payment-method badge row.
 *
 * Icons come from agentic_payment_icon_svg() in agentic-blocks.php —
 * simplified logo-style marks, not traced official artwork.
 *
 * @var array $attributes
 */
$methods = $attributes['methods'] ?? [ 'visa', 'mastercard', 'amex', 'paypal' ];
?>
<div <?php echo agentic_section_classes( 'payment-badges' ); ?>>
	<?php foreach ( $methods as $method ) :
		$icon = agentic_payment_icon_svg( $method );
		if ( ! $icon ) {
			continue;
		}
		?>
		<span class="agentic-payment-badges__icon"><?php echo $icon; // phpcs:ignore -- built from a fixed internal icon map, never user input. ?></span>
	<?php endforeach; ?>
</div>
