<?php
/*
Plugin Name: Cache Images
Plugin URI: http://wordpress.org/extend/plugins/cache-images/
Description: Goes through your posts and gives you the option to cache all hotlinked images from a domain locally in your upload folder
Version: 2.0
Author: Matt Mullenweg
Author URI: http://ma.tt/
WordPress Version Required: 2.8
*/

/**
 * Yes, we're localizing the plugin.  This partly makes sure non-English
 * users can use it too.  To translate into your language use the
 * cache-images.pot file in /languages folder.  Poedit is a good tool to for translating.
 * @link http://poedit.net
 *
 */
function cache_images_init() {
	load_plugin_textdomain( 'cache-images', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'cache_images_init' );

function mm_ci_add_pages() {
	
	add_management_page( __('Cache Remote Images', 'cache-images'), __('Cache Remote Images', 'cache-images'), 8, __FILE__,
'mm_ci_manage_page');
}

/**
 * Search a multiple array
 * via http://www.php.net/manual/en/function.in-array.php#20594
 */
function in_multi_array($needle, $haystack)
{
    $in_multi_array = false;
    if(in_array($needle, $haystack))
    {
        $in_multi_array = true;
    }
    else
    {   
        for($i = 0; $i < sizeof($haystack); $i++)
        {
            if(is_array($haystack[$i]))
            {
                if(in_multi_array($needle, $haystack[$i]))
                {
                    $in_multi_array = true;
                    break;
                }
            }
        }
    }
    return $in_multi_array;
}

function mm_ci_manage_page() {
	global $wpdb;
?>
<div class="wrap">
<h2><?php _e('Remote Image Caching', 'cache-images'); ?></h2>
<?php if ( !isset($_POST['step']) ) : ?>
<p><?php _e('Here&#8217;s how this works:', 'cache-images'); ?></p>
<ol>
	<li><?php _e('Click the button below and we&#8217;ll scan all of your posts for remote images', 'cache-images'); ?></li>
	<li><?php _e('Then you&#8217;ll be presented with a list of domains. For each domain, press button Cache from this domain', 'cache-images'); ?></li>
	<li><?php _e('The images will be copied to your upload directory, the links in your posts will be updated to the new location, and images will be added to your media library, associated to first post from where they are found.', 'cache-images'); ?></li>
</ol>
<?php
	/*
	* Show notice for WP Smush.it
	*/
	if (!function_exists('wp_smushit')) {
		?><div class="wp-smushit-notice">
		<strong><?php _e( "Tip:", "gse_textdomain" );?></strong><br />
		<?php _e( "You can install plugin WP Smush.it to reduce image file sizes and improve performance using the Smush.it API.", "cache-images" );
		echo sprintf(__(" (<a href='%s'>read more about WP Smush.it</a>)", "cache-images" ), "http://dialect.ca/code/wp-smushit/");?><br />
		<?php echo sprintf(__("<a href='%s' class='thickbox'>Install WP Smush.it</a>", "cache-images" ),  esc_url(admin_url('plugin-install.php?tab=plugin-information&plugin=wp-smushit&TB_iframe=true&width=600&height=550')));?><br />
		</div>
	<?php }
?>
<form action="" method="post">
<p class="submit">
	<input name="step" type="hidden" id="step" value="2">
	<input type="submit" name="Submit" value="<?php _e('Scan &raquo;', 'cache-images'); ?>" />
</p>
</form>
<?php endif; ?>

<?php if ('2' == $_POST['step']) : ?>
<?php
$posts = $wpdb->get_results("SELECT post_content FROM $wpdb->posts WHERE post_content LIKE ('%<img%')");

if ( !$posts ) 
	die(__("No posts with images were found.", "cache-images") );

foreach ($posts as $post) :
	preg_match_all('|<img.*?src=[\'"](.*?)[\'"].*?>|i', $post->post_content, $matches);
	
	foreach ($matches[1] as $url) :
		$url = parse_url($url);
		$url['host'] = str_replace('www.', '', $url['host']);
		$domains[$url['host']]++;
	endforeach;

endforeach;
?>
<script type="text/javascript">
		
		
		function setMessage(msg) {
			jQuery("#message").html(msg);
			jQuery("#message").show();
		}
		
		function regenerate(domain,domainmd5) {
			jQuery("#cache_images_" + domainmd5).attr("disabled", true);
			setMessage("<?php _e('Reading posts...', 'cache-images'); ?>");
			jQuery.ajax({
				url: ajaxurl, 
				type: "POST",
				//contentType: "application/json",
				data: "action=cache_images&do=getlist&domain=" + domain,
				success: function(result) {
					var list = eval(result);
					var curr = 0;

					function regenItem() {
						if (curr >= list.length) {
							jQuery("#cache_images_" + domainmd5).removeAttr("disabled");
							jQuery("#thumb").hide();
							jQuery("#domain_" + domainmd5).hide();
							var donecachingone = "<?php _e('Done caching from %1$s', 'cache-images'); ?>";
							var donecaching = donecachingone.replace("%1$s", domain);
							setMessage(donecaching);
							jQuery("#message").fadeOut(1300);
							return;
						}
						var cachestatusone = "<?php _e('Caching %1$s of %2$s', 'cache-images'); ?>";
						var cachestatustwo = cachestatusone.replace("%1$s", curr+1);
						var cachestatus = cachestatustwo.replace("%2$s", list.length);
						setMessage(cachestatus);

						jQuery.ajax({
							url: ajaxurl,
							type: "POST",
							data: "action=cache_images&do=regen&url=" + list[curr].url + "&postid=" + list[curr].postid,
							success: function(result) {
								jQuery("#thumb").show();
								jQuery("#thumb-img").html(result);

								curr = curr + 1;
								regenItem();
							}
						});
					}

					regenItem();
				},
				error: function(request, status, error) {
					var errormessageone = "<?php _e('Error %1$s', 'cache-images'); ?>";
					var errormessage = errormessageone.replace("%1$s", request.status);
					setMessage(errormessage);
				}
			});
		}
		
		
</script>
<?php
	$this_domain = parse_url(get_option('siteurl'));
	$this_domain['host'] = str_replace('www.', '', $this_domain['host']);
	$this_domain_md5 = md5( $this_domain['host'] );
?>
<script type="text/javascript">
jQuery(document).ready(function()
{
	jQuery("#domain_<?php echo $this_domain_md5; ?>").hide();
});
</script>
<p><?php _e('We found some results. Choose the domains from where you want to grab images from by clicking on a button "Cache from this domain" next to it.', 'cache-images'); ?></p>
<p><?php _e('<strong>Note</strong>: you <strong>must not close</strong> this page while caching is performed. You can close it when you see message "Done caching from..." and yellow bar is removed', 'cache-images'); ?></p>
<div id="message" class="updated fade" style="display:none"></div>
		<div id="thumb" style="display:none"><?php printf(__('Last cached picture: %1$s', 'cache-images'), '<span id="thumb-img"></span>'); ?></div>
<form action="" method="post">
<ul>
<?php foreach ($domains as $domain => $num) : ?>
	<?php if ( strstr( $domain, get_option('siteurl') . '/' . get_option('upload_path') ) )
		continue; // Already local ?>
	<?php $domain_md5 = md5( $domain ); ?>
	<li id="domain_<?php echo $domain_md5; ?>">
		<label><code><?php echo $domain; ?></code> <?php printf(_n('(%1$s image found)', '(%1$s images found)', $num, 'cache-images'), $num); ?> <input type="button" onClick="javascript:regenerate('<?php echo $domain; ?>', '<?php echo $domain_md5; ?>');" class="button" name="cache_images_<?php echo $domain_md5; ?>" id="cache_images_<?php echo $domain_md5; ?>" value="<?php _e( 'Cache from this domain', 'cache-images' ) ?>" /> </label>
	</li>
<?php endforeach; ?>
</ul>
</form>
<?php endif; ?>

</div>
<?php
}

add_action('admin_menu', 'mm_ci_add_pages');


add_action('wp_ajax_cache_images', 'cache_images_ajax');
 
function cache_images_ajax() {
	global $wpdb;
	$action = $_POST["do"];
	$domain = $_POST["domain"];

	if ($action == "getlist") {
		$postid_list = $wpdb->get_results("SELECT DISTINCT ID FROM $wpdb->posts WHERE post_content LIKE ('%<img%') AND post_content LIKE ('%$domain%')");

		foreach ( $postid_list as $v ) {
			$postid = $v->ID;
			$post = $wpdb->get_results("SELECT post_content FROM $wpdb->posts WHERE ID = '$postid'");
			preg_match_all('|<img.*?src=[\'"](.*?)[\'"].*?>|i', $post[0]->post_content, $matches);
			foreach ( $matches[1] as $url ) :
				if ( strstr( $url, get_option('siteurl') . '/' . get_option('upload_path') ) || !strstr( $url, $domain) || (($res) && in_multi_array($url, $res)))
					continue; // Already local
				$res[] = array('url' => $url, 'postid' => $postid);
			endforeach;
		}
		die( json_encode($res) );
	} else if ($action == "regen") {
		$url = $_POST["url"];
		$postid = $_POST["postid"];
		$orig_url = $url;
		
		//check if pic is on Blogger
		if (strpos($url, 'blogspot.com') || strpos($url, 'blogger.com') || strpos($url, 'ggpht.com') || strpos($url, 'googleusercontent.com') || strpos($url, 'gstatic.com')) {
			$response = wp_remote_request($url);
			if ( is_wp_error( $response ) )
				die('error1');
				
			$my_body = wp_remote_retrieve_body($response);
			
			if (strpos($my_body, 'src')) {
				preg_match_all('|<img.*?src=[\'"](.*?)[\'"].*?>|i', $my_body, $matches);
				foreach ( $matches[1] as $url ) :
					$spisak = $url;
				endforeach;
				
				$url = $spisak;
			}
		}
		
		set_time_limit( 30 );
		$upload = media_sideload_image($url, $postid);
			
		if ( !is_wp_error($upload) ) {
			preg_match_all('|<img.*?src=[\'"](.*?)[\'"].*?>|i', $upload, $locals);
			foreach ( $locals[1] as $newurl ) :
				$wpdb->query("UPDATE $wpdb->posts SET post_content = REPLACE(post_content, '$orig_url', '$newurl');");
			endforeach;
		}

		die( $url );
	}
}
/*
to do:
if pic from Blogger has + in it, how to handle it
cache only on page where is found
check if image is in array
*/
?>