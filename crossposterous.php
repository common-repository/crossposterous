<?php
/*
Plugin Name: Crossposterous
Plugin URI: http://www.bbqiguana.com/wordpress-plugins/crossposterous/
Description: This plugin will automatically cross-post your Wordpress blog entry to your Posterous site. 
Version: 1.2.1
Author: Randy Hunt
Author URI: http://www.bbqiguana.com/
*/

/*  Copyright 2010  Randy Hunt  (email : bbqiguana@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/



/* When plugin is activated */
register_activation_hook  (__FILE__, 'crossposterous_activate'  );
register_deactivation_hook(__FILE__, 'crossposterous_deactivate');

function crossposterous_activate () {
	$options = array('email'=>'', 'password'=>'', 'siteid'=>'', 'posttype'=>'');
	add_option('crossposterous', $options);
}

function crossposterous_deactivate () {
	delete_option('crossposterous');
}

if (is_admin()) {
	add_action('admin_menu',      'crossposterous_admin_menu');
	add_action('admin_init',      'crossposterous_init');
	add_filter('plugin_row_meta', 'posterizePluginLinks',10,2);

	function crossposterous_admin_menu(){
		add_options_page('Crossposterous Settings', 'Crossposterous', 'administrator', __FILE__, 'crossposterous_admin_page');
	}

	function posterizePluginLinks($links, $file){
		if( $file == 'posterize/posterize.php') {
			$links[] = '<a href="' . admin_url( 'options-general.php?page=posterize-settings' ) . '">' . __('Settings') . '</a>';
			$links[] = '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=QC745TKR6AHBS" target="_blank">Donate</a>';
		}
		return $links;
	}

	function crossposterous_init() {
		register_setting('crossposterous','crossposterous');
		add_settings_section('posterous', 'Posterous settings', 'crossposterous_foo', __FILE__);
		add_settings_field('email', 'Email address', 'crossposterous_render_email', __FILE__, 'posterous');
		add_settings_field('password', 'Password', 'crossposterous_render_password', __FILE__, 'posterous');
		add_settings_field('siteid', 'Site ID', 'crossposterous_render_sites', __FILE__, 'posterous');
		add_settings_field('posttype', 'What to post', 'crossposterous_render_posttype', __FILE__, 'posterous');
	}

	function crossposterous_foo() {
		//echo '1.foo';
	}

	function crossposterous_render_email () {
		$options = get_option('crossposterous');
		echo '<input id="posterous_email" name="crossposterous[posterous_email]" size="40" type="text" value="' . $options['posterous_email'] . '" class="regular-text" />';
	}

	function crossposterous_render_password () {
		$options = get_option('crossposterous');
		echo '<input id="posterous_password" name="crossposterous[posterous_password]" size="40" type="text" value="' . $options['posterous_password'] . '" class="regular-text" />';
	}

	function crossposterous_render_sites () {
		$options  = get_option('crossposterous');
		$email    = $options['posterous_email'];
		$password = $options['posterous_password'];

		$xml = invoke_rest_api('http://posterous.com/api/getsites', 'GET', null, $email, $password);

		if (function_exists('simplexml_load_string')) {
			$root = simplexml_load_string($xml);
			$data = get_object_vars($root);
		} else {
			require('xml2arr.php');
			$data = xml2array($xml);
			if ($data['rsp']) $data = $data['rsp'];
		}

		$sites = array();

		if(!$data['site']){
			print '<div class="error"><strong>Error:</strong> Check your username and password</div>';
		} else {
			$html = '<select id="posterous_site" name="crossposterous[siteid]"><option value=""></option>';
//			/if(is_array($data['site'])){
//
//				foreach ($data['site'] as $key => $value) {
//					$site[$key] = $value;
//					//foreach ($values as $key => $value) {
//					//	$sites['site'][$keys][$key] = $value;
//					//}
//				}
//				$html .= "<option value=\"{$site['id']}\">{$site['name']}</option>";
//				//foreach($sites['site'] as $site){
//				//	$html .= '<option value="'.$site['id'].'">'.$site['name'].'</option>';
//				//}
//			}else{
				foreach ($data['site'] as $key => $value) {
					$site[$key] = $value;
				}
				$html .= "<option value=\"{$site['id']}\"".($site['id']==$options['siteid']?" selected" :"").">{$site['name']}</option>";
//			}
			echo $html . '</select>';
		}
	}

	function crossposterous_render_posttype () {
		$options = get_option('crossposterous');
		echo '<input name="crossposterous[posttype]" type="radio" value="link"'.($options['posttype']=='link'?' checked':'').'> <label>Teaser and link.</label><br>';
		echo '<input name="crossposterous[posttype]" type="radio" value="full"'.($options['posttype']=='full'?' checked':'').'> <label>Post full content.</label><br>';
	}

	function crossposterous_admin_page() {
		echo '<div class="wrap">';
		echo '<div class="icon32" id="icon-options-general"><br></div>';
		echo '<h2>crossposterous</h2>';
		echo '<form action="options.php" method="post">';
		settings_fields('crossposterous');
		do_settings_sections(__FILE__);
		echo '<p class="submit">';
		echo '<input name="Submit" type="submit" class="button-primary" value="' . __('Save Changes') . '" />';
		echo '</p>';
		echo '</form>';
		echo '</div>';
	}  
}

function crossposterous_excerpt ($text, $more='... ', $limit=55) {
  $text = strip_shortcodes( $text );
  $text = str_replace(']]>', ']]&gt;', $text);
  if (preg_match('/<!--more(.*?)?-->/', $text, $matches) ) {
    $text = explode($matches[0], $text, 2);
    $text = strip_tags($text[0]) . $more;
  } else {
    $text = strip_tags($text);
    $words = explode(' ', $text, $limit + 1);
    if (count($words) > $limit) {
      array_pop($words);
      $text = implode(' ', $words) . $more;
    }
  }
  return $text;
}

function send_to_posterous ($post_ID) {
	$options  = get_option('crossposterous');
	$email    = $options['posterous_email'];
	$password = $options['posterous_password'];
	$siteid   = $options['posterous_siteid'];

	if ($email!='' && $password!='') {
		global $userdata;
		get_currentuserinfo();

		$post = get_post($post_ID);

		$data = array();
		$data['site_id'] = $options['siteid'];
		$data['title']   = $post->post_title;

		if ($options['posttype'] == 'full') {
			$data['body'] = $post->post_content;
		} else {
			$blog_title = get_bloginfo();
			$data['body'] = crossposterous_excerpt($post->post_content) . ' <a href="'.get_permalink($post_ID).'">continue reading at ' . $blog_title . '</a>';
		}
		$tags = array();
		$posttags = get_the_tags($post_ID);
		if ($posttags) {
			foreach($posttags as $tag) {
				$tags[] = $tag->name; 
			}
		}
		$data['tags']       = implode(',', $tags);
		$data['source']     = 'Crossposterous';
		$data['sourceLink'] = 'http://www.bbqiguana.com/wordpress-plugins/crossposterous/';

		$url = 'http://posterous.com/api/newpost';
		invoke_rest_api($url, 'POST', $data, $email, $password);
	}
}

function invoke_rest_api ($endpoint, $method = 'GET', $data = null, $username = '', $password = '') {
	$url = $endpoint;
	$ch  = curl_init();
	if($data) {
		$postdata = array();
		foreach ($data as $key=>$value)
			array_push($postdata, $key . '=' . urlencode($value));

        if ($method = 'POST') {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $postdata));
        } else {
            $url .= '?' . implode('&', $postdata);
            curl_setopt($ch, CURLOPT_URL, $url);
        }
    } else {
        curl_setopt($ch, CURLOPT_URL, $url);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    if ($username && $password) {
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

add_action('new_to_publish',     'send_to_posterous');
add_action('draft_to_publish',   'send_to_posterous');
add_action('future_to_publish',  'send_to_posterous');
add_action('pending_to_publish', 'send_to_posterous');

?>
