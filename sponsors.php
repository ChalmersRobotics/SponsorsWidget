<?php

/*
@link              http://tim.gremalm.se/
@since             1.0.0
@package           Sponsorswidget

@wordpress-plugin
Plugin Name:       SponsorsWidget
Plugin URI:        https://github.com/ChalmersRobotics/SponsorsWidget
Description:       A widget for Wordpress where you can list sponsors.
Version:           1.0.0
Author:            Tim Gremalm
Author URI:        http://tim.gremalm.se/
License:           GPL-3.0+
License URI:       http://www.gnu.org/licenses/gpl-3.0.txt
Text Domain:       sponsorswidget
Domain Path:       /languages
*/

//Widget
class Sponsors_Widget extends WP_Widget {
	function __construct() {
		parent::__construct(
			'sponsors_widget', // Base ID
			esc_html__( 'Sponsors Widget', 'text_domain' ), // Name
			array( 'description' => esc_html__( 'Adds sponsors in a widget', 'text_domain' ), )
		);
	}

	//Front-end display of widget
	public function widget( $args, $instance ) {
		echo $args['before_widget'];
		if ( ! empty( $instance['title'] ) ) {
			echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
		}
		/*
		*/
		echo $args['after_widget'];
	}

	//Back-end widget form
	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : esc_html__( 'New title', 'text_domain' );
		?>
		<p>
		<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_attr_e( 'Title:', 'text_domain' ); ?></label>
		<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<?php
	}

	//Sanitize widget form values as they are saved
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';

		return $instance;
	}
}
add_action('widgets_init', create_function('', 'return register_widget("Sponsors_Widget");'));

//Meta box for post type sponsor
function wpt_add_sponsor_metaboxes( $post ) {
	//add_meta_box( $id, $title, $callback, $page, $context, $priority, $callback_args );
	add_meta_box(
		'wpt_sponsor',
		'sponsor parameters',
		'wpt_sponsor',
		'sponsor',
		'normal',
		'default'
	);
}
function wpt_sponsor() {
	global $post;
	wp_enqueue_script('jquery');
	wp_enqueue_script('thickbox');
	wp_enqueue_style('thickbox');
	wp_enqueue_script('media-upload');
	wp_enqueue_script('wptuts-upload');

	//Nonce field to validate form request came from current site
	wp_nonce_field( basename( __FILE__ ), 'sponsor_fields' );

	//Parameter sponsor url
	$sponsorurl = get_post_meta( $post->ID, 'sponsorurl', true );
	echo '<label>' . esc_attr_e( 'URL to sponsor:', 'text_domain' ) . '</label>';
	echo '<input type="text" name="sponsorurl" id="sponsorurl" value="' . esc_url( $sponsorurl )  . '" class="widefat" />';

	//Parameter for movement url
	$sponsorimage = get_post_meta( $post->ID, 'sponsorimage', true );
	echo '<label>' . esc_attr_e( 'URL to sponsor image:', 'text_domain' ) . '</label>';
	echo '<input type="text" name="sponsorimage" id="sponsorimage" value="' . esc_url( $sponsorimage )  . '" class="widefat" />';
	echo '<input id="upload_logo_button" type="button" class="button" value="Upload Logo" />';
	echo '<span class="description">Upload an image for the logotype</span>';
	echo '<div id="logopreview"></div>';
	MediaUploadScript();
}
function MediaUploadScript() {
	?>
	<script type="text/javascript" language="javascript">// <![CDATA[
		jQuery(document).ready(function($) {
			$('#upload_logo_button').click(function() {
				//Thickbox open Media Uploader
				tb_show('Upload a logo', 'media-upload.php?type=image&TB_iframe=true&post_id=0', false);
				return false;
			});
			window.send_to_editor = function(html) {
				imgurl = jQuery('img', html).attr('src');
				jQuery('#sponsorimage').val(imgurl);
				jQuery('#logopreview').html(html);
				jQuery('#logopreview img').css("max-width", "100%");
				tb_remove();
			};
			if (jQuery('#sponsorimage').val()) {
				jQuery('#logopreview').prepend('<img class="alignnone" src="' + jQuery('#sponsorimage').val() + '" style="max-width: 100%" />');
			}
		});
		// ]]>
	</script>
	<?php
}
function save_meta_box_sponsor( $post_id, $post ) {
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return $post_id;
	}

	//Verify this came from the our screen and with proper authorization, because save_post can be triggered at other times.
	if ( ! wp_verify_nonce( $_POST['sponsor_fields'], basename(__FILE__) ) ) {
		return $post_id;
	}
	if ( ! isset( $_POST['sponsorurl'] ) || ! isset( $_POST['sponsorimage'] ) ) {
		return $post_id;
	}

	//This sanitizes the data from the field and saves it into an array $sponsor_meta
	$sponsor_meta['sponsorurl'] = esc_textarea( $_POST['sponsorurl'] );
	$sponsor_meta['sponsorimage'] = esc_textarea( $_POST['sponsorimage'] );

	foreach ( $sponsor_meta as $key => $value ) :
		//Don't store custom data twice
		if ( 'revision' === $post->post_type ) {
			return;
		}
		if ( get_post_meta( $post_id, $key, false ) ) {
			update_post_meta( $post_id, $key, $value );
		} else {
			add_post_meta( $post_id, $key, $value);
		}
		if ( ! $value ) {
			delete_post_meta( $post_id, $key );
		}
	endforeach;
}
add_action('save_post', 'save_meta_box_sponsor', 1, 2);

//Post type sponsors
function register_cpt_sponsors() {
	$labels = array(
		'name' => __( 'Sponsors', 'sponsor' ),
		'singular_name' => __( 'Sponsor', 'sponsor' ),
		'add_new' => __( 'Add New', 'sponsor' ),
		'add_new_item' => __( 'Add New Sponsor', 'sponsor' ),
		'edit_item' => __( 'Edit Sponsor', 'sponsor' ),
		'new_item' => __( 'New Sponsor', 'sponsor' ),
		'view_item' => __( 'View Sponsor', 'sponsor' ),
		'search_items' => __( 'Search Sponsors', 'sponsor' ),
		'not_found' => __( 'No sponsors found', 'sponsor' ),
		'not_found_in_trash' => __( 'No sponsors found in Trash', 'sponsor' ),
		'parent_item_colon' => __( 'Parent sponsor:', 'sponsor' ),
		'menu_name' => __( 'Sponsors', 'sponsor' ),
	);

	$args = array(
		'labels' => $labels,
		'hierarchical' => false,
		'description' => 'List of sponsors',
		'supports' => array( 'title' ),
		'public' => false,
		'show_ui' => true,
		'show_in_menu' => true,
		'menu_position' => 71,
		'show_in_nav_menus' => true,
		'publicly_queryable' => true,
		'exclude_from_search' => true,
		'has_archive' => false,
		'query_var' => true,
		'can_export' => true,
		'rewrite' => true,
		'capability_type' => 'post',
		'register_meta_box_cb' => 'wpt_add_sponsor_metaboxes',
	);

	register_post_type( 'sponsor', $args );
}
add_action( 'init', 'register_cpt_sponsors' );

