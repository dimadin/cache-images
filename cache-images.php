<?php
/*
Plugin Name: Cache Images
Plugin URI: http://photomatt.net/#
Description: Goes through your posts and gives you the option to cache all hotlinked images from a domain locally in your upload folder
Version: 1.0&alpha;
Author: Matt Mullenweg
Author URI: http://photomatt.net/
WordPress Version Required: 1.5
*/

function mm_ci_add_pages() {
	
	add_management_page('Cache Remote Images', 'Remote Images', 8, __FILE__,
'mm_ci_manage_page');
}


function mm_ci_manage_page() {
	global $wpdb;
?>
<div class="wrap">
<pre><?php var_dump($_POST); ?></pre>
<h2>Remote Image Caching</h2>
<?php if ( !isset($_POST['step']) ) : ?>
<p>Here's how this works:</p>
<ol>
	<li>Click the button below and we'll scan all of your posts for remote images</li>
	<li>Then you'll be presented with a checklist of domains, check the domains you want to grab cache from</li>
	<li>The images will be copied to your upload directory (this must be writable) and the links in your posts will be updated to the new location.</li>
</ol>
<form action="" method="post">
<p class="submit">
	<input name="step" type="hidden" id="step" value="2">
	<input type="submit" name="Submit" value="Get Started &raquo;" />
</p>
</form>
<?php endif; ?>

<?php if ('2' == $_POST['step']) : ?>
<?php
$posts = $wpdb->get_results("SELECT post_content FROM $wpdb->posts WHERE post_content LIKE ('%<img%')");

if ( !$posts ) 
	die('No posts with images were found.');

foreach ($posts as $post) :
	preg_match_all('|<img.*?src=[\'"](.*?)[\'"].*?>|i', $post->post_content, $matches);
	$url = parse_url($matches[1][0]);
	$url['host'] = str_replace('www.', '', $url['host']);
	$domains[$url['host']]++;
endforeach;
?>
<p>We found some goodies. Check the domains that you want to grab images from:</p>
<form action="" method="post">
<ul>
<?php foreach ($domains as $domain => $num) : ?>
	<li>
		<label><input type="checkbox" name="domains[]" value="<?php echo $domain; ?>" /> <code><?php echo $domain; ?></code> (<?php echo $num; ?> images found)</label>
	</li>
<?php endforeach; ?>
</ul>
<p class="submit">
	<input name="step" type="hidden" id="step" value="3">
	<input type="submit" name="Submit" value="Cache These Images &raquo;" />
</p>
</form>
<pre><?php var_dump($domains); ?></pre>
<?php endif; ?>

<?php if ('3' == $_POST['step']) : ?>
<?php
if ( !isset($_POST['domains']) )
	die("You didn't check any domains, did you change your mind?");
if ( !is_writable(get_settings('fileupload_realpath')) )
	die('Your upload folder is not writable');

foreach ( $_POST['domains'] as $domain ) :
	$posts = $wpdb->get_results("SELECT post_content FROM $wpdb->posts WHERE post_content LIKE ('%<img%') AND post_content LIKE ('%$domain%')");
?>
<h3><?php echo $domain; ?></h3>

<ul>
<?php 
	foreach ($posts as $post) :
		preg_match_all('|<img.*?src=[\'"](.*?)[\'"].*?>|i', $post->post_content, $matches);
		$url      = $matches[1][0];
		$filename = basename($matches[1][0]);
		$f        = fopen( get_option('fileupload_realpath') . "/$filename", 'w' );
		$img      = file_get_contents($url);
		if ( $img ) {
			fwrite( $f, $img );
			fclose( $f );
			$local = get_option('fileupload_url') . "/$filename";
			$wpdb->query("UPDATE $wpdb->posts SET post_content = REPLACE(post_content, '{$matches[1][0]}', '$local');");
			echo "<li>Cached {$matches[1][0]}</li>";
			flush();
		}
	endforeach;
?>
</ul>
<?php
endforeach;
?>
<h3>All done!</h3>
<?php endif; ?>
</div>
<?php
}

add_action('admin_menu', 'mm_ci_add_pages');

?>