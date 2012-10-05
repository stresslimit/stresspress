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


/*-----------------------------------
   wp-config.php stuff
-------------------------------------*/

// define('WP_POST_REVISIONS', 5 );		// Limit post revisions: int or false
// define( 'DISALLOW_FILE_EDIT', true );	// Disable file editor
define( 'EMPTY_TRASH_DAYS', 1 );		// Purge trash interval
define( 'AUTOSAVE_INTERVAL', 60 );		// Autosave every N seconds


/*-----------------------------------
   admin stuff
-------------------------------------*/

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
	Frontend functions
-------------------------------------*/

// add conditional for login page
function is_login_page() {
    return in_array($GLOBALS['pagenow'], array('wp-login.php', 'wp-register.php'));
}

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
	Utility functions
-------------------------------------*/

// make cleaner better permalink urls; this might be solved in core by now [todo] check wp/trunk
add_filter('editable_slug', 'sld_url_cleaner_clean');
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

add_filter( 'admin_body_class', 'sld_admin_body_class' );
// add post type class to body admin 
function sld_admin_body_class( $classes ) {
	global $wpdb, $post;
	$post_type = get_post_type( $post->ID );
	if ( is_admin() ) {
		$classes .= 'type-' . $post_type;
	}
	return $classes;
}

add_filter( 'body_class', 'sld_page_slug_body_class' );
function sld_page_slug_body_class( $classes ) {
    global $post;
    if ( !empty( $post ) )
        $classes[] = $post->post_type . '-' . $post->post_name;
    return $classes;
}

// add shortcode to template_url to be able to reference
add_shortcode( 'template_url', 'sld_template_url' );
function sld_template_url( $atts ) {
	return get_bloginfo('template_url');
}



/*-----------------------------------
   Stresslimit admin branding
-------------------------------------*/

function sld_admin_styles() { ?>
	<style>
	/* nice client logo for wp login screen	*/
	#login h1 a { background:url('<?php echo get_bloginfo( 'template_url' ) ?>/images/logo-admin.png') left top no-repeat; height:20px; margin-left:35px; background-size:auto; }
	.login form { position:relative; }
	/* nice stresslimit kut-korners	*/
	.login form:after { content:"."; text-indent:-999em; display:block; position:absolute; right:-8px; bottom:-10px; width:26px; height:74px; 
/*	background:url('<?php bloginfo('template_url') ?>/images/stresscorner-login.png') right bottom no-repeat;*/
	background:url('data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABoAAABKCAIAAAAqptNuAAAAGXRFWHRTb2Z0d2FyZQBBZG9iZSBJbWFnZVJlYWR5ccllPAAAAj5JREFUeNqsmNuOwjAMREla4P9/EyHxgBDXwg54GZkmaRInfkCrhR7Zk7FjcK/Xa1Ufu93Oe7/dbvE6DANenXP4vzewkMHj8RjHEX+7T/AtC+56vb6f9F5w+i0L7nK5oECymCBejThU6r7RlN3z+ZymqRsOqenqZu9W487ns5wpVWs62dvtJpXKyTIsvoNq0A7H2qdYVEoWO6EJx2bQNRpx9/udFgmFq8OBhW51KsLPeJtwHXDSW6FwFt+hzJRwluzgXpoj9HA1TlukQ3YYmev1OpTM4js0Fqa5HOtCaqU4DqUFx9XhpNI+2UE4PeNSwhXhoBq0o0WWP+xLKuUhdCg2dFyq0iKc9FZ0ulXjdG+lGqsCh0r18tCqXcpxKajPbkrct7LCZXBwLyUrYWVw+sIvES6fXXS1sYyA6RO6H5qKXd6ULDg9fltPlsJFN6U6HOwG09FurcVyKBX6I4PLbkp1uOymVIEr2ZQqcFJpTxwdl9qUSnEcSrV5xXEylBglA30JV7gpleK4YmYv/DxOfwsxpDbHzYRrLZYWMUy6eHbRC98yApCaXqZXpvA6NT3gWo8C6wiE0yyDfJ4Wkd4yG/gHdzqdZHkwnGYEp1NrSdBzZEYvmlqoQ16HwwFzabPZDJ+QQWK08X6/F7uhW2erjaXJ5Ac3PCn3Q+tRIIRouwkjuFAymfLGk+UQZ+HmYlHi6L/RWOn7wePxKNnNZomN6NBevLQ6ZIe7hj9kNgr3fly+JrWD/iHo1lW/6Iz7E2AAoVgTmcr1bUIAAAAASUVORK5CYII=') right bottom no-repeat;
	 }
	/* stresslimit logo on dashboard */
	#icon-index { margin:8px 8px 0 8px; background:transparent url('data:image/png;base64,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') 0 0 no-repeat; }
	</style>
	<?php
}
add_action('login_head', 'sld_admin_styles');
add_action('admin_head', 'sld_admin_styles');

// login logo link url
function login_header_url() {
	return home_url();
}
add_filter( 'login_headerurl', 'login_header_url' );

// admin footer branding
function sld_admin_footer_brand() {
  	return date("Y") .' <a href="http://stresslimitdesign.com">stresslimitdesign</a> <a href="http://creativecommons.org/licenses/by-nc-sa/3.0/">cc</a>';
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


