<?php
/**
 * Latest posts — dynamic grid of Journal entries.
 *
 * Queries real published posts via WP_Query rather than taking content
 * through attributes like most blocks in this library (see collection-list,
 * cta-cards) — the whole point of this section is that it always reflects
 * whatever the Journal actually has, the same reasoning agentic/featured-
 * collection uses wc_get_products() for real product data instead of
 * hardcoded cards.
 *
 * With `relatedToCurrent`, this doubles as the "Related Posts" section on
 * single.html: it reads the currently-viewed post straight from the loop
 * context WordPress already sets up for a singular template (the same
 * mechanism post-title/post-content rely on with no attributes of their
 * own) and queries posts sharing one of its categories, excluding itself.
 * If that turns up fewer than `count` — a post in a one-off category, or a
 * thin catalog — the remainder is backfilled from the site's latest posts
 * (still excluding the current one and anything already picked) so the
 * grid never renders sparse or empty just because a category is thin.
 *
 * @var array $attributes
 */
$heading  = $attributes['heading'] ?? '';
$cta_text = $attributes['ctaText'] ?? '';
$cta_url  = $attributes['ctaUrl'] ?? '';
$count    = max( 1, min( 6, (int) ( $attributes['count'] ?? 3 ) ) );
$related  = ! empty( $attributes['relatedToCurrent'] );

$exclude_id   = ( $related && is_singular( 'post' ) ) ? get_the_ID() : 0;
$category_ids = $exclude_id ? wp_get_post_categories( $exclude_id ) : [];

$posts = [];
if ( $category_ids ) {
	$related_query = new WP_Query(
		[
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $count,
			'post__not_in'        => [ $exclude_id ],
			'category__in'        => $category_ids,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		]
	);
	$posts = $related_query->posts;
}

$remaining = $count - count( $posts );
if ( $remaining > 0 ) {
	$fallback_query = new WP_Query(
		[
			'post_type'           => 'post',
			'post_status'         => 'publish',
			'posts_per_page'      => $remaining,
			'post__not_in'        => array_merge( [ $exclude_id ], wp_list_pluck( $posts, 'ID' ) ),
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		]
	);
	$posts = array_merge( $posts, $fallback_query->posts );
}

if ( empty( $posts ) ) {
	return;
}
?>
<section <?php echo agentic_section_classes( 'latest-posts', $related ? [ 'agentic-latest-posts--related' ] : [] ); ?>>
	<?php if ( $heading || ( $cta_text && $cta_url ) ) : ?>
		<div class="agentic-latest-posts__header">
			<?php if ( $heading ) : ?>
				<h2 class="agentic-latest-posts__heading"><?php echo esc_html( $heading ); ?></h2>
			<?php endif; ?>
			<?php if ( $cta_text && $cta_url ) : ?>
				<a class="agentic-latest-posts__cta" href="<?php echo esc_url( $cta_url ); ?>">
					<?php echo esc_html( $cta_text ); ?>
				</a>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<ul class="agentic-latest-posts__grid" style="--agentic-columns:<?php echo esc_attr( min( 3, count( $posts ) ) ); ?>">
		<?php
		global $post;
		foreach ( $posts as $related_post ) :
			$post = $related_post;
			setup_postdata( $post );
			?>
			<li class="agentic-latest-posts__item">
				<a class="agentic-latest-posts__link" href="<?php the_permalink(); ?>">
					<span class="agentic-latest-posts__media">
						<?php if ( has_post_thumbnail() ) : ?>
							<?php the_post_thumbnail( 'medium_large', [ 'loading' => 'lazy', 'decoding' => 'async' ] ); ?>
						<?php else : ?>
							<span class="agentic-latest-posts__placeholder" aria-hidden="true"></span>
						<?php endif; ?>
					</span>
					<span class="agentic-latest-posts__date"><?php echo esc_html( get_the_date() ); ?></span>
					<span class="agentic-latest-posts__title"><?php the_title(); ?></span>
					<span class="agentic-latest-posts__excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt(), 20 ) ); ?></span>
					<span class="agentic-latest-posts__readmore"><?php esc_html_e( 'Read More', 'agentic' ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</section>
<?php
wp_reset_postdata();
