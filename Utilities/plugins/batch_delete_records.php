<html>

<?php
/**
 * PLUGIN NAME: batch_delete_records
 * DESCRIPTION: Delete a subset of a project's records all at once
 * VERSION:     1.0
 * AUTHOR:      Mike Gleason <mgleason@unmc.edu>
 */

// The recommended location for this script is in /redcap/plugins.
// Call the REDCap Connect file in the main "redcap" directory
require_once "../redcap_connect.php";

// OPTIONAL: Your custom PHP code goes here. You may use any constants/variables listed in redcap_info().
$verify_existence = 1;	// Change to 0 if you don't want to query the record existed before deleting it.
$verify_deletion = 1;	// Change to 0 if you don't want to re-query each record after trying to delete it.

// Uncomment the line below if you want only administrators to run this plug-in.
//if (!SUPER_USER) exit("Access denied! Only super users can access this page.");

// OPTIONAL: Display the project header
require_once APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

// Must load this to access deleteRecord function
require_once APP_PATH_DOCROOT . 'ProjectGeneral/form_renderer_functions.php';

function mgGetNumberOfKeyValuePairsForRecord($project_id, $record_id_to_query) {
	global $conn;

	if (($project_id == 0) or ($record_id_to_query === "")) {
		return (-2);
	}
	$num_k_v_pairs = -1;
	$stmt = $conn->prepare("SELECT COUNT(*) FROM redcap_data WHERE project_id = ? AND record = ?");
	$stmt->bind_param("is", $project_id, $record_id_to_query);	// pid is integer, but record could be alphanumeric.
	if (($stmt->execute()) and ($stmt->bind_result($num_k_v_pairs)) and ($stmt->fetch())) {
		// Because bind_result was run, fetch() sets the value of $num_k_v_pairs
		$stmt->close();
		return ($num_k_v_pairs); // Returns 0 if record did not exist, 1+ otherwise.
	}
	$stmt->close();
	return (-3);
}	// mgGetNumberOfKeyValuePairsForRecord

$scrf = PAGE_FULL;

/*****************************************************************************/
if (! array_key_exists("records_to_delete_textarea", $_POST)) {
// Present the form

if (! array_key_exists("pid", $_GET) || ! $_GET["pid"]) {
	exit("<b>Missing project number</b>. Make sure your URL appends \"<tt>?pid=123</tt>\" where <i>123</i> is your project number.");
}

$proj_id = $_GET["pid"];
$scrf .= "?pid=" . $proj_id;
?>

<!--
<?php echo "<p>The project is $proj_id.</p>" ?>
-->
<p>Please paste in a list record numbers to delete, one per-line or separated by spaces. </p>
<p>Note that there is <i>no</i> confirmation of deletion!  Once you click the DELETE RECORDS button below your supplied records will be <b>permanently deleted</b> from this project, so please double-check your list before proceeding. </p>
<form action="
<?php echo $scrf; ?>
" method="post">
<textarea rows="10" cols="80" name="records_to_delete_textarea"></textarea>
<br>
<input type="submit" name="submit" value="DELETE RECORDS">
</form>
</body>

<?php
/*****************************************************************************/
} else {
// Process the form


echo '<p>Deletion in progress...</p>';
echo '<ul><pre>';

$records_to_delete = preg_split("/[\s]+/", $_POST["records_to_delete_textarea"], -1, PREG_SPLIT_NO_EMPTY);
$proj_id = $_GET["pid"];
$ndel = 0;

for ($i = 0; $i < count($records_to_delete); $i++) {
	$rec_id = $records_to_delete[$i];
	if (($verify_existence) and (($nkvpairs = mgGetNumberOfKeyValuePairsForRecord($proj_id, $rec_id)) == 0)) {
		printf("%s:\t%s\n", htmlspecialchars($rec_id), "DID NOT EXIST");
		deleteRecord($rec_id);	// But try again, anyway.
	} else {
		deleteRecord($rec_id);
		if (! $verify_deletion) {
			$ndel++;	// Hope it worked (and it probably did)...
			printf("%s\n", htmlspecialchars($rec_id));
		} elseif (($nkvpairs = mgGetNumberOfKeyValuePairsForRecord($proj_id, $rec_id)) == 0) {
			$ndel++;
			printf("%s:\t%s\n", htmlspecialchars($rec_id), "deleted");
		} elseif ($nkvpairs > 0) {
			printf("%s:\t%s (%s)\n", htmlspecialchars($rec_id), "FAILED", $nkvpairs);
		} else {
			printf("%s:\t%s\n", htmlspecialchars($rec_id), "ERROR");
		}
	}
}

echo '</pre></ul>';
printf("<p>Number of records %sdeleted: <b>%d</b>.</p>\n", ($verify_deletion ? "" : "attempted to be "), $ndel);

/*****************************************************************************/
}

// OPTIONAL: Display the project footer
require_once APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
