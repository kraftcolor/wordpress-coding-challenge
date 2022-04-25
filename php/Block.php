<?php
/**
 * Block class.
 *
 * @package SiteCounts
 */

namespace XWP\SiteCounts;

use WP_Block;
use WP_Query;
use WP_Term;

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
							echo esc_html(
								sprintf( 
									/* translators: %1$d: post count. %2$s: post type singular name. %3$s: Post type plural name. */
									_n( 'There is %1$d %2$s', 'There are %1$d %3$s', $post_count->publish, 'site-counts' ), // phpcs:ignore WordPress.WP.I18n.MismatchedPlaceholders -- Singular and plural placeholders are intentionally different.
									number_format_i18n( $post_count->publish ),
									$post_type_object->labels->singular_name,
									$post_type_object->labels->name
								)
							);

						?>
						</li>
				<?php endforeach; ?>
			</ul>

			<p>
				<?php
					echo esc_html(
						sprintf(
						/* translators: 1: current post id */
							__( 'The current post ID is %1$d.', 'site-counts' ),
							get_the_ID()
						) 
					); 
				?>
			</p>

			<?php echo wp_kses_post( $this->render_posts_with_tag_cat() ); ?>
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
		$tag       = get_term_by( 'name', 'foo', 'post_tag' );
		$cat       = get_term_by( 'name', 'baz', 'category' );
		$tag_count = 0;
		$cat_count = 0;

		if ( $tag instanceof WP_Term ) {
			$tag_count = $tag->count;
		}

		if ( $cat instanceof WP_Term ) {
			$cat_count = $cat->count;
		}

		$cached = wp_cache_get( 'site_counts_block_render_posts_with_tag_cat_' . $tag_count . $cat_count, 'site-counts' );

		if ( false !== $cached ) {
			return $cached;
		}

		$query = new WP_Query(
			[
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'fields'                 => 'ids',
				'post_type'              => [ 'post', 'page' ],
				'post_status'            => 'any',
				'date_query'             => [
					[
						'hour'    => 9,
						'compare' => '>=',
					],
					[
						'hour'    => 17,
						'compare' => '<=',
					],
				],
				'tag'                   => 'foo',
				'category_name'         => 'baz',
				'posts_per_page'        => 6,
			] 
		);

		ob_start();

		if ( $query->have_posts() ) : 
			$posts      = $query->posts;
			$skip_index = array_search( get_the_ID(), $posts );

			if ( false !== $skip_index ) :
				unset( $posts[ $skip_index ] );
			endif;

			$posts      = array_slice( $posts, 0, 5 );
			$cont_posts = count( $posts );
			?>

			<h2>
				<?php
					echo esc_html(
						sprintf( 
							/* translators: %1$s: post count */
							_n( '%1$d post with the tag of foo and the category of baz', '%1$d posts with the tag of foo and the category of baz', $cont_posts, 'site-counts' ),
							number_format_i18n( $cont_posts ),
						)
					);
				?>
			</h2>
			<ul>
				<?php foreach ( $posts as $post ) : ?>
					<li><?php echo esc_html( get_the_title( $post ) ); ?></li>
				<?php endforeach; ?>
			</ul>
			<?php 
		endif;

		$html_content = ob_get_clean();
		wp_cache_set( 'site_counts_block_render_posts_with_tag_cat_' . $tag_count . $cat_count, $html_content, 'site-counts', DAY_IN_SECONDS );

		return $html_content;
	}

}
