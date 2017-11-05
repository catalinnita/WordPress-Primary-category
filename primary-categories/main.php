<?php
/*
Plugin Name: Primary Category
Plugin URI: None yet
Description: Let users select the primary category for a post
Version: 1.0.0
Author: Catalin Nita
Author URI: Catalin Nita
License: GNU General Public License v2 or later
*/

class TENUP_MAIN_CATEGORY {

	const VERSION = '1.0.0';

	function __construct() {

		// add js script for handling categories changes
		add_action( 'admin_enqueue_scripts', array( $this, '_enqueue_admin_js') );
				
		// create post type metaboxes
		add_action( 'add_meta_boxes', array( $this, '_add_meta_box' ) );
		add_action( 'save_post', array( $this, '_save_meta_box' ) );

		// remove primary category metas when a taxonomy is removed
		add_action( 'delete_term', array( $this, '_delete_primary_category'), 100, 4 );

		// update rewrite rules for primary category and modify the permalink
		add_filter( 'rewrite_rules_array', array( $this, '_cpt_rewrite_rules') );
		add_filter( 'post_type_link', array( $this, '_primary_category_permalink' ), 100, 3 );
		add_filter( 'post_link', array( $this, '_primary_category_permalink' ), 100, 3 );

	}


	/**
	 * Loads the primary-category.js
	 */

	public function _enqueue_admin_js() {

		wp_enqueue_script(
			'tenup-primary-category-admin-js', 
			plugin_dir_url( __FILE__ ) . '/js/primary-category.js', 
			array('jquery'),
			self::VERSION,
			true
		);	

	}


	/**
	 * Creates primary category meta box
	 */

	public function _add_meta_box() {

		// --> add here all custom post types
		add_meta_box( 'tenup-primary-category', __( 'Primary category', 'tenuppc' ), array( $this, '_primary_category_settings' ), $this->_get_cpts() , 'side' );

	}


	/**
	 * Saves primary category metabox changes as _tenup_primary_category post meta
	 *
	 * @param $post_id (int) Post ID
	 *
	 * @return null
	 */

	public function _save_meta_box( $post_id ) {

		// make sure user have rights to see these settings
		if ( !current_user_can('edit_posts') )
			return;

		// make sure they are comming from admin page and have the nonce set up
		if ( defined( 'DOING_AJAX' ) || !isset($_POST[ '_wpnonce' ]) || !wp_verify_nonce( $_POST[ '_wpnonce' ], 'update-post_'.$post_id ) )
			return;

		// if there is no primary category set it means we can remove the old one
		if ( !isset( $_POST[ 'tenup_primary_category' ] ) ) {
			delete_post_meta( $post_id, '_tenup_primary_category' );
			$this->refresh_primary_category( $post_id );

			return;
		}

		// sanitize the term_id just to be sure
		$primary_category_id = intval( $_POST[ 'tenup_primary_category' ] );

		// update post meta with term_id
		update_post_meta( $post_id, '_tenup_primary_category', $primary_category_id );

		// refresh transient
		$this->refresh_primary_category( $post_id );

	}


	/**
	 * Creates primary category metabox html
	 *
	 * @param $post (object) Current post
	 *
	 * @return null
	 */	

	public function _primary_category_settings( $post ) {

		// get primary category as se up in db
		$primary_category = get_post_meta( $post->ID, '_tenup_primary_category', true );
		
		// get all post categories
		$taxonomy = $this->_get_tax_for_post_type( get_post_type( $post->ID ) );
		$all_categories = wp_get_post_terms( $post->ID, $taxonomy );

		$disabled = ( count( $all_categories ) < 2 ) ? 'disabled="disabled"' : '';
		$hidden = ( count( $all_categories ) < 2 ) ? ' class="hidden"' : '';
		$hiddendesc = ( count( $all_categories ) >= 2 ) ? ' class="hidden"' : '';

		// send vars to js
		wp_localize_script( 'tenup-primary-category-admin-js', 'primary_category_vars', array( 'taxonomy' => $taxonomy ) );

		// build the meta box select
		?>
		<div<?php echo $hidden; ?>>
			<p>Please select primary category</p>
			<select id="tenup_primary_category" name="tenup_primary_category"<?php echo $disabled; ?>>
				<?php 
				foreach( $all_categories as $category ) { 
					$selected = ( $primary_category == $category->term_id ) ? ' selected="selected"' : '';
					?>
					<option value="<?php echo $category->term_id; ?>"<?php echo $selected; ?>><?php echo $category->name; ?></option>
					<?php

				}
				?>
			</select>
		</div>
		<em<?php echo $hiddendesc; ?>>Please select at least two categories to activate primary category feature</em>
		
		<?php

	}


	/**
	 * Gets all registeres custom post types + post
	 *
	 * @return $cpts
	 */

	public function _get_cpts() {
		
		$exclude = array( 'page', 'attachment', 'revision', 'nav_menu_item', 'custom_css', 'customize_changeset' );
		$cpts = array();

   		$post_types = get_post_types( array( 'public' => true ), 'objects' );
   		foreach( $post_types as $post_type ) {

   			if ( !in_array( $post_type->name, $exclude ) )
   				$cpts[] = $post_type->name;

   		}

   		return $cpts;

	}


	/**
	 * Adds new rewrite rules for custom posts types - callback for rewrite_rules_array filter
	 *
	 * @param $rules (array) Existing rewrite rules to be filtered
	 *
	 * @return $rules
	 */	

	public function _cpt_rewrite_rules( $rules ) {
	    
	    $new  = array();

	    foreach( $this->_get_cpts() as $cpt ) {
		    $new[ $cpt . '/' . $this->_get_tax_for_post_type( $cpt ) . '/(.+)/(.+)/(.+)/(.+)/?$' ] = 'index.php?custom_post_type_name=$matches[4]';
		    $new[ $cpt . '/' . $this->_get_tax_for_post_type( $cpt ) . '/(.+)/?$' ]                = 'index.php?taxonomy_name=$matches[1]'; 

		}

	    return array_merge($new, $rules);

	}	
	

	/**
	 * Updates the permalink for posts and custom posts type - callback for post_link & post_type_link filters
	 *
	 * @param $permalink (string) The post URL
	 * @param $post (object) The post object
	 * @param $leavename (bool) Whether to keep the post name or page name 	 
	 *
	 * @return $permalink
	 */	

	public function _primary_category_permalink( $permalink, $post, $leavename ) {

		$primary_category = $this->get_post_primary_category( $post->ID );

		// if a primary category is set add it in permalink
		if( $primary_category ) {

			if( $post->post_type == 'post' ) {
				$permalink = trailingslashit( home_url( $primary_category. '/' . $post->post_name .'/' ) );

			} else {
				$permalink = str_replace( '%' . $this->_get_tax_for_post_type( $post->post_type ) . '%', $primary_category, $permalink );

			}

		// if NO primary category is set we still have to add the first term in permalink for custom post types
		} elseif( $post->post_type != 'post' ) {
			$terms = get_the_terms( $post->ID, $this->_get_tax_for_post_type( $post->post_type ) );
			$primary_category = ( is_array( $terms ) ) ? array_pop( $terms )->slug : '';
			
			$permalink = str_replace( '%' . $this->_get_tax_for_post_type( $post->post_type ) . '%', $primary_category, $permalink );

		}

		return $permalink;

	}


	/**
	 * Clears and sets again the primary category transient for a given post 
	 *
	 * @param $post_id (int) Post ID
	 *
	 * @return null
	 */	

	public function refresh_primary_category( $post_id ) {

		delete_transient( '_tenup_primary_category' . $post_id );
		$this->get_post_primary_category( $post_id );

	}


	/**
	 * Retrieves primary category for a given post with a little bit of cache
	 *
	 * @param $post_id (int) Post ID
	 *
	 * @return $primary_category
	 */	

	public function get_post_primary_category( $post_id ) {

		$primary_category = get_transient( '_tenup_primary_category' . $post_id );

		if( !$primary_category ) { 
			$primary_category_id = get_post_meta( $post_id, '_tenup_primary_category', true );

			if( !$primary_category_id ) 
				return false;

			$taxonomy = $this->_get_tax_for_post_type( get_post_type( $post_id ) );

			$primary_category_o = get_term( $primary_category_id, $taxonomy );
			$primary_category = $primary_category_o->name; 

			set_transient( '_tenup_primary_category' . $post_id, $primary_category, DAY_IN_SECONDS );

		}

		return $primary_category;

	}


	/**
	 * Queries and returns all posts for a given primary category
	 *
	 * @param $term_id (int) Term ID
	 *
	 * @return $posts
	 */	

	public function get_all_posts( $term_id ) {

		$args = array(
		   'meta_query' => array(
		       array(
		           'key' => '_tenup_primary_category',
		           'value' => $term_id,
		           'compare' => '=',
		       )
		   )
		);
		
		$posts = get_posts($args);

		return $posts;

	}


	/**
	 * Removes primary category metas when a taxonomy is removed - callback for delete_term action 
	 *
	 * @param $term (int) Term ID
	 * @param $tt_id (int) Term taxonomy ID
	 * @param $taxonomy (string) Taxonomy slug
	 * @param $deleted_term (mixed) Copy of the already-deleted term
	 *
	 * @return null
	 */	

	public function _delete_primary_category( $term, $tt_id, $taxonomy, $deleted_term ) {
		global $wpdb;

		if ( !in_array( $taxonomy, $this->_get_taxs() ) )
			return;

		$wpdb->delete(
			$wpdb->prefix . 'postmeta',
			array(
				'meta_key' => '_tenup_primary_category',
				'meta_value' => $term
			),
			array(
				'%s',
				'%d'
			) 
		);

		// delete transients as well
		$posts = $this->get_all_posts( $term );
		foreach( $posts as $post ) {
			delete_transient( '_tenup_primary_category' . $post->ID );

		}



	}

 
	/**
	 * Gets first hierarchical taxonomy for a given post type
	 *
	 * @param $post_type (string) Post type name
	 *
	 * @return $taxonomy->name|false
	 */

	public function _get_tax_for_post_type( $post_type ) {

		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		foreach( $taxonomies as $taxonomy ) {
			if( $taxonomy->hierarchical )
				return $taxonomy->name;

		}

		return false;

	}

 
	/**
	 * Gets all taxonomies for all custom post types
	 *
	 * @return $taxs
	 */

	public function _get_taxs() {

		$cpts = $this->_get_cpts();
		$taxs = array();

		foreach( $cpts as $cpt ) {
			
			$tax = $this->_get_tax_for_post_type( $cpt );
			if( !$tax )
				continue;

			$taxs[] = $tax;
		}

		return $taxs;

	}


}

// start a new instance
$TENUP_MAIN_CATEGORY = new TENUP_MAIN_CATEGORY();