<?php
/**
 * Logo list — "as seen in" brand strip.
 *
 * Each item is { imageUrl, alt }, set directly in the template file.
 *
 * @var array $attributes
 */
$heading = $attributes['heading'] ?? '';
$logos   = is_array( $attributes['logos'] ?? null ) ? $attributes['logos'] : [];

if ( empty( $logos ) ) {
	return;
}
?>
<section <?php echo agentic_section_classes( 'logo-list' ); ?>>
	<?php if ( $heading ) : ?>
		<p class="agentic-logo-list__heading"><?php echo esc_html( $heading ); ?></p>
	<?php endif; ?>

	<ul class="agentic-logo-list__row">
		<?php foreach ( $logos as $logo ) : ?>
			<?php
			$image = $logo['imageUrl'] ?? '';
			$alt   = $logo['alt'] ?? '';
			if ( ! $image ) {
				continue;
			}
			?>
			<li class="agentic-logo-list__item">
				<img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $alt ); ?>" loading="lazy" decoding="async" />
			</li>
		<?php endforeach; ?>
	</ul>
</section>
