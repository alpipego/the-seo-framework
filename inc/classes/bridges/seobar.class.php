<?php
/**
 * @package The_SEO_Framework\Classes\Bridges
 * @subpackage The_SEO_Framework\Bridges
 */

namespace The_SEO_Framework\Bridges;

/**
 * The SEO Framework plugin
 * Copyright (C) 2019 Sybre Waaijer, CyberWire (https://cyberwire.nl/)
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 3 as published
 * by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

defined( 'THE_SEO_FRAMEWORK_PRESENT' ) or die;

/**
 * Loads the SEO Bar for administrative tables.
 *
 * @since 3.3.0
 * @uses \The_SEO_Framework\Interpreters\SeoBar
 * @see \The_SEO_Framework\Interpreters\SeoBar to generate a bar.
 *
 * @access private
 */
final class SeoBar {
	use \The_SEO_Framework\Traits\Enclose_Stray_Private;

	/**
	 * @since 3.3.0
	 * @var string $post_type The current post type.
	 */
	private $post_type = '';

	/**
	 * @since 3.3.0
	 * @var string $taxonomy The current taxonomy.
	 */
	private $taxonomy = '';

	/**
	 * Constructor.
	 *
	 * @since 3.3.0
	 */
	public function __construct() { }

	/**
	 * @since 3.3.0
	 */
	public function prepare_seo_bar_tables() {
		//* Initialize columns.
		\add_action( 'current_screen', [ $this, '_prepare_columns' ] );

		//* Ajax handlers for columns.
		\add_action( 'wp_ajax_add-tag', [ $this, '_prepare_columns_wp_ajax_add_tag' ], -1 );
		\add_action( 'wp_ajax_inline-save', [ $this, '_prepare_columns_wp_ajax_inline_save' ], -1 );
		\add_action( 'wp_ajax_inline-save-tax', [ $this, '_prepare_columns_wp_ajax_inline_save_tax' ], -1 );
	}

	/**
	 * Initializes SEO Bar columns.
	 *
	 * @since 3.3.0
	 *
	 * @param \WP_Screen|string $screen \WP_Screen
	 */
	public function _prepare_columns( $screen ) {
		$this->init_seo_bar_columns( $screen );
	}

	/**
	 * Initializes SEO columns for adding a tag or category.
	 *
	 * @since 2.9.1
	 * @since 3.3.0 Moved to \The_SEO_Framework\Bridges\SeoBar
	 * @access private
	 */
	public function _prepare_columns_wp_ajax_add_tag() {

		if ( ! \check_ajax_referer( 'add-tag', '_wpnonce_add-tag', false )
		|| empty( $_POST['taxonomy'] ) )
			return;

		$taxonomy = stripslashes( $_POST['taxonomy'] ); // phpcs:ignore -- Nonce, Sanitization, CSRF ok
		$tax_object = $taxonomy ? \get_taxonomy( $taxonomy ) : false;

		if ( $tax_object && \current_user_can( $tax_object->cap->edit_terms ) )
			$this->init_seo_bar_columns_ajax();
	}

	/**
	 * Initializes SEO columns for adding a tag or category.
	 *
	 * @since 2.9.1
	 * @since 3.3.0 Moved to \The_SEO_Framework\Bridges\SeoBar
	 * @securitycheck 3.0.0 OK.
	 * @access private
	 */
	public function _prepare_columns_wp_ajax_inline_save() {

		if ( ! \check_ajax_referer( 'inlineeditnonce', '_inline_edit', false )
		|| empty( $_POST['post_ID'] )
		|| empty( $_POST['post_type'] ) )
			return;

		$post_type = stripslashes( $_POST['post_type'] ); // phpcs:ignore -- Nonce, Sanitization, CSRF ok
		$pto       = $post_type ? \get_post_type_object( $post_type ) : false;

		if ( $pto && \current_user_can( 'edit_' . $pto->capability_type, (int) $_POST['post_ID'] ) )
			$this->init_seo_bar_columns_ajax();
	}

	/**
	 * Initializes SEO columns for adding a tag or category.
	 *
	 * @since 2.9.1
	 * @since 3.3.0 Moved to \The_SEO_Framework\Bridges\SeoBar
	 * @securitycheck 3.0.0 OK.
	 * @access private
	 */
	public function _prepare_columns_wp_ajax_inline_save_tax() {

		if ( ! \check_ajax_referer( 'taxinlineeditnonce', '_inline_edit', false )
		|| empty( $_POST['tax_ID'] ) )
			return;

		$tax_id = (int) $_POST['tax_ID'];

		if ( \current_user_can( 'edit_term', $tax_id ) )
			$this->init_seo_bar_columns_ajax();
	}

	/**
	 * Initializes SEO Bar columns.
	 *
	 * @since 3.3.0
	 *
	 * @param \WP_Screen $screen The current screen.
	 */
	protected function init_seo_bar_columns( $screen ) {

		if ( ! \the_seo_framework()->is_wp_lists_edit()
		|| empty( $screen->id ) )
			return;

		$post_type = isset( $screen->post_type ) ? $screen->post_type : '';
		$taxonomy  = isset( $screen->taxonomy ) ? $screen->taxonomy : '';

		if ( $taxonomy ) {
			if ( ! \the_seo_framework()->taxonomy_supports_custom_seo( $taxonomy ) )
				return;
		} else {
			if ( ! \the_seo_framework()->post_type_supports_custom_seo( $post_type ) )
				return;
		}

		$this->post_type = $post_type;
		$this->taxonomy  = $taxonomy;

		if ( $taxonomy )
			\add_filter( 'manage_' . $taxonomy . '_custom_column', [ $this, '_output_seo_bar_for_column_tax' ], 1, 3 );

		\add_filter( 'manage_' . $screen->id . '_columns', [ $this, '_add_column' ], 10, 1 );
		/**
		 * Always load pages and posts.
		 * Many CPT plugins rely on these.
		 */
		\add_action( 'manage_posts_custom_column', [ $this, '_output_seo_bar_for_column' ], 1, 2 );
		\add_action( 'manage_pages_custom_column', [ $this, '_output_seo_bar_for_column' ], 1, 2 );
	}

	/**
	 * Initializes SEO Bar columns for AJAX.
	 *
	 * @since 3.3.0
	 * @see callers for CSRF protection.
	 */
	protected function init_seo_bar_columns_ajax() {

		$taxonomy  = isset( $_POST['taxonomy'] ) ? stripslashes( $_POST['taxonomy'] ) : ''; // phpcs:ignore -- CSRF ok.
		$post_type = isset( $_POST['post_type'] ) ? stripslashes( $_POST['post_type'] ) : ''; // phpcs:ignore -- CSRF ok.

		//? /wp-admin/js/inline-edit-tax.js doesn't send post_type, instead, it sends tax_type, which is the same.
		$post_type = $post_type
				?: ( isset( $_POST['tax_type'] ) ? stripslashes( $_POST['tax_type'] ) : '' ); // phpcs:ignore -- CSRF ok.

		if ( $taxonomy ) {
			if ( ! \the_seo_framework()->taxonomy_supports_custom_seo( $taxonomy ) )
				return;
		} else {
			if ( ! \the_seo_framework()->post_type_supports_custom_seo( $post_type ) )
				return;
		}

		$this->post_type = $post_type;
		$this->taxonomy  = $taxonomy;

		$screen_id = isset( $_POST['screen'] ) ? stripslashes( $_POST['screen'] ) : ''; // phpcs:ignore -- CSRF ok.

		// Not elseif; either request.
		if ( $taxonomy )
			\add_filter( 'manage_' . $taxonomy . '_custom_column', [ $this, '_output_seo_bar_for_column_tax' ], 1, 3 );

		if ( $screen_id ) {
			//* Everything but inline-save-tax action.
			\add_filter( 'manage_' . $screen_id . '_columns', [ $this, '_add_column' ], 10, 1 );

			/**
			 * Always load pages and posts.
			 * Many CPT plugins rely on these.
			 */
			\add_action( 'manage_posts_custom_column', [ $this, '_output_seo_bar_for_column' ], 1, 2 );
			\add_action( 'manage_pages_custom_column', [ $this, '_output_seo_bar_for_column' ], 1, 2 );
		} elseif ( $taxonomy ) {
			/**
			 * Action "inline-save-tax" does not POST 'screen'.
			 * @see WP Core wp_ajax_inline_save_tax():
			 * `_get_list_table( 'WP_Terms_List_Table', array( 'screen' => 'edit-' . $taxonomy ) );`
			 */
			\add_filter( 'manage_edit-' . $taxonomy . '_columns', [ $this, '_add_column' ], 1, 1 );
		}
	}

	/**
	 * Adds SEO column on edit(-tags).php
	 *
	 * Also determines where the column should be placed. Preferred before comments, then data, then tags.
	 * If neither found, it will add the column to the end.
	 *
	 * @since 2.1.9
	 * @since 3.3.0 1. Now marked private.
	 *              2. Moved to \The_SEO_Framework\Bridges\SeoBar
	 * @access private
	 *
	 * @param array $columns The existing columns.
	 * @return array $columns The adjusted columns.
	 */
	public function _add_column( $columns ) {

		$seocolumn = [ 'tsf-seo-bar-wrap' => 'SEO' ];

		$column_keys = array_keys( $columns );

		//* Column keys to look for, in order of appearance.
		$order_keys = [
			'comments',
			'posts',
			'date',
			'tags',
		];

		/**
		 * @since 2.8.0
		 * @param array $order_keys The keys where the SEO column may be prepended to.
		 *                          The first key found will be used.
		 */
		$order_keys = (array) \apply_filters( 'the_seo_framework_seo_column_keys_order', $order_keys );

		foreach ( $order_keys as $key ) {
			//* Put value in $offset, if not false, break loop.
			if ( false !== ( $offset = array_search( $key, $column_keys, true ) ) )
				break;
		}

		//* I tried but found nothing
		if ( false === $offset ) {
			//* Add SEO bar at the end of columns.
			$columns = array_merge( $columns, $seocolumn );
		} else {
			//* Add seo bar between columns.

			//* Cache columns.
			$columns_before = $columns;

			$columns = array_merge(
				array_splice( $columns, 0, $offset ),
				$seocolumn,
				array_splice( $columns_before, $offset )
			);
		}

		return $columns;
	}

	/**
	 * Outputs the SEO Bar on singular pages.
	 *
	 * @since 3.3.0
	 *
	 * @param string $column_name The name of the column to display.
	 * @param int    $post_id     The current post ID.
	 */
	public function _output_seo_bar_for_column( $column_name, $post_id ) {

		if ( 'tsf-seo-bar-wrap' === $column_name ) {
			echo \The_SEO_Framework\Interpreters\SeoBar::generate_bar( [ // phpcs:ignore -- Output is escaped.
				'id'        => $post_id,
				'post_type' => $this->post_type,
			] );
		}
	}

	/**
	 * Outputs the SEO Bar on taxonomy pages.
	 *
	 * @since 3.3.0
	 * @NOTE Unlike _output_seo_bar_for_column(), this is a filter callback.
	 *       Because of this, the first parameter is a useless string, which must be extended.
	 *       Discrepancy: https://core.trac.wordpress.org/ticket/33521
	 *       With this, the proper function name should be "_get..." or "_add...", but not "_output.."
	 *
	 * @param string $string      Blank string.
	 * @param string $column_name Name of the column.
	 * @param string $term_id     Term ID.
	 * @return string
	 */
	public function _output_seo_bar_for_column_tax( $string, $column_name, $term_id ) {

		if ( 'tsf-seo-bar-wrap' === $column_name ) {
			return $string . \The_SEO_Framework\Interpreters\SeoBar::generate_bar( [
				'id'       => $term_id,
				'taxonomy' => $this->taxonomy,
			] );
		}

		return $string;
	}
}