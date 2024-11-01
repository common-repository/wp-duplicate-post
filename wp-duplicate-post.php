<?php
/**
 * Plugin Name:     WP Duplicate Post
 * Plugin URI:      https://wordpress.org/plugins/wp-duplicate-post
 * Description:     Duplicate any type of posts, or copy them to new drafts for further editing.
 * Version:         1.0
 * Author:          Rubel Miah
 * Author URI:      http://rubelmiah.com
 * License:         GPL-2.0+
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:     wp-duplicate-post
 * Domain Path:     /languages
 */


/*
 * Function creates testimonial slider duplicate as a draft.
 */
function rm_wp_duplicate_post(){
	global $wpdb;
	if (! ( isset( $_GET['post']) || isset( $_POST['post'])  || ( isset($_REQUEST['action']) && 'rm_wp_duplicate_post' == $_REQUEST['action'] ) ) ) {
		wp_die(__('No post to duplicate has been supplied!','wp-duplicate-post'));
	}

	/*
	 * Nonce verification
	 */
	if ( !isset( $_GET['rm_wp_duplicate_nonce'] ) || !wp_verify_nonce( $_GET['rm_wp_duplicate_nonce'], basename( __FILE__ ) ) )
		return;

	/*
	 * Get the original post id
	 */
	$post_id = (isset($_GET['post']) ? absint( $_GET['post'] ) : absint( $_POST['post'] ) );
	/*
	 * and all the original post data then
	 */
	$post = get_post( $post_id );

	$current_user = wp_get_current_user();
	$new_post_author = $current_user->ID;

	/*
	 * if post data exists, create the post duplicate
	 */
	if (isset( $post ) && $post != null) {

		/*
		 * new post data array
		 */
		$args = array(
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_author'    => $new_post_author,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_name'      => $post->post_name,
			'post_parent'    => $post->post_parent,
			'post_password'  => $post->post_password,
			'post_status'    => 'draft',
			'post_title'     => $post->post_title,
			'post_type'      => $post->post_type,
			'to_ping'        => $post->to_ping,
			'menu_order'     => $post->menu_order
		);

		/*
		 * insert the post by wp_insert_post() function
		 */
		$new_post_id = wp_insert_post( $args );

		/*
		 * get all current post terms ad set them to the new post draft
		 */
		$taxonomies = get_object_taxonomies($post->post_type); // returns array of taxonomy names for post type, ex array("category", "post_tag");
		foreach ($taxonomies as $taxonomy) {
			$post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
			wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
		}

		/*
		 * duplicate all post meta just in two SQL queries
		 */
		$post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id");
		if (count($post_meta_infos)!=0) {
			$sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
			foreach ($post_meta_infos as $meta_info) {
				$meta_key = $meta_info->meta_key;
				if( $meta_key == '_wp_old_slug' ) continue;
				$meta_value = addslashes($meta_info->meta_value);
				$sql_query_sel[]= "SELECT $new_post_id, '$meta_key', '$meta_value'";
			}
			$sql_query.= implode(" UNION ALL ", $sql_query_sel);
			$wpdb->query($sql_query);
		}


		/*
		 * finally, redirect to the edit post screen for the new draft
		 */
		//wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
		wp_redirect( admin_url( 'edit.php?post_type=' . $post->post_type ) );
		exit;
	} else {
		wp_die(__('Post creation failed, could not find original post: ', 'wp-duplicate-post') . $post_id);
	}
}
add_action( 'admin_action_rm_wp_duplicate_post', 'rm_wp_duplicate_post' );

/*
 * Add the duplicate link to action list for post_row_actions
 */
function rm_wp_duplicate_post_link( $actions, $post ) {
	if (current_user_can('edit_posts')) {
		$actions['duplicate'] = '<a href="' . wp_nonce_url('admin.php?action=rm_wp_duplicate_post&post=' . $post->ID, basename(__FILE__), 'rm_wp_duplicate_nonce' ) . '" rel="permalink">'.__('Duplicate', 'wp-duplicate-post').'</a>';
	}
	return $actions;
}
add_filter( 'post_row_actions', 'rm_wp_duplicate_post_link', 10, 2 );