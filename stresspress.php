<?php
/**
 * @author Stresslimit [@stresslimit, @jkudish]
 * this file gives a bunch of core functionality extensions for 
 * use in setting up a new Stresslimit WordPress site. Feel free 
 * to comment/uncomment/add stuff as needed.
 */

/*-----------------------------------
   Enable/Disable WordPress stuff
-------------------------------------*/

// remove junk from head
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'feed_links_extra', 3);
remove_action('wp_head', 'start_post_rel_link', 10, 0);
remove_action('wp_head', 'parent_post_rel_link', 10, 0);

// we normally want these to be active
// remove_action('wp_head', 'feed_links', 2);
// remove_action('wp_head', 'index_rel_link');
// remove_action('wp_head', 'adjacent_posts_rel_link', 10, 0);

// add post thumbnail support + custom menu support
add_theme_support('post-thumbnails');
register_nav_menus();

// Limit post revisions: this should go in wp-config
// define('WP_POST_REVISIONS', 5);

// disable file editor
define('DISALLOW_FILE_EDIT',true);

// remove unwanted core dashboard widgets
add_action('wp_dashboard_setup', 'sld_rm_dashboard_widgets');
function sld_rm_dashboard_widgets() {
	global $wp_meta_boxes;
	// unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_right_now']);         // right now [content, discussion, theme, etc]
	unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_plugins']);              // plugins
	unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_incoming_links']);       // incoming links
	unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_primary']);                // wordpress blog
	unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_secondary']);              // other wordpress news
	unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_quick_press']);            // quickpress
	// unset($wp_meta_boxes['dashboard']['side']['core']['dashboard_recent_drafts']);          // drafts
	// unset($wp_meta_boxes['dashboard']['normal']['core']['dashboard_recent_comments']);      // comments
}

// who uses Links ? goodbye 2005...
add_action('admin_menu', 'sld_manage_menu_items', 99);
function sld_manage_menu_items() {
	// we can do this based on permissions too if we want
	if( !current_user_can( 'administrator' ) ) {
	}
	remove_menu_page('link-manager.php'); // Links
	// remove_menu_page('edit.php'); // Posts
	// remove_menu_page('upload.php'); // Media
	// remove_menu_page('edit-comments.php'); // Comments
	// remove_menu_page('edit.php?post_type=page'); // Pages
	// remove_menu_page('plugins.php'); // Plugins
	// remove_menu_page('themes.php'); // Appearance
	// remove_menu_page('users.php'); // Users
	// remove_menu_page('tools.php'); // Tools
	// remove_menu_page('options-general.php'); // Settings
}

// remove the +NEW items from the admin bar
add_action( 'wp_before_admin_bar_render', 'sld_admin_bar' );
function sld_admin_bar() {
    global $wp_admin_bar;
    // $wp_admin_bar->remove_menu( 'new-post' );
    $wp_admin_bar->remove_menu( 'new-media' );
    $wp_admin_bar->remove_menu( 'new-link' );
    // $wp_admin_bar->remove_menu( 'comments' );
}

// remove unwanted metaboxes
// these are managed through Screen Options, but in case you want to disable them 
// entirely, here they are. Disabled for now, so post edit screen is per default.
// add_action('admin_head', 'sld_rm_post_custom_fields');
function sld_rm_post_custom_fields() {
	// pages
	remove_meta_box( 'postcustom' , 'page' , 'normal' );
	remove_meta_box( 'commentstatusdiv' , 'page' , 'normal' );
	remove_meta_box( 'commentsdiv' , 'page' , 'normal' );
	remove_meta_box( 'authordiv' , 'page' , 'normal' );

	// posts
	remove_meta_box( 'postcustom' , 'post' , 'normal' );
	remove_meta_box( 'postexcerpt' , 'post' , 'normal' );
	remove_meta_box( 'trackbacksdiv' , 'post' , 'normal' );
}

// Do not show the Editorial Calendar for yourcustomtype
// if ( is_admin() ) {
// 	add_filter('edcal_show_calendar_yourcustomtype', '__return_false');
// }



/*-----------------------------------
	Misc
-------------------------------------*/

// make cleaner better permalink urls
function sld_url_cleaner_clean($slug) {
	// make sure to replace spaces with dashes
	$slug = str_replace( ' ', '-', $slug);

	// remove everything except letters, numbers and -
	$pattern = '~([^a-z0-9\-])~i';
	$replacement = '';
	$slug = preg_replace($pattern, $replacement, $slug);

	// when more than one - , replace it with one only
	$pattern = '~\-\-+~';
	$replacement = '-';
	$slug = preg_replace($pattern, $replacement, $slug);

	return $slug;
}
add_filter('editable_slug', 'sld_url_cleaner_clean');

// add conditional for login page
function is_login_page() {
    return in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php'));
}

// add post type class to body admin 
function sld_admin_body_class( $classes ) {
	global $wpdb, $post;
	$post_type = get_post_type( $post->ID );
	if ( is_admin() ) {
		$classes .= 'type-' . $post_type;
	}
	return $classes;
}
add_filter( 'admin_body_class', 'sld_admin_body_class' );

// grab all post meta into $post object [could be resource intensive]
function setup_postmeta_all( $post=false ) {
	global $wpdb;
	// make sure we have a proper $post object, so we can use in templates without args, 
	// or in special cases where we want to pass the object manually
	if ( !$post ) global $post;
	$sql = "
		SELECT `meta_key`, `meta_value`
		FROM `$wpdb->postmeta`
		WHERE `post_id` = $post->ID
	";
	$wpdb->query($sql);
	foreach($wpdb->last_result as $k => $v) {
		if ( isset ( $post->{$v->meta_key} ) ) {
			if ( !is_array($post->{$v->meta_key}) ) {
				$post->{$v->meta_key} = array( $post->{$v->meta_key} );
			}
			$post->{$v->meta_key}[] = $v->meta_value;
		} else
		$post->{$v->meta_key} = $v->meta_value;
	};
	return $post;
}

// get either the featured image or first image in the post
function sld_get_post_thumbnail( $postid, $size='thumbnail' ) {
	if ( has_post_thumbnail( $postid ) ) {
		return get_the_post_thumbnail( $postid, $size );
	} else {
		// echo 'has no thumbnail';
		$post = get_post( $postid );
		if ( preg_match_all( '/<img [^>]class=["|\'][^"|\']*wp-image-([\d]+)/i', $post->post_content, $matches) ) {
			$img_id = @$matches[1][0];
			return wp_get_attachment_image( $img_id, $size );
		} else if ( preg_match_all( '/<img [^>]*src=["|\']([^"|\']+)/i', $post->post_content, $matches) ) {
			// get sizes dimensions from wp
			$img = @$matches[1][0];
			return '<img src="'.$img.'">';
		}		

	}
}

function sld_post_thumbnail( $postid, $size='thumbnail' ) {
	echo sld_get_post_thumbnail( $postid, $size );
}


/*-----------------------------------
   Stresslimit admin branding
-------------------------------------*/

function sld_admin_styles() { ?>
	<style>
	/* nice stresslimit logo for wp login screen*/
	#login h1 a { background:transparent url('data:image/png;base64,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') 0 0 no-repeat; background-size:auto; height:55px; margin-left:10px; }
	/* stresslimit logo top left of admin bar */
	/*	#wp-admin-bar-wp-logo > .ab-item .ab-icon {background:transparent url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABQAAAATCAYAAACQjC21AAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAnRJREFUeNqsVMtqIlEQLbWNryQIPuKLcTGELFUQZVbCIIObwcGvkAj+glv/wGU2Oj8QyCoLXehOEEFUBAUXCiqO4zBJfMXpU9iSdLdZTUHR91bfOvfUqeoWOp0OHeznbreLbTab3Xa7pdfXVw7u9/ujS/u3Ju1xXsz9Ixzin0X/IbqJVEyr1SqAT9hf7WHxTQ4GELPZTAaDgUTmBNaIWSwWEgThFPBaYvj9bVSn03HC4+Mj1et1ms1mXJLJZKLr62uKx+PkcDjo+flZgSiIiZ80Gs0XKSCuGaxQKFC5XCa9Xs9giOEiaF6r1ej29pZubm5otVq9l0f0r6JbpQBKBKtKpUKXl5dcNlgFAgGyWq0MvFgsqNVqsQQKhqIn5doNh0NmA91cLhflcjkGHwwGzDoSiZDP56OXlxdVwLA8COFRJsqdz+eUz+dZM6/XS+FwmPx+PzdKrTHCwY8mzhKFQiG6uLhgBpCg2+1Su90+6gjt0uk0s8Z5uYZ7OaDH46FsNktut5uZgK3EGBc0Gg0qFos8PmoMFYZxQGnBYJD6/T5rOplMqNls8hrse70eNwdNw6VvGSrMaDTy4fv7e25SMpmkTCZDqVSKxwSjBZD1es3rDxmipGq1Snd3dzSdTsnpdFI0GuVLUCqekMVms7GG0jd/EhAH0FEwALvlckkPDw/ckLOzMz6DZiUSCf5ynp6e3uVrxO6NRNpubACC2yH2aDSiUqnE3ZXmDXG73c5gsViMy5f9bX6pAsLRUTAcj8dcOt6dn5/zoGNOJWZyQNUuQ2hJcJR/dXXFiYckRZlyDfWnXgIEAB/9YGWmB+AUpOj/2O9/AgwAieZx8/H99fcAAAAASUVORK5CYII=') 0 0 no-repeat; }*/
	/* stresslimit logo on dashboard */
	#icon-index { margin:8px 8px 0 8px; background:transparent url('data:image/png;base64,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') 0 0 no-repeat; }
	</style><?php
}
add_action('login_head', 'sld_admin_styles');
add_action('admin_head', 'sld_admin_styles');

// login logo link url
function login_header_url() {
	return 'http://stresslimitdesign.com';
}
add_filter( 'login_headerurl', 'login_header_url' );

// admin footer branding
function sld_admin_footer_brand() {
	echo date("Y").' <a href="http://stresslimitdesign.com">stresslimitdesign</a>';
} 
add_filter('admin_footer_text', 'sld_admin_footer_brand');



/*-----------------------------------
	Show categories as tree in admin
-------------------------------------*/

if ( ! class_exists('Category_Checklist') ) {
class Category_Checklist {

	function init() {
		add_action('add_meta_boxes', array(__CLASS__, 'replace_box'));
	}

	// adapted from wp-admin/edit-form-advanced.php
	function replace_box($post_type) {
		foreach ( get_object_taxonomies($post_type) as $tax_name ) {
			$taxonomy = get_taxonomy($tax_name);
			if ( !$taxonomy->show_ui || !$taxonomy->hierarchical )
				continue;
			$label = isset($taxonomy->label) ? esc_attr($taxonomy->label) : $tax_name;
			remove_meta_box($tax_name . 'div', $post_type, 'side');
			// don't use 'core' as priority
			add_meta_box($tax_name . 'div', $label, array(__CLASS__, 'meta_box'), $post_type, 'side', 'high', array( 'taxonomy' => $tax_name ));
		}
	}

	// pasted from wp-admin/includes/meta-boxes.php -> post_categories_meta_box()
	function meta_box( $post, $box ) {
		$defaults = array('taxonomy' => 'category');
		if ( !isset($box['args']) || !is_array($box['args']) )
			$args = array();
		else
			$args = $box['args'];
		extract( wp_parse_args($args, $defaults), EXTR_SKIP );
		$tax = get_taxonomy($taxonomy);

		?>
		<div id="taxonomy-<?php echo $taxonomy; ?>" class="categorydiv">
			<ul id="<?php echo $taxonomy; ?>-tabs" class="category-tabs">
				<li class="tabs"><a href="#<?php echo $taxonomy; ?>-all" tabindex="3"><?php echo $tax->labels->all_items; ?></a></li>
				<li class="hide-if-no-js"><a href="#<?php echo $taxonomy; ?>-pop" tabindex="3"><?php _e( 'Most Used' ); ?></a></li>
			</ul>

			<div id="<?php echo $taxonomy; ?>-pop" class="tabs-panel" style="display: none;">
				<ul id="<?php echo $taxonomy; ?>checklist-pop" class="categorychecklist form-no-clear" >
					<?php $popular_ids = wp_popular_terms_checklist($taxonomy); ?>
				</ul>
			</div>

			<div id="<?php echo $taxonomy; ?>-all" class="tabs-panel">
				<?php
	            $name = ( $taxonomy == 'category' ) ? 'post_category' : 'tax_input[' . $taxonomy . ']';
	            echo "<input type='hidden' name='{$name}[]' value='0' />"; // Allows for an empty term set to be sent. 0 is an invalid Term ID and will be ignored by empty() checks.
	            ?>
				<ul id="<?php echo $taxonomy; ?>checklist" class="list:<?php echo $taxonomy?> categorychecklist form-no-clear">
					<?php wp_terms_checklist($post->ID, array( 'taxonomy' => $taxonomy, 'popular_cats' => $popular_ids, 'checked_ontop' => false ) ) ?>
					<?php /* ^ only change */ ?>
				</ul>
			</div>
		<?php if ( !current_user_can($tax->cap->assign_terms) ) : ?>
		<p><em><?php _e('You cannot modify this taxonomy.'); ?></em></p>
		<?php endif; ?>
		<?php if ( current_user_can($tax->cap->edit_terms) ) : ?>
				<div id="<?php echo $taxonomy; ?>-adder" class="wp-hidden-children">
					<h4>
						<a id="<?php echo $taxonomy; ?>-add-toggle" href="#<?php echo $taxonomy; ?>-add" class="hide-if-no-js" tabindex="3">
							<?php
								/* translators: %s: add new taxonomy label */
								printf( __( '+ %s' ), $tax->labels->add_new_item );
							?>
						</a>
					</h4>
					<p id="<?php echo $taxonomy; ?>-add" class="category-add wp-hidden-child">
						<label class="screen-reader-text" for="new<?php echo $taxonomy; ?>"><?php echo $tax->labels->add_new_item; ?></label>
						<input type="text" name="new<?php echo $taxonomy; ?>" id="new<?php echo $taxonomy; ?>" class="form-required form-input-tip" value="<?php echo esc_attr( $tax->labels->new_item_name ); ?>" tabindex="3" aria-required="true"/>
						<label class="screen-reader-text" for="new<?php echo $taxonomy; ?>_parent">
							<?php echo $tax->labels->parent_item_colon; ?>
						</label>
						<?php wp_dropdown_categories( array( 'taxonomy' => $taxonomy, 'hide_empty' => 0, 'name' => 'new'.$taxonomy.'_parent', 'orderby' => 'name', 'hierarchical' => 1, 'show_option_none' => '&mdash; ' . $tax->labels->parent_item . ' &mdash;', 'tab_index' => 3 ) ); ?>
						<input type="button" id="<?php echo $taxonomy; ?>-add-submit" class="add:<?php echo $taxonomy ?>checklist:<?php echo $taxonomy ?>-add button category-add-sumbit" value="<?php echo esc_attr( $tax->labels->add_new_item ); ?>" tabindex="3" />
						<?php wp_nonce_field( 'add-'.$taxonomy, '_ajax_nonce-add-'.$taxonomy, false ); ?>
					<span id="<?php echo $taxonomy; ?>-ajax-response"></span>
				</p>
			</div>
		<?php endif; ?>
	</div>
	<?php
	}

}
Category_Checklist::init();
}


