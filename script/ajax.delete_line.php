<?php
require("../config.php");
dol_include_once('/' . ATM_ASSET_NAME . '/class/asset.class.php');

if(isset($_POST['id_detail'])){
	$id_detail = $_POST['id_detail'];
}
else
	return 0;

$ATMdb = new TPDOdb;

$sql = "DELETE FROM ".MAIN_DB_PREFIX."expeditiondet_asset WHERE rowid = ".$id_detail;
$ATMdb->Execute($sql);

return 1;