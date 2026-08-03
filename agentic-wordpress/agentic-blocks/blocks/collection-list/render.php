<?php
/**
 * Collection list — row of circular product category icons ("Shop by
 * category" / "Our Collections"), the main storefront navigation section.
 *
 * Attribute-driven: each item is { title, imageUrl, url }, set directly in
 * the template file. Pass a real `taxonomy-product_cat` archive URL as `url`
 * for each entry once categories exist.
 *
 * @var array $attributes
 */
$heading     = $attributes['heading'] ?? '';
$cta_text    = $attributes['ctaText'] ?? '';
$cta_url     = $attributes['ctaUrl'] ?? '';
$columns     = max( 1, min( 6, (int) ( $attributes['columns'] ?? 4 ) ) );
$collections = is_array( $attributes['collections'] ?? null ) ? $attributes['collections'] : [];

if ( empty( $collections ) ) {
	return;
}
?>
<section <?php echo agentic_section_classes( 'collection-list' ); ?>>
	<?php if ( $heading || ( $cta_text && $cta_url ) ) : ?>
		<div class="agentic-collection-list__header">
			<?php if ( $heading ) : ?>
				<h2 class="agentic-collection-list__heading"><?php echo esc_html( $heading ); ?></h2>
			<?php endif; ?>
			<?php if ( $cta_text && $cta_url ) : ?>
				<a class="agentic-collection-list__cta" href="<?php echo esc_url( $cta_url ); ?>">
					<?php echo esc_html( $cta_text ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<ul class="agentic-collection-list__grid" style="--agentic-columns:<?php echo esc_attr( $columns ); ?>">
		<?php foreach ( $collections as $item ) : ?>
			<?php
			$title = $item['title'] ?? '';
			$url   = $item['url'] ?? '';
			$image = $item['imageUrl'] ?? '';
			if ( ! $title || ! $url ) {
				continue;
			}
			?>
			<li class="agentic-collection-list__item">
				<a class="agentic-collection-list__link" href="<?php echo esc_url( $url ); ?>">
					<span class="agentic-collection-list__media">
						<?php if ( $image ) : ?>
							<img src="<?php echo esc_url( $image ); ?>" alt="" loading="lazy" decoding="async" />
						<?php endif; ?>
					</span>
					<span class="agentic-collection-list__title"><?php echo esc_html( $title ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
