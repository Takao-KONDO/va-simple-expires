<?php
/*
Plugin Name: Simple expires
Plugin URI: http://www.wordpress.org
Description: Add expire date for post and page.
Author: Andrea Bersi
Version: 0.10
Author URI: http://www.andreabersi.com/
*/

/*
Copyright (c) 2010 Andrea Bersi.

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in
all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
THE SOFTWARE.
*/

define('SIMPLE_EXPIRES_PLUGIN_URL', WP_PLUGIN_URL.'/'.dirname(plugin_basename(__FILE__)));
define('SIMPLE_EXPIRES_DOMAIN', 'simple-expires');

	function deactivation()
	{
	    //  remove rows from wp_postmeta tables
		global $wpdb;
		$wpdb->query( $wpdb->prepare("DELETE FROM $wpdb->postmeta WHERE meta_key='scadenza-enable' OR meta_key='scadenza-date'" ));
	}
	
	add_action('admin_menu', 'loadAdmin');
	function loadAdmin()
    {
        load_plugin_textdomain(
            SIMPLE_EXPIRES_DOMAIN, PLUGINDIR.'/'.dirname(plugin_basename(__FILE__)).'/lang', dirname(plugin_basename(__FILE__)).'/lang'
        );

	  wp_enqueue_script('my_validate', SIMPLE_EXPIRES_PLUGIN_URL.'/js/jquery.validate.pack.js', array('jquery'));
	 	
	}
	
	
	// 07/02/2011 by Riboni Igor
	//enable expires in custom posts
	function expirationdate_meta_custom() {
	    $custom_post_types = get_post_types();
	    foreach ($custom_post_types as $t) {
	           add_meta_box('scadenza_plugin', __('Expire', SIMPLE_EXPIRES_DOMAIN), 'scadenza_', $t, 'side', 'low');
	    }
	}
	add_action ('add_meta_boxes','expirationdate_meta_custom');
	// end Riboni Igor
	
	function validate_data(){
		?>
			<script type="text/javascript">
			jQuery.extend(jQuery.validator.messages, {
			       required: "<?php _e('Field required', SIMPLE_EXPIRES_DOMAIN)?>",number: "<?php _e('Invalid number', SIMPLE_EXPIRES_DOMAIN)?>",min: jQuery.validator.format("<?php _e('Please enter a value greater than or equal to {0}', SIMPLE_EXPIRES_DOMAIN)?>")
			});
			jQuery().ready(function() {
			    jQuery("#post").validate(
				{
					rules:{anno:{number:true,min:2011},ore:{number:true,max:24},min:{number:true,max:60}}
				}
				);
			});
			</script>
		<?php
	}
	add_action("admin_head","validate_data");
	
	function validate_css(){
		?>
		<style>
		.error{
			color:red;font-weight:bold;	}
		</style>
		<?php
	}
	
	add_action("admin_head","validate_css");

	function simple_expires(){
		global $wpdb;
		//20 june 2011: bug fix by Kevin Roberts for timezone
		$querystring = 'SELECT postmetadate.post_id 
			FROM 
			' .$wpdb->postmeta. ' AS postmetadate, 
			' .$wpdb->postmeta. ' AS postmetadoit, 
			' .$wpdb->posts. ' AS posts 
			WHERE postmetadoit.meta_key = "scadenza-enable" 
			AND postmetadoit.meta_value = "1" 
			AND postmetadate.meta_key = "scadenza-date" 
			AND postmetadate.meta_value <= "' . current_time("mysql") . '" 
			AND postmetadate.post_id = postmetadoit.post_id 
			AND postmetadate.post_id = posts.ID 
			AND posts.post_status = "publish"';
		$result = $wpdb->get_results($querystring);

		// Act upon the results
		if ( ! empty( $result ) )
		{
				//get the admin email
				$site_admin_email = "Admin <".get_option('admin_email').">";
				// Proceed with the updating process	      	        
				// step through the results
				foreach ( $result as $cur_post )
				{							
						$update_post = array('ID' => $cur_post->post_id);
			  	        // Get the Post's ID into the update array
						$update_post['post_status'] = 'draft';
			  	        wp_update_post( $update_post ) ;
						// get post data
						$post_data = get_post( $cur_post->post_id, ARRAY_A );
						// get the author ID
						$auth_id = $post_data['post_author'];
						// get the author email address
						$auth_info = get_userdata( $auth_id );
						$auth_email = "Author <" . $auth_info->user_email . ">";
						// get the post ID
						$post_id = $post_data['ID'];
						// get the post title
						$post_title = $post_data['post_title'];
						// get / create the post viewing URL
						$post_view_url = $post_data['guid'];
						// pack it up into our array
						// make a new item array
						$new_item['ID'] = $post_id;
						$new_item['post_title'] = $post_title;
						$new_item['view_url'] = $post_view_url;

						// add the post to the notification list
						$notify_list[$auth_email][] = $new_item;
						// See if the site_admin_email matches the author email.
						// If it does, the admin is already being notified, so we should not continue
						if( $site_admin_email != $auth_email )
						{
							// add the post to the notification list for the admin user
							$notify_list[$site_admin_email][] = $new_item;
						}
			
						$blog_name = htmlspecialchars_decode( get_bloginfo('name'), ENT_QUOTES );
						foreach( $notify_list as $user )
						{
							// reset $usr_msg
							$usr_msg = __("The following notifications come from the website:", SIMPLE_EXPIRES_DOMAIN). "$blog_name\n";
							// tell them why they are receiving the notification
							$usr_msg .= __("These notifications indicate items Simple Expires has automatically applied expiration changes to.", SIMPLE_EXPIRES_DOMAIN)."\n";
							$usr_msg .= "====================\n";
							// get this user's email address -- it is the key for the current element, $user
							$usr_email = key($notify_list);

							if( ! empty( $usr_email ) )
							{
								// step through elements in the user's array
								foreach( $user as $post )
								{						
									$usr_msg .= __("The Post / Page entitled", SIMPLE_EXPIRES_DOMAIN)." '" . $post['post_title'] . ",' \nwith the post_id of '" . $post['ID'] . ".'\n";
									$usr_msg .= __("Unless the content is disabled, it can be viewed at ", SIMPLE_EXPIRES_DOMAIN) . $post['view_url'] . ".\n";
									$usr_msg .= "=====\n";
								} // end foreach stepping through list of posts for a user

								// send $msg to $user_email
								// Build subject line
								$subject = "$why_notify ".__("Notification from", SIMPLE_EXPIRES_DOMAIN)." $blog_name";
								// Send the message
								if( wp_mail( $usr_email, $subject, $usr_msg ) == 1 )
								{
									// SUCCESS
									// for debug
									
								}
								else
								{
									// FAILED
									// for debug
									
								}

							} // end if checking that email address existed

						} // end foreach stepping through list of users to notify
						
						
				} // end foreach
		} // endif
	}

add_action( 'init', 'simple_expires' );

/* Define the custom box */
add_action('add_meta_boxes', 'scadenza_add_custom_box');

/* Do something with the data entered */
add_action('save_post', 'scadenza_save_postdata');


/* Adds a box to the main column on the Post and Page edit screens */
function scadenza_add_custom_box() {
    add_meta_box( 'scadenza_plugin', __('Expire', SIMPLE_EXPIRES_DOMAIN), 'scadenza_', 'page','side' ,'high');
	add_meta_box( 'scadenza_plugin', __('Expire', SIMPLE_EXPIRES_DOMAIN), 'scadenza_', 'post','side' ,'high');
}

/* Prints the box content */
function scadenza_($post) {
  global $wp_locale;
  // Use nonce for verification
  wp_nonce_field( plugin_basename(__FILE__), 'simple-expires-nonce' );
	
 $scadenza = get_post_meta($post->ID,'scadenza-date',true);
 $time_adj = time() + (365 * 24 * 60 * 60);//current_time('timestamp');
 $giorno = (! empty($scadenza)) ? mysql2date( 'd', $scadenza, false ) : gmdate( 'd', $time_adj );
 $mese = (! empty($scadenza)) ? mysql2date( 'm', $scadenza, false ) : gmdate( 'm', $time_adj );
 $anno = (! empty($scadenza)) ? mysql2date( 'Y', $scadenza, false ) : gmdate( 'Y', $time_adj );
 $ore = (! empty($scadenza)) ? mysql2date( 'H', $scadenza, false ) : gmdate( 'H', $time_adj );
 $min = (! empty($scadenza)) ? mysql2date( 'i', $scadenza, false ) : gmdate( 'i', $time_adj );
	

	$month = "<select  id=\"mese\" name=\"mese\">\n";
	for ( $i = 1; $i < 13; $i = $i +1 ) {
		$month .= "\t\t\t" . '<option value="' . zeroise($i, 2) . '"';
		if ( $i == $mese )
			$month .= ' selected="selected"';
			$month .= '>' . $wp_locale->get_month_abbrev( $wp_locale->get_month( $i ) ) . "</option>\n";
	}
	$month .= '</select>';
		
	
	echo'<div id="timestampdiv_scadenza" class="">';
	$the_data = get_post_meta( $post->ID, 'scadenza-enable', true );
	// Checkbox for scheduling this Post / Page, or ignoring
	$items = array( __('Enabled', SIMPLE_EXPIRES_DOMAIN), __('Disabled', SIMPLE_EXPIRES_DOMAIN));
	$value = array(1,0);
	$i=0;
	foreach( $value as $item)
	{
		$checked = (( $the_data == $item ) || ( $the_data=='')) ? ' checked="checked" ' : '';
		echo "<label><input ".$checked." value='$item' name='scadenza-enable' id='scadenza-enable' type='radio' /> $items[$i]</label>  ";
		$i++;
	} // end foreach
	echo "<br />\n<br />\n";

	echo '<div class="">
	'.$month.'
	<input type="text" class="number" id="giorno" name="giorno" value="'.$giorno.'" size="2" maxlength="2" tabindex="4" autocomplete="off" />, 
	<input type="text"  id="anno" name="anno" value="'.$anno.'" size="4" maxlength="4" tabindex="4" autocomplete="off" /> @ 
	<input type="text"  id="ore" name="ore" value="'.$ore.'" size="2" maxlength="2" tabindex="4" autocomplete="off" /> : 
	<input type="text"  id="min" name="min" value="'.$min.'" size="2" maxlength="2" tabindex="4" autocomplete="off" />
	</div>
	</div>';
	echo "<p>".__('Insert a date for expire', SIMPLE_EXPIRES_DOMAIN)."</p>";
}

/* When the post is saved, saves our custom data */
function scadenza_save_postdata( $post_id ) {

  // verify this came from the our screen and with proper authorization,
  // because save_post can be triggered at other times

  if ( !wp_verify_nonce( $_POST['simple-expires-nonce'], plugin_basename(__FILE__) )) {
    return $post_id;
  }

  // verify if this is an auto save routine. If it is our form has not been submitted, so we dont want
  // to do anything
  if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) 
    return $post_id;


  // Check permissions
  if ( 'page' == $_POST['post_type'] ) {
    if ( !current_user_can( 'edit_page', $post_id ) )
      return $post_id;
  } else {
    if ( !current_user_can( 'edit_post', $post_id ) )
      return $post_id;
  }

  // OK, we're authenticated: we need to find and save the data

  $mydata = $_POST['anno']."-".$_POST['mese']."-".zeroise($_POST['giorno'],2)." ".zeroise($_POST['ore'],2).":".$_POST['min'].":00";
($mydata);

	$enabled = $_POST['scadenza-enable'];
  // Do something with $mydata 
 
	update_post_meta($post_id,'scadenza-date', $mydata);
	update_post_meta( $post_id, 'scadenza-enable', $enabled );
   return $mydata;
}


