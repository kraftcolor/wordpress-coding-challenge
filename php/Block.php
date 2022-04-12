<?php
/**
 * Block class.
 *
 * @package SiteCounts
 */

namespace XWP\SiteCounts;

use WP_Block;
use WP_Query;

/**
 * The Site Counts dynamic block.
 *
 * Registers and renders the dynamic block.
 */
class Block {

	/**
	 * The Plugin instance.
	 *
	 * @var Plugin
	 */
	protected $plugin;

	/**
	 * Instantiates the class.
	 *
	 * @param Plugin $plugin The plugin object.
	 */
	public function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Adds the action to register the block.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'init', [ $this, 'register_block' ] );

		// Clear cache when a post is updated, post status changes, category or tag updated.
		add_action( 'edit_term', [ $this, 'flush_caches' ], 10, 3 );
		add_action( 'edit_category', [ $this, 'flush_caches' ], 10, 1 );
		add_action( 'edit_post', [ $this, 'flush_caches' ], 10, 1 );
	}

	/**
	 * Flush cached HTML markup.
	 * HTML markup cached in render_posts_with_tag_cat() method.
	 *
	 * @return void
	 */
	public function flush_caches() {
		wp_cache_delete( 'render_posts_with_tag_cat', 'site-counts' );
	}

	/**
	 * Registers the block.
	 */
	public function register_block() {
		register_block_type_from_metadata(
			$this->plugin->dir(),
			[
				'render_callback' => [ $this, 'render_callback' ],
			]
		);
	}

	/**
	 * Renders the block.
	 *
	 * @param array    $attributes The attributes for the block.
	 * @param string   $content    The block content, if any.
	 * @param WP_Block $block      The instance of this block.
	 * @return string The markup of the block.
	 */
	public function render_callback( $attributes, $content, $block ) {
		$post_types = get_post_types( [ 'public' => true ] );
		$class_name = $attributes['className'] ?? false;
		ob_start();

		?>
		<div class="<?php echo esc_attr( $class_name ); ?>">
			<h2><?php esc_html_e( 'Post Counts', 'site-counts' ); ?></h2>
			<ul>
				<?php 
				foreach ( $post_types as $post_type_slug ) :
					$post_type_object = get_post_type_object( $post_type_slug );
					$post_count       = wp_count_posts( $post_type_slug ); 
					?>
						<li>
						<?php 
							echo sprintf( 
								/* translators: 1: post count, 2: post type singular name, 3: Post type plural name */
								_n( 'There is %1$s %2$s', 'There are %1$s %3$s', $post_count->publish, 'site-counts' ),
								number_format_i18n( $post_count->publish ),
								$post_type_object->labels->singular_name,
								$post_type_object->labels->name
							);

						?>
						</li>
				<?php endforeach; ?>
			</ul>

			<p>
				<?php
					echo sprintf(
						/* translators: 1: current post id */
						__( 'The current post ID is %1$s.', 'site-counts' ),
						get_the_ID()
					); 
				?>
			</p>

			<?php echo $this->render_posts_with_tag_cat(); ?>
		</div>
		<?php

		return ob_get_clean();
	}

	/**
	 * Render 5 posts with tag of `foo` and category of `bar`.
	 * Caches the rendered HTML markip into object cache for 24 horus.
	 *
	 * @return string HTML markup
	 */
	public function render_posts_with_tag_cat() {
		$cached = wp_cache_get( 'render_posts_with_tag_cat', 'site-counts' );

		if ( $cached !== false ) {
			return $cached;
		}

		$query = new WP_Query(
			[
				'no_found_rows'  => true,
				'fields'         => 'ids',
				'post_type'      => [ 'post', 'page' ],
				'post_status'    => 'any',
				'date_query'     => [
					[
						'hour'    => 9,
						'compare' => '>=',
					],
					[
						'hour'    => 17,
						'compare' => '<=',
					],
				],
				'tag'            => 'foo',
				'category_name'  => 'baz',
				'posts_per_page' => 6,
			] 
		);

		ob_start();

		if ( $query->have_posts() ) : 
			$posts      = $query->posts;
			$skip_index = array_search( get_the_ID(), $posts );

			if ( $skip_index !== false ) :
				unset( $posts[ $skip_index ] );
			endif; 
			?>

			<h2><?php _e( '5 posts with the tag of foo and the category of baz', 'site-counts' ); ?></h2>
			<ul>
				<?php foreach ( array_slice( $posts, 0, 5 ) as $post ) : ?>
					<li><?php echo get_the_title( $post ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php 
		endif;

		$html_content = ob_get_clean();
		wp_cache_set( 'render_posts_with_tag_cat', $html_content, 'site-counts', DAY_IN_SECONDS );

		return $html_content;
	}

}
