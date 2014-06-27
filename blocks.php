<?php
/**
 * Plugin Name: Blocks
 * Plugin URI: http://engr.washington.edu/
 * Description: Emulates Drupal Blocks for Wordpress
 * Version: 1.0.0
 * Author: Justin Labak
 * Author URI: http://engr.washington.edu
 * License: GPL2
 */
 
/*  Copyright 2014  Justin Labak  (email : jtlabak@uw.edu)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

	
/*
*  init
*
*  This function is called during the 'init' action and will do things such as:
*  create post_type, register scripts, add actions / filters
*/

if( !class_exists('blocks') ):

class blocks
{
	public $version 	= "1.0.0";
	public $db_version 	= "1.0.0";

	public $table_name	= "pages_blocks";

	function blocks()
	{

		// activation hooks
		register_activation_hook( __FILE__, array($this, 'blocks_install'));

		// actions
		add_action('init', array($this, 'init'), 1);
		//add_action('admin_init', array($this, 'admin_init'), 1);

		// scripts and styles
		add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

		// custom meta boxes
		add_action('add_meta_boxes', array($this, 'add_region_meta_box'));
		add_action('save_post', array($this, 'meta_save'));
		
		// admin columns
		add_filter('manage_edit-block_columns', array($this, 'add_block_columns'));
		add_action('manage_block_posts_custom_column', array($this, 'manage_block_columns'), 10, 2);

		// actions for sidebar modifications
		add_action('dynamic_sidebar_before', array($this, 'dynamic_sidebar_before'));
		add_filter('is_active_sidebar', array($this, 'is_active_sidebar'), 10, 2);
		
		// admin headers
		//add_action('admin_head', array($this, 'admin_css'));
	}

	function blocks_install()
	{
		global $wpdb;
		$table_name = $wpdb->base_prefix . $this->table_name;

		$sql = "CREATE TABLE $table_name (
				id mediumint(9) NOT NULL AUTO_INCREMENT,
				blog_id mediumint(9) NOT NULL DEFAULT 0,
				page varchar(128) NOT NULL,
				region varchar(64) NOT NULL,
				block_id mediumint(9) NOT NULL,
				UNIQUE KEY page_region (page, blog_id, region),
				PRIMARY KEY  (id)
				);";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta( $sql );
		add_option( "blocks_db_version", $this->db_version );
	}
	
	function dynamic_sidebar_before($region_name)
	{
		$block_ids = $this->block_ids_for_current_page($region_name);

		foreach ((array)$block_ids as $block_id)
		{
			$block_post = get_post($block_id);
			if ($block_post->post_status == "publish")
			{
				$block = $block_post->post_content;
				$block = apply_filters( "the_content" , $block);
				echo $block;
				if (current_user_can( "edit_pages" ))
				{
					echo "(<a href=\"/wp-admin/post.php?post=$block_id&action=edit\">Edit Block</a>)";
				}
			}
		}
	}

	function is_active_sidebar($is_active, $index)
	{
		$block_ids = $this->block_ids_for_current_page($index);

		foreach ((array)$block_ids as $block_id)
		{
			$block_post = get_post($block_id);
			if ($block_post->post_status == "publish")
			{
				return true;
			}
		}
		return false;
	}
	
	function build_page_array()
	{
		$path = $_SERVER['REQUEST_URI'];

		if ($path == "/")
			return array("<front>");

		$path = trim($path, "/");

		$return = array();
		$path_split = explode("/", $path);
		$path_so_far = "";

		foreach ((array)$path_split as $path_segment)
		{
			if ($path_segment != "")
			{
				$return[] = $path_so_far . "*";
				$path_so_far .= $path_segment . "/";
			}
		}
		$return[] = $path;
		return $return;
	}

	function block_ids_for_current_page($region_name)
	{
		global $wpdb;

		$blog_id = get_current_blog_id();
		$region = addslashes($region_name);
		$pages = $this->build_page_array();
		$pages_where = implode("' OR page = '", $pages);
		$table = $wpdb->base_prefix . $this->table_name;

		$sql = "SELECT block_id
				FROM $table
				WHERE region = '$region'
				AND blog_id = $blog_id
				AND (page = '$pages_where')";
		return $wpdb->get_col($sql);
	}

	function admin_init()
	{
		//wp_register_style('blocks_styles', plugins_url('admin.css', __FILE__));
	}

	function enqueue_scripts()
	{
		wp_register_style('blocks_admin', plugins_url('admin.css', __FILE__));
		wp_enqueue_style('blocks_admin');
	}

	function manage_block_columns($column_name, $id)
	{
		//echo print_r(get_option('sidebars_widgets'));
	}
	
	function add_block_columns( $cols )
	{
		$cols['author'] = __('Author');
		//$cols['custom'] = __('Content', 'blocks');
		
		return $cols;
	}
	
	function add_region_meta_box()
	{
		add_meta_box(
			'regions',
			__( 'Regions', 'blocks' ),
			array($this, 'render_region_meta_box'),
			'block'
		);
	}
	
	function render_region_meta_box($post)
	{
		include_once "meta_box_regions.php";
	}

	function meta_save($post_id)
	{
	    // Checks save status
	    $is_autosave = wp_is_post_autosave( $post_id );
	    $is_revision = wp_is_post_revision( $post_id );
	    $is_valid_nonce = ( 
	    	isset( $_POST[ 'blocks_nonce' ] ) && 
	    	wp_verify_nonce( $_POST[ 'blocks_nonce' ], 'save_blocks_regions' ) 
	    ) ? 'true' : 'false';
	 
	    // Exits script depending on save status
	    if ( $is_autosave || $is_revision || !$is_valid_nonce ) {
	        return;
	    }
	 
	    // Checks for input and sanitizes/saves if needed
	    if( isset( $_POST[ 'blocks_regions' ] ) ) {
	    	$this->update_pages_blocks($post_id, $_POST['blocks_regions']);
	        //update_post_meta( $post_id, 'blocks_regions', $_POST[ 'blocks_regions' ] );
	    }
	}

	function update_pages_blocks($block_id, $regions_pages)
	{
		global $wpdb;

		$table = $wpdb->base_prefix . $this->table_name;
		$blog_id = get_current_blog_id();

		foreach ((array)$regions_pages as $region_name => $pages)
		{
			// need to delete all pages first
			$wpdb->delete( $table, array(
				"blog_id" => $blog_id,
				"region" => $region_name,
				"block_id" => $block_id
			));

			foreach (explode("\n", $pages) as $page)
			{
				$page = trim($page);
				if ($page != "")
				{
					$wpdb->replace($table,
						array(
							"page" => $page,
							"region" => $region_name,
							"block_id" => $block_id,
							"blog_id" => $blog_id
						)
					);
				}
			}
		}
	}
	
	function init()
	{
		$labels = array(
			'name' => __( 'Blocks', 'blocks' ),
			'singular_name' => __( 'Blocks', 'blocks' ),
			'add_new' => __( 'Add New' , 'blocks' ),
			'add_new_item' => __( 'Add New Block' , 'blocks' ),
			'edit_item' =>  __( 'Edit Block' , 'blocks' ),
			'new_item' => __( 'New Block' , 'blocks' ),
			'view_item' => __('View Block', 'blocks'),
			'search_items' => __('Search Blocks', 'blocks'),
			'not_found' =>  __('No Blocks found', 'blocks'),
			'not_found_in_trash' => __('No Blocks found in Trash', 'blocks'), 
		);
		
		register_post_type('block', array(
			'labels' => $labels,
			'description' => __('Reusable Content Block', 'blocks'),
			'exclude_from_search' => false,
			'public' => false,
			'publicly_queryable' => false,
			'show_ui' => true,
			'show_in_nav_menus' => false,
			'show_in_menu' => true,
			'menu_position' => 20,
			'_builtin' =>  false,
			'capability_type' => 'page',
			'hierarchical' => false,
			'rewrite' => false,
			'query_var' => "block",
			'supports' => array(
				'title',
				'editor',
				'revisions'
			)
		));
		
		if( is_admin() )
		{
			//add_action('admin_menu', array($this,'admin_menu'));
			//add_action('admin_head', array($this,'admin_head'));
			//add_filter('post_updated_messages', array($this, 'post_updated_messages'));
		}
	}
	

	/*
	*  admin_menu
	*/

	/*
	function admin_menu()
	{
		add_menu_page(
			__("Blocks",'blocks'), 
			__("Blocks",'blocks'), 
			'manage_options', 
			'edit.php?post_type=blocks', 
			false, 
			false, 
			'80.025'
		);
	}
	*/
	
}

/*
*  blocks
*
*  The main function responsible for returning the one true blocks Instance to functions everywhere.
*  Use this function like you would a global variable, except without needing to declare the global.
*/

function blocks()
{
	global $blocks;
	
	if( !isset($blocks) )
	{
		$blocks = new blocks();
	}
	
	return $blocks;
}


// initialize
blocks();

endif;
