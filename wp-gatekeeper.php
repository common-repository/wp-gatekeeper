<?php
/*
=========================================================================
Plugin Name: WP-Gatekeeper
Version: 1.5rc3
Plugin URI: http://www.meyerweb.com/eric/tools/wordpress/wp-gatekeeper/
Description: This plugin contains all the routines needed to display, check, and manage challenges for the <a href="http://www.meyerweb.com/eric/tools/wordpress/wp-gatekeeper/">Gatekeeper anti-spam system</a>.  The point of Gatekeeper is to stymie spambots by requiring a commenter to answer a question that has an obvious answer.  Challenges are not posed to users who are logged in.
Author: <a href="http://meyerweb.com/">Eric A. Meyer</a> &amp; <a href="http://scott.sauyet.com/">Scott Sauyet</a>
Author URI: 
WordPress Version Required: 1.5
=========================================================================
*/ 


/* bootstraps */

add_action('wp_head', 'gatekeeper_comment_form_scan');
add_action('admin_head','gatekeeper_base_styles');
add_action('admin_menu', 'gatekeeper_admin_page');
add_action('preprocess_comment', 'gatekeeper_stand_guard');
ob_start('gatekeeper_comment_form_filter');

$wp_gk_challenge_function_present = false;
$wp_gk_filename = 'wp-gatekeeper.php';
$wp_gk_adminurl = 'edit.php?page=wp-gatekeeper.php';
$wp_gk_date_template = 'j M Y - g:i:s a';
$wp_gk_cr_bs_now = date('Y-m-d H:i:s', (time() + (get_settings('time_difference') * 3600)));

$wp_gk_masterkey = get_option('gatekeeper_masterkey');

$wp_gk_markup_default = '<p><input name="response" type="text" size="22" tabindex="{TAB_INDEX}" /> <label for="response"><small>{QUESTION}</small></label> <input name="key" type="hidden" value="{KEY}" /></p>';
$wp_gk_markup = stripslashes(get_option('gatekeeper_markup'));

$wp_gk_challenges = get_option('gatekeeper_challenges');
$wp_gk_challenges_default = array(
		array(
			"ID"=>"1",
			"visible"=>"Y",
			"challenge"=>"What color is an orange?",
			"response"=>"orange",
			"date_added"=>$wp_gk_cr_bs_now,
			"date_modified"=>$wp_gk_cr_bs_now)
		);

if (!$wp_gk_masterkey) {
	update_option('gatekeeper_masterkey',substr(md5(uniqid(microtime())), 0, 10));
}
if (!$wp_gk_markup) {
	update_option('gatekeeper_markup',$wp_gk_markup_default);
}
if (!$wp_gk_challenges) {
	update_option('gatekeeper_challenges',$wp_gk_challenges_default);
}



/* admin page addition and generation */

function gatekeeper_admin_page() {	
	add_management_page('Gatekeeper Options', 'Gatekeeper', 8, basename(__FILE__), 'gatekeeper_management');
}

function gatekeeper_management() {

	if (isset($_REQUEST['action'])) {
		gatekeeper_action_jackson();
	} else {
		gatekeeper_leadtext();
		gatekeeper_listing();
		gatekeeper_editform();
		gatekeeper_key_box();
		gatekeeper_default_template();
	}

}



/* stand by for action! */

function gatekeeper_action_jackson() {
	global $wp_gk_challenges, $wp_gk_adminurl, $wp_gk_markup_default, $wp_gk_challenges_default;

	$action = $_REQUEST['action'];
	switch ($action) {
	case 'Add':
		$ID = -1;
		foreach ($wp_gk_challenges as $challenge) {
			if ($challenge['ID'] > $ID) $ID = $challenge['ID'];
		}
		if ($ID < 0) die ('ID scan failed!');
		$ID = $ID + 1;
		$now = date('Y-m-d H:i:s', (time() + (get_settings('time_difference') * 3600)));
		$addition = array(
			"ID"=>$ID,
			"visible"=>$_REQUEST['visible'],
			"challenge"=>$_REQUEST['challenge'],
			"response"=>$_REQUEST['response'],
			"date_added"=>$now,
			"date_modified"=>$now);
		array_push($wp_gk_challenges,$addition);
		update_option('gatekeeper_challenges',$wp_gk_challenges);
	break;

	case 'Delete':
		if (isset($_REQUEST['challenge_id'])) {
			$max = count($wp_gk_challenges);
			for ($i = 0; $i <= $max; $i++) {
				if ($wp_gk_challenges[$i]["ID"] == $_REQUEST['challenge_id']) {
					array_splice($wp_gk_challenges,$i,1);
				}
				if ($i >= 1000) die("Fatal error: runaway loop in update routine! Ran until loop $i of a potential $max maximum; any loop greater than or equal to 1000 will trigger this error.");
			}
			update_option('gatekeeper_challenges',$wp_gk_challenges);
		}
	break;
	
	case 'Edit':
		if (isset($_REQUEST['challenge_id'])) {
			gatekeeper_editform('Update');
		}
	break;
	
	case 'Update':
		if (isset($_REQUEST['challenge_id'])) {
			$max = count($wp_gk_challenges);
			for ($i = 0; $i <= $max; $i++) {
				if ($wp_gk_challenges[$i]["ID"] == $_REQUEST['challenge_id']) {
					$wp_gk_challenges[$i]["challenge"] = $_REQUEST['challenge'];
					$wp_gk_challenges[$i]["response"] = $_REQUEST['response'];
					$wp_gk_challenges[$i]["visible"] = $_REQUEST['visible'];
					$wp_gk_challenges[$i]["date_modified"] = date('Y-m-d H:i:s', (time() + (get_settings('time_difference') * 3600)));
					$i = $max;
				}
				if ($i >= 1000) die("Fatal error: runaway loop in update routine! Ran through loop $i of a potential $max maximum; any maximum over 1000 will trigger this error.");
			}
			update_option('gatekeeper_challenges',$wp_gk_challenges);
		 }
	break;

	case 'Reset':
		$reset = $_REQUEST['setting'];
		switch ($reset) {
			case 'markup':
				update_option('gatekeeper_markup',$wp_gk_markup_default);
			break;
			case 'challenges':
				update_option('gatekeeper_challenges',$wp_gk_challenges_default);
			break;
		}
	break;

	case 'Randkey':
		$new_master = substr(md5(uniqid(microtime())), 0, 10);
		update_option('gatekeeper_masterkey',$new_master);
	break;
	case 'Userkey':
		update_option('gatekeeper_masterkey',$_REQUEST['masterkey']);
	break;
	case 'Markover':
		update_option('gatekeeper_markup',$_REQUEST['default_markup']);
	break;
	}

	if ($action != 'Edit') {
		header('Location: ' . $wp_gk_adminurl);
	}

}



/* various admin display routines */

function gatekeeper_base_styles() {
	if ('Gatekeeper Options' != get_admin_page_title()) return;
	echo <<<STYLE
<style type="text/css">
.wrap h3 {border-bottom: 1px dotted #AAA;}
.resetter {margin: -2.4em 0 1.2em; text-align: right;}
.wrap table {width: 100%;}
.wrap th, .wrap td {padding: 3px;}
th {text-align: left;}
.id {width: 1.5em; text-align: right;}
.pass {width: 6em;}
tbody th {text-align: right;}
.adddate, .moddate {width: 12em;}
#gk-listing .action {padding: 0;}
.No {color: #AAA;}

.a {float: left; width: 58%; margin-top: 0; margin-right: 0;}
.b {float: right; width: 25%; margin-top: 0; margin-left: 0;}
#footer {clear: both;}
</style>
STYLE;

}


function gatekeeper_leadtext() {

echo <<<TEXT
<div class="wrap">
<h2>Gatekeeper</h2>
<p>
Keep in mind that the point is to write questions and answers are completely obvious to a human.  Answers can be capitalized however you like, as they are checked case-insensitively when the user posts; thus, "Orange" and "orange" are treated as being the same.
</p>
TEXT;

}


function gatekeeper_key_box() {
	global $wp_gk_adminurl, $wp_gk_masterkey;

	echo <<<KEYBOX
<div class="wrap b">

<form method="post" action="$wp_gk_adminurl&amp;action=Newkey">
<h3>Master key</h3>
<p class="resetter"><a href="$wp_gk_adminurl&amp;action=Randkey" onclick="return confirm('WARNING: You are about to replace your current master key with new random key.  You cannot reverse this operation.\\n  \'Cancel\' to abort, \'OK\' to proceed.');">Generate new key</a></p>
<p>
The master key is used to modify the hash generated by Gatekeeper.  You can have Gatekeeper generate a random master key, as it does by default, or else enter one of your own devising.
</p>
<p>
<input type="hidden" name="action" value="Userkey" /><input type="text" name="masterkey" value="$wp_gk_masterkey" /><input type="submit" value="Use this key" />
</form>

</div>
KEYBOX;

}


function gatekeeper_default_template() {
	global $wp_gk_adminurl, $wp_gk_markup;

	$format = stripslashes($wp_gk_markup);
	echo <<<DEFAULT
<div class="wrap">

<form method="post" action="$wp_gk_adminurl&amp;action=Markover">
<h3>Markup template</h3>
<p class="resetter"><a href="$wp_gk_adminurl&amp;action=Reset&amp;setting=markup" onclick="return confirm('WARNING: You are about to replace your markup template with the installation default.  You cannot reverse this operation.\\n  \'Cancel\' to abort, \'OK\' to proceed.');">Reset to default</a></p>
<p>
<strong>Altering the value of this option will change the markup generated by the system and inserted into your comment forms.</strong>  Only mess with this if you're absolutely sure you know what you're doing.
</p>
<p>
<textarea name="default_markup" rows="5" cols="100%">$format</textarea>
</p>
<input type="submit" value="Update template" />

</form>

</div>
DEFAULT;

}


function gatekeeper_listing() {
	global $wp_gk_challenges, $wp_gk_adminurl, $wp_gk_date_template;

	?>
<h3>Challenges</h3>
	<?php
	echo "<p class=\"resetter\"><a href=\"$wp_gk_adminurl&amp;action=Reset&amp;setting=challenges\" onclick=\"return confirm('WARNING: You are about to replace your challenges with the installation default.  THIS WILL WIPE OUT ALL OF YOUR CURRENT CHALLENGES.  You cannot reverse this operation.\\n  \'Cancel\' to abort, \'OK\' to proceed.');\">Reset to default</a></p>";
		
//	if ($_REQUEST['show'] == 'all') {
//		echo '<p id="limiter"><a href="'.$wp_gk_adminurl.'">Most recent 10</a> | Complete list</p>';
//	} else {
//		echo '<p id="limiter">Most recent 10 | <a href="'.$wp_gk_adminurl.'&amp;show=all">Complete list</a></p>';
//	}
	?>
	<table cellspacing="1" id="gk-listing">
		<thead>
		<tr>
		  <th>ID</th>
		  <th>Challenge</th>
		  <th>Response</th>
		  <th>Date Added</th>
		  <th>Last Update</th>
		</tr>
		</thead>
		<tbody>

	<?php
	$challenges = $wp_gk_challenges;
	if ($challenges) {
		if (count($challenges) > 10 && $_REQUEST['show'] != 'all') {
			$challenges = array_slice($challenges, 0, 10);
		}
		foreach ($challenges as $challenge) {
			$ID = $challenge["ID"];
			$question = stripslashes($challenge["challenge"]);
			$answer = stripslashes($challenge["response"]);
			$visible = ($challenge["visible"] == 'Y') ? 'Yes' : 'No';

			++$i;
			$style = ($i % 2) ? 'alternate ' : '';
			$style .= $visible;

			$add_date = date($wp_gk_date_template, strtotime($challenge["date_added"]));
			$mod_date = date($wp_gk_date_template, strtotime($challenge["date_modified"]));
			$mod_date == $add_date ? $mod_date = '-' : '';

			echo <<<CHALLENGES
	<tr class="$style">
		<td class="id">$ID</td>
		<td class="challenge">$question</td>
		<td class="response">$answer</td>
		<td class="adddate">$add_date</td>
		<td class="moddate">$mod_date</td>
		<td class="action"><a href="$wp_gk_adminurl&amp;action=Edit&amp;challenge_id=$ID" class="edit">Edit</a></td>
		<td class="action"><a href="$wp_gk_adminurl&amp;action=Delete&amp;challenge_id=$ID" onclick="return confirm('WARNING: You are about to delete this challenge.  You cannot undo this operation.\\n  \'Cancel\' to abort, \'OK\' to delete.');" class="delete">Delete</a></td>
	</tr>
CHALLENGES;
		}
	}
	?>

		</tbody>
	</table>
	</div>
<?php

}


function gatekeeper_editform($action = 'Add') {
	global $wp_gk_challenges, $wp_gk_adminurl;

	$challenge = $response = $visible = $ID = '';
	if ($action != 'Add') {
		$ID = stripslashes($_REQUEST['challenge_id']);
		$update = $wp_gk_challenges[$ID - 1];
		$challenge = htmlspecialchars(stripslashes($update["challenge"]));
		$response = htmlspecialchars(stripslashes($update["response"]));
		$passcode = htmlspecialchars(stripslashes($update["passcode"]));
		$visible = htmlspecialchars(stripslashes($update["visible"]));
	}
	?>

<div class="wrap a">
	<h3><strong><?php echo $action; ?></strong> a challenge</h3> 
	<form name="addchallenge" method="post" action="<?php echo $wp_gk_adminurl; ?>">
		<table cellspacing="1">
			<tbody>
			<tr>
				<th scope="row">
					Challenge: 
				</th>
				<td>
					<input type="text" name="challenge" size="60" value="<?php echo $challenge; ?>"> 
				</td>
			</tr>
			<tr>
				<th scope="row">
					Response: 
				</th>
				<td>
					<input type="text" name="response" size="60" value="<?php echo $response; ?>"> 
				</td>
			</tr>
			<tr>
				<th scope="row">
					Activate? 
				</th>
				<td>
	<?php
		if ($visible == '') {
	?>
					 <input type="radio" name="visible" checked="checked" value="Y"> <label>Yes</label>
					 &nbsp;
					 <input type="radio" name="visible" value="N"> <label>No</label> 
	<?php
		} else {
	?>
			 		<input type="radio" name="visible" value="Y" <?php if ($visible == 'Y') echo 'checked="checked"' ?>> <label>Yes</label>
					&nbsp;
					<input type="radio" name="visible" value="N" <?php if ($visible == 'N') echo 'checked="checked"' ?> <label>No</label>
	<?php
		}
	?>
				</td>
			</tr>
			</tbody>
		</table>
		<p style="text-align: center;">
			<input type="hidden" name="action" value="<?php echo $action; ?>" /> 
			<input type="hidden" name="user_ID" value="<?php echo $user_ID; ?>" />
			<input type="hidden" name="challenge_id" value="<?php echo $ID; ?>" />
			<input type="submit" name="submit" value="<?php echo $action; ?> Challenge" class="search" />
		</p>
	</form>
</div>
	<?php

}



/* challenge/response routines */

function gatekeeper_pose_challenge($format='',$tabindex='5',$stripslashes='Y',$doctype='xhtml') {
	global $wpdb, $wp_gk_masterkey, $wp_gk_challenges, $user_ID;

	if ($user_ID) return; // no need to check logged-in users

	$result = gatekeeper_markup_template($format,$tabindex);
	if ($stripslashes != 'Y') {addslashes($result);}
	if ($doctype == 'html') {preg_replace('/\/([\s]*)>/','\\1>',$result);}

	echo $result;

}

function gatekeeper_run_gauntlet($key,$answer,$stripslashes='Y') {
	global $wp_gk_masterkey, $wp_gk_challenges;

	if (!$wp_gk_masterkey) return false;

	$user_ip = $_SERVER['REMOTE_ADDR'];

	foreach ($wp_gk_challenges as $challenge) {
		$check = $challenge["challenge"];
		if (md5("$check.$user_ip.$wp_gk_masterkey") == $key) {
			if (strcasecmp(stripslashes($challenge["response"]),stripslashes($answer)) == 0) {
				return true;
			}
		}
	}
	return false;

}

function gatekeeper_stand_guard($commentdata) {
	global $user_ID;

	if ($user_ID) return $commentdata; // no need to check logged-in users
	$key = trim(strip_tags($_REQUEST['key']));
	$answer = trim(strip_tags($_REQUEST['response']));
	if (!gatekeeper_run_gauntlet($key,$answer)) {
		die( __('Sorry, posting has been closed for the time being.') );
	}
	return $commentdata;

}



/* form modifier routines */

function gatekeeper_comment_form_scan() {
	global $wp_gk_challenge_function_present;

	$comment_file = get_theme_root(). '/' . $gk_template = get_settings('template') . '/comments.php';
	if (file_exists($comment_file)) {
		$comment_form = file_get_contents($comment_file,'r+');
		preg_match('/gatekeeper_pose_challenge/', $comment_form, $matches);
		if ($matches) {
			$wp_gk_challenge_function_present = true;
		}
	}

}

function gatekeeper_comment_form_filter($buffer) {
	global $wp_gk_challenge_function_present, $wp_gk_markup, $user_ID;

	if ($wp_gk_challenge_function_present || $user_ID) return $buffer; // bail if function is already present or user is logged in

	$default_markup = $wp_gk_markup;

	$search = '#<p><input(.*?)name="url"(.*?)/>(.*?)</p>(.*?)<p><textarea(.*?)tabindex="(\d+)"(.*?)>#si';
	preg_match($search, $buffer, $matches);
	if ($matches) {
		$challenge_tab = $matches[6];
		$textarea_tab = $challenge_tab + 1;
		$replace = '<p><input$1name="url"$2/>$3</p>' . gatekeeper_markup_template($default_markup, $challenge_tab) . '$4<p><textarea$5tabindex="' . $textarea_tab . '">';
	} else {
		$search = '#<textarea(.*?)name="comment"(.*?)tabindex="(\d+)"(.*?)</textarea>(.*?)</p>#si';
		preg_match($search, $buffer, $matches);
		if ($matches) {
			$challenge_tab = $matches[3] + 1;
			$replace = '<textarea$1name="comment"$2tabindex="$3"$4</textarea>$5</p>' . "\n\n". gatekeeper_markup_template($default_markup, $challenge_tab);
		} else {
			return $buffer;
		}
	}

	$content = preg_replace($search, $replace, $buffer);
	return $content;

}

function gatekeeper_markup_template($format, $tabindex) {
	global $wp_gk_masterkey, $wp_gk_challenges;

	if (!$format) $format = stripslashes(get_option('gatekeeper_markup'));
	
	$user_ip = $_SERVER['REMOTE_ADDR'];

	if (!$wp_gk_challenges) return false;
	do {
     	$cr = rand(1,count($wp_gk_challenges)) - 1;
		$challenge = $wp_gk_challenges[$cr];
	} while ($challenge["visible"] != "Y"); // sds TODO -- fix possible infinite loop!
	$check = $challenge['challenge'];
	$key = md5("$check.$user_ip.$wp_gk_masterkey");

	$format = str_replace('{QUESTION}', stripslashes($challenge["challenge"]), $format);
	$format = str_replace('{KEY}', $key , $format);
	$format = str_replace('{TAB_INDEX}', $tabindex , $format);
//	$format = str_replace('{ANSWER}', $challenge["response"] , $format);
//	$format = str_replace('{USER_IP}', $user_ip , $format);
//	$format = str_replace('{MASTER_KEY}', $wp_gk_masterkey , $format);

	return $format;
}

?>