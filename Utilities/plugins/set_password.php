<html>

<?php
/**
 * PLUGIN NAME: set_password
 * DESCRIPTION: Manually set a password for a table-based user
 * VERSION:     1.0 ($Id: set_password.php 593 2016-01-27 01:58:11Z mgleason $)
 * AUTHOR:      Mike Gleason <mgleason@unmc.edu>
 */

// The recommended location for this script is in /redcap/plugins.
// Call the REDCap Connect file in the main "redcap" directory
require_once "../redcap_connect.php";

$HtmlPage = new HtmlPage();
$HtmlPage->PrintHeaderExt();

// Uncomment the line below if you want only administrators to run this plug-in.
if (!SUPER_USER) exit("Access denied! Only super users can access this page.");

// Must load this to access password hashing and setting functions.
require_once APP_PATH_DOCROOT . 'Classes/Authentication.php';

$scrf = PAGE_FULL;

/*****************************************************************************/
if (! array_key_exists("hello", $_POST)) {
// Present the form
?>

<h1>Manually Set a User's REDCap Password</h1>
<p>Hello <?php echo USERID; ?>, you are an administrator on <?php echo SERVER_NAME; ?>.<br /> It is now <?php echo NOW; ?>.</p>
<form id="mgForm" action="<?php echo $scrf; ?>" method="post">
<input type="hidden" name="hello" value="world">
<br>

<div style="margin-bottom:20px;padding:10px 15px 15px;border:1px solid #d0d0d0;background-color:#f5f5f5;">
	<b>Table-based User ID:</b><br />
	<div id="view_user_div" style="padding-top:5px;">
		<div style="margin:10px 0;">
			<input id="user_search" name="user_search" class="x-form-text x-form-field" maxlength="255" style="width:400px;color:darkblue;" tabindex="1"
				value="Search for user by username, first name, last name, or primary email" 
				onkeydown="if(event.keyCode==13) { onPressEnterForCompletion(); return false; }" 
				onfocus="if ($(this).val() == &#039;Search for user by username, first name, last name, or primary email&#039;) { 
						$(this).val(&#039;&#039;);
						$(this).css(&#039;color&#039;,&#039;#000&#039;); 
					}"
				onblur="$(this).val( trim($(this).val()) );
					if ($(this).val() == &#039;&#039;) { 
						$(this).val(&#039;Search for user by username, first name, last name, or primary email&#039;);
						$(this).css(&#039;color&#039;,&#039;#999&#039;); 
					}"
				type="text"/>
		</div>
	</div>
</div>

<p>
<table>
<tr><td></td><td><input type="button" tabindex="4" name="Generate Random Password" value="Generate Random Password" onClick="mgGenerateRandomPassword();"></td></tr>
<tr><td align="right">New Password: </td><td><input type="password" tabindex="2" size="32" style="color: darkblue;" name="new1" id="new1" /></td><td><input type="checkbox" tabindex="5" name="showthem" id="showthem" value="show" onClick="mgOnClickShowPassword();">Show Password</td></tr>
<tr><td align="right">New Password, again:</td><td><input type="password" tabindex="3" size="32" style="color: darkblue;" name="new2" id="new2" /></td></tr>
<tr><td></td><td><input type="button" tabindex="6" name="submitButton" value="Change Password" onClick="mgSubmit();" ></td></tr>
</table>

<script type="text/javascript">
function mgInit() {
	document.getElementById("new1").value = "";
	document.getElementById("new2").value = "";
	document.getElementById("showthem").checked = 0;
}	// mgInit

function mgOnClickShowPassword() {
	var prevVal = document.getElementById("showthem").checked;
	if (! prevVal) {
		document.getElementById("new1").type = "password";
		document.getElementById("new2").type = "password";
	} else {
		document.getElementById("new1").type = "text";
		document.getElementById("new2").type = "text";
	}
}	// mgOnClickShowPassword

function mgGenerateRandomPassword() {
	//                    1         2         3         4
	//           1234567890123456789012345678901234567890
	var chars = "abcdefghjkmnpqrstuvwxyz23456789";
	var m = 5;
	var L = 15;
	var p = "";
	for (i=0; i<L; i++) {
		var n = Math.floor(Math.random() * chars.length);
		var c = chars.substring(n, n + 1);
		if ((m > 0) && (i > 0) && ((i % m) == 0)) {
			p = p + "-";
		}
		p = p + c;
	}
	document.getElementById("new1").value = p;
	document.getElementById("new2").value = p;
	document.getElementById("showthem").checked = 1;
	document.getElementById("new1").type = "text";
	document.getElementById("new2").type = "text";
	// alert("Shhh... I chose \"" + p + "\".");
}	// mgGenerateRandomPassword

function mgSubmit() {
	var p = document.getElementById("new1").value;
	var minLen = 6;
	if ((document.getElementById("user_search").value.substring(0, 10) == "") || (document.getElementById("user_search").value.substring(0, 10) == "Search for")) {
		alert("Please specify a username.");
	} else if (p.length < minLen) {
		alert("Password does not meet minimum length requirement of " + minLen + ".");
	} else if (document.getElementById("new1").value != document.getElementById("new2").value) {
		alert("Passwords do not match.");
	} else {
		// alert("Shhh... I am about to set it to \"" + document.getElementById("new1").value + "\".");
		document.getElementById("mgForm").submit();
	}
}	// mgSubmit

// Auto-suggest for adding new users
function enableUserSearch() {	
	$('#user_search').autocomplete({
		source: app_path_webroot+"UserRights/search_user.php?searchEmail=1",
		minLength: 2,
		delay: 150,
		select: function( event, ui ) {
			$(this).val(ui.item.value);
			$('#user_search_btn').click();
			return false;
		}
	})
	.data('autocomplete')._renderItem = function( ul, item ) {
		return $("<li></li>")
			.data("item", item)
			.append("<a>"+item.label+"</a>")
			.appendTo(ul);
	};
}
$(function(){
	// alert('I see you, Mr. Autoloader.');
	mgInit();
	enableUserSearch();
	$('#user_search').prop("disabled", false);
});
</script>
</form>

<?php
/*****************************************************************************/
} else {
	// Process the form

	if ((strlen($_POST["user_search"]) <= 0) || (substr($_POST["user_search"], 0, 10) == "Search for"))  {
		exit("Please specify a user name.");
	} elseif (strlen($_POST["new1"]) < 6) {
		exit("Password too short.");
	} elseif ($_POST["new1"] != $_POST["new2"]) {
		exit("Passwords did not match.");
	} else {
		// echo '<p>Form processing in progress...</p>';
		$user = $_POST["user_search"];
		// echo '<p>USER=' . $user . "</p>";
		// echo '<p>NEW1=' . $_POST["new1"] . "</p>";
		// echo '<p>NEW2=' . $_POST["new2"] . "</p>";
		$isTableBasedUser =  Authentication::isTableUser($user);
		$old_salt = Authentication::getUserPasswordSalt($user);
		$new_salt = Authentication::generatePasswordSalt();
		$new_hashed_password = Authentication::hashPassword($_POST["new1"], $new_salt);
		if (strlen($old_salt) < 4) {
			exit("Username is invalid or does not exist.");
		} elseif (! $isTableBasedUser) {
			exit("User is not table-based.");
		} elseif (strlen($new_hashed_password) < 8) {
			exit("Hmmm, could not create a new hashed password.");
		} else {
			// echo '<p>ISTU=' . $isTableBasedUser . "</p>";
			// echo '<p>OSLT=' . $old_salt . "</p>";
			// echo '<p>SALT=' . $new_salt . "</p>";
			// echo '<p>HASH=' . $new_hashed_password . "</p>";
			$setpwResult = Authentication::setUserPasswordAndSalt($user, $new_hashed_password, $new_salt);
			// echo '<p>RESULT=' . $setpwResult . "</p>";
			if ($setpwResult > 0) {
				echo '<p><span style="color: green;">Successfully</span> set the password for user <tt>' . $user . '</tt>';
				if ($_POST['showthem'] == 'show') {
					echo ' to: <tt>' . htmlspecialchars($_POST["new1"]) . '</tt></p>';
				} else {
					echo '.</p>';
				}
			} else {
				echo '<p><span style="color: red;"><b>FAILED</b></span> to set the password for user <tt>' . $user . '</tt>. Sorry about that!</p>';
			}
		}
	}

/*****************************************************************************/
}

// OPTIONAL: Display the footer
$HtmlPage->PrintFooterExt();
