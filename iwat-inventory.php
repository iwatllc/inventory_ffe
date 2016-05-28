<?php

/*
Plugin Name: iWAT Inventory
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: This plugin is used to import data into the Inventory Post.
Version: 1.0
Author: rfulcher
Author URI: http://www.iwatllc.com
License: A "Slug" license name e.g. GPL2
*/

add_action( 'admin_init', 'iwat_import_plugin_settings' );

add_action('admin_menu', 'iwat_import_plugin_menu');

function iwat_import_plugin_menu() {
	add_menu_page('iWAT Inventory Plugin Settings', 'Inventory Import Settings', 'administrator', 'iwat-import-plugin-settings', 'iwat_import_plugin_settings_page', 'dashicons-admin-generic');
}

function iwat_import_plugin_settings() {

	register_setting( 'iwat-import-plugin-settings-group', 'last_run_info' );
	register_setting( 'iwat-import-plugin-settings-group', 'file_path' );
	register_setting( 'iwat-import-plugin-settings-group', 'image_path' );
	register_setting( 'iwat-import-plugin-settings-group', 'equipment_file' );
	register_setting( 'iwat-import-plugin-settings-group', 'datastart' );
}

function iwat_import_plugin_settings_page() {

		?>
		<div class="wrap">
		<h2>Import Program Details</h2>

		<form method="post" action="options.php">
		    <?php settings_fields( 'iwat-import-plugin-settings-group' ); ?>
			<?php do_settings_sections( 'iwat-import-plugin-settings-group' ); ?>
		<table class="form-table">
			<tr valign="top">
				<th scope="row">File Path</th>
				<td><input type="text" name="file_path" value="<?php echo esc_attr( get_option('file_path') ); ?>" /></td>
			</tr>

			<tr valign="top">
				<th scope="row">Image Path</th>
				<td><input type="text" name="image_path" value="<?php echo esc_attr( get_option('image_path') ); ?>" /></td>
			</tr>

			<tr valign="top">
				<th scope="row">Equipment File</th>
				<td><input type="text" name="equipment_file" value="<?php echo esc_attr( get_option('equipment_file') ); ?>" /></td>
			</tr>

			<tr valign="top">
				<th scope="row">Data Starts on Line</th>
				<td><input type="text" name="datastart" value="<?php echo esc_attr( get_option('datastart') ); ?>" /></td>
			</tr>

			<tr valign="top">
				<th scope="row">Last Run Status</th>
				<td><?php echo esc_attr( get_option('last_run_info') ); ?></td>
			</tr>
		</table>

		<?php submit_button(); ?>

		</form>
		</div>

		<h2>Run Import Process</h2>
		<?php
		// Check whether the button has been pressed AND also check the nonce
		if (isset($_POST['import_inventory']) && check_admin_referer('import_inventory_button_clicked')) {
			// the button has been pressed AND we've passed the security check
			iwat_import_inventory();
		}
		?>

		<form method="post" action="options.php?page=iwat-import-plugin-settings">
			<input type="hidden" value="true" name="import_inventory" />
			<?php
				wp_nonce_field('import_inventory_button_clicked');
				submit_button('Import Inventory');
			?>
		</form>



		<?php
}



function iwat_import_inventory() {

	iwat_delete_inventory();

	update_option( 'last_run_info', date("Y-m-d h:i:sa") );

	$lines = file(get_option('file_path') . "/" . get_option('equipment_file'));

	foreach ($lines as $inventory_line => $inventory) {
		// First start importing on the line specified in the form.  skipping headers.
		if ($inventory_line >= get_option('datastart')) {
			$inventory_fields = explode(',', $inventory);

			$slug = $inventory_fields[11];
			$author_id = 1;
			$title = $inventory_fields[11];
			$content = $inventory_fields[2] . '<br>' . $inventory_fields[3] . '<br>' . $inventory_fields[6] . '<br>' ;

			$post_id = wp_insert_post(
				array(
					'comment_status'	=>	'closed',
					'ping_status'		=>	'closed',
					'post_author'		=>	str_replace('"', "", $author_id),
					'post_name'		    =>	str_replace('"', "", $slug),
					'post_title'		=>	str_replace('"', "", $title),
					'post_content'      =>  str_replace('"', "", $content),
					'post_status'		=>	'publish',
					'post_type'		    =>	'inventory'
				)
			);

			update_field('field_52dee641d74b8', '64', $post_id);  //Category (Taxonomy)    ?
			update_field('field_52b358c91215d', '34', $post_id);  //Status (Taxonomy)      ?
			update_field('field_523c0dd64b63d', '0', $post_id);  //Features (Boolean)      ?
			update_field('field_523af9693ded3', str_replace('"', "", $inventory_fields[17]), $post_id);  //Manufacturer (Text)
			update_field('field_523af9783ded4', str_replace('"', "", $inventory_fields[19]), $post_id);  //Year (Text)
			update_field('field_523af9813ded5', str_replace('"', "", $inventory_fields[18]), $post_id);  //Model (Text)/
			update_field('field_523af98a3ded6', str_replace('"', "", $inventory_fields[26]), $post_id);  //Stock (Text)
			update_field('field_528a4967969a3', str_replace('"', "", $inventory_fields[8]), $post_id);  //Hours
			update_field('field_523af98f3ded7', str_replace('"', "", $inventory_fields[28]), $post_id);  //Price

			$image_files = trim(str_replace('"', "", $inventory_fields[35]));
			if (!empty($image_files)){
				iwat_process_images($inventory_fields[35], $post_id);
			}

		}

	}
}

function iwat_delete_inventory(){

	$args = array(
		'numberposts' => -1,
		'post_type' =>'inventory'
	);

	$posts = get_posts( $args );

	if (is_array($posts)) {
		foreach ($posts as $post) {
			wp_delete_post( $post->ID, $bypass_trash = true);
		}
	}


}


function iwat_process_images($image_string, $post_id){

	$images = explode('|', $image_string);

	foreach ($images as $image_number => $image){
		if ($image_number == 0) {
			// if the it is the first image then set it as featured image.
			iwat_set_Featured_Image( get_option('image_path') . "/" . str_replace('"', "",$image),   $post_id );
		} else {
			$gallery_images = $images;
			unset($gallery_images[0]); // Remove the image we used for the featured image.
			// Add images to the gallery
			iwat_set_Gallery_Image( $gallery_images,   $post_id );
		}
	}

}


function iwat_set_Featured_Image( $image_url, $post_id  ){
	$upload_dir = wp_upload_dir();
	$image_data = file_get_contents($image_url);
	$filename = basename($image_url);
	if(wp_mkdir_p($upload_dir['path']))     $file = $upload_dir['path'] . '/' . $filename;
	else                                    $file = $upload_dir['basedir'] . '/' . $filename;
	file_put_contents($file, $image_data);

	$wp_filetype = wp_check_filetype($filename, null );
	$attachment = array(
		'post_mime_type' => $wp_filetype['type'],
		'post_title' => sanitize_file_name($filename),
		'post_content' => '',
		'post_status' => 'inherit'
	);
	$attach_id = wp_insert_attachment( $attachment, $file, $post_id );
	require_once(ABSPATH . 'wp-admin/includes/image.php');
	$attach_data = wp_generate_attachment_metadata( $attach_id, $file );
	$res1= wp_update_attachment_metadata( $attach_id, $attach_data );
	$res2= set_post_thumbnail( $post_id, $attach_id );
}


/*
 * This function takes a list of image names and then uploads them
 * and creates and array $gallery and uses one update call to the
 * custom field for gallery to add the images.
 */
function iwat_set_Gallery_Image( $gallery_images, $post_id  ){
	$upload_dir = wp_upload_dir();
	$gallery = array();

	foreach ($gallery_images as $image_number => $image) {

		$image_data = file_get_contents(get_option('image_path') . "/" . str_replace('"', "",$image));
		$filename = basename(get_option('image_path') . "/" . str_replace('"', "",$image));

		if (wp_mkdir_p($upload_dir['path'])) $file = $upload_dir['path'] . '/' . $filename;
		else                                    $file = $upload_dir['basedir'] . '/' . $filename;
		file_put_contents($file, $image_data);

		$wp_filetype = wp_check_filetype($filename, null);
		$attachment = array(
			'post_mime_type' => $wp_filetype['type'],
			'post_title' => sanitize_file_name($filename),
			'post_content' => '',
			'post_status' => 'inherit'
		);

		$attach_id = wp_insert_attachment($attachment, $file, $post_id);
		require_once(ABSPATH . 'wp-admin/includes/image.php');
		$attach_data = wp_generate_attachment_metadata($attach_id, $file);
		wp_update_attachment_metadata($attach_id, $attach_data);

		$gallery[$image_number] = $attach_id;
	}

	update_field('field_523b021698f59', $gallery, $post_id);

}

//function iwat_import_inventory_old() {
//
//	update_option( 'last_run_info', date("Y-m-d h:i:sa") );
//
//	$post_id = -1;
//
//	$slug = 'example-inventory';
//	$author_id = 1;
//	$title = 'Sample Inventory Item Inserted 7';
//	$content = 'Sample Inventory Content 7';
//
//
//	// Search Args
//	$args = array(
//		'numberposts'	=> -1,
//		'post_type'		=> 'inventory',
//		'meta_key'		=> 'stock',
//		'meta_value'	=> '78-777-7777'
//	);
//
//	$the_query = new WP_Query( $args );
//
//	if( $the_query->have_posts() ){
//		while( $the_query->have_posts() ) {
//			$the_query->the_post();
//
//
//			$post_id = get_the_id();
//
//			// Update post
//			$my_post = array(
//				'ID'                => $post_id,
//				'comment_status'	=>	'closed',
//				'ping_status'		=>	'closed',
//				'post_author'		=>	$author_id,
//				'post_name'		    =>	$slug,
//				'post_title'        => 'This is the updated post title. 2',
//				'post_content'      => 'This is the updated content. 2',
//				'post_status'		=>	'publish',
//				'post_type'		    =>	'inventory'
//			);
//
//			// Update the post into the database
//			//wp_update_post( $my_post );
//			wp_insert_post( $my_post );
//
//			update_field('field_52dee641d74b8', '64', $post_id);  //Category (Taxonomy)
//			update_field('field_52b358c91215d', '34', $post_id);  //Status (Taxonomy)
//			update_field('field_523c0dd64b63d', '0', $post_id);  //Features (Boolean)
//			update_field('field_523af9693ded3', 'King James Updated', $post_id);  //Manufacturer (Text)
//			update_field('field_523af9783ded4', '1977 Updated', $post_id);  //Year (Text)
//			update_field('field_523af9813ded5', '7 Series Updated', $post_id);  //Model (Text)
//			update_field('field_523af98a3ded6', '77-777-7777', $post_id);  //Stock (Text)
//			update_field('field_528a4967969a3', '700', $post_id);  //Hours
//			update_field('field_523af98f3ded7', '77.77', $post_id);  //Price
//
//		}
//
//	} else {
//
//		$post_id = wp_insert_post(
//			array(
//				'comment_status'	=>	'closed',
//				'ping_status'		=>	'closed',
//				'post_author'		=>	$author_id,
//				'post_name'		    =>	$slug,
//				'post_title'		=>	$title,
//				'post_content'      =>  $content,
//				'post_status'		=>	'publish',
//				'post_type'		    =>	'inventory'
//			)
//		);
//
//		update_field('field_52dee641d74b8', '64', $post_id);  //Category (Taxonomy)
//		update_field('field_52b358c91215d', '34', $post_id);  //Status (Taxonomy)
//		update_field('field_523c0dd64b63d', '0', $post_id);  //Features (Boolean)
//		update_field('field_523af9693ded3', 'King James', $post_id);  //Manufacturer (Text)
//		update_field('field_523af9783ded4', '1977', $post_id);  //Year (Text)
//		update_field('field_523af9813ded5', '7 Series', $post_id);  //Model (Text)/
//		update_field('field_523af98a3ded6', '78-777-7777', $post_id);  //Stock (Text)
//		update_field('field_528a4967969a3', '700', $post_id);  //Hours
//		update_field('field_523af98f3ded7', '77.77', $post_id);  //Price
//
//		iwat_set_Featured_Image( '/users/rfulcher/Documents/Bronco.jpg',   $post_id );
//		iwat_set_Gallery_Image( '/users/rfulcher/Documents/IMG_0113.jpg',   $post_id );
//		iwat_set_Gallery_Image( '/users/rfulcher/Documents/IMG_0114.jpg',   $post_id );
//
//
//	}
//
//	wp_reset_query();
//
//
//
//}