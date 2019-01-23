<?php

define('INC_FROM_CRON_SCRIPT', true);
set_time_limit(0);

require('../config.php');

//dol_include_once('/' . ATM_ASSET_NAME . '/config.php');
dol_include_once('/' . ATM_ASSET_NAME . '/lib/asset.lib.php');
dol_include_once('/' . ATM_ASSET_NAME . '/class/asset.class.php');

//Interface qui renvoie les emprunts de ressources d'un utilisateur
$PDOdb=new TPDOdb;
global $langs;

header('Content-Type: application/json');

$get = GETPOST('get');
_get($PDOdb, $get);

$put = GETPOST('put');
_put($PDOdb, $put);

function _get(&$PDOdb, $get) {

	switch ($get) {
		case 'serial_number':

			__out(_serial_number($PDOdb, GETPOST('term')),'json');

			break;
        case 'autocomplete_asset':
        	__out(_autocomplete_asset($PDOdb,GETPOST('lot_number'),GETPOST('productid'),GETPOST('expeditionid'),GETPOST('expeditiondetid')),'json');
            break;
		case 'autocomplete_lot_number':
            __out(_autocomplete_lot_number($PDOdb,GETPOST('productid')),'json');
            break;
	}

}


function _put(TPDOdb &$PDOdb, $put)
{
	switch($put)
	{
		case 'set_line_is_prepared':
			__out(_set_line_is_prepared($PDOdb, GETPOST('fk_expeditiondet', 'int'), GETPOST('is_prepared', 'int')), 'json');
			break;
	}
}

function _serial_number(&$PDOdb, $sn) {

	$sql = "SELECT DISTINCT(rowid) as id, serial_number
			FROM ".MAIN_DB_PREFIX.ATM_ASSET_NAME."
			WHERE serial_number LIKE '".$sn."%'";
	$PDOdb->Execute($sql);
	$Tab=array();

	while($obj=$PDOdb->Get_line()) {
		/*
		$Tab[]=array(
			'value'=>$obj->id
			,'label'=>$obj->serial_number
		);
		*/

		$Tab[]=$obj->serial_number;
	}

	return $Tab;
}

function _autocomplete_asset(&$PDOdb, $lot_number, $productid, $expeditionID, $expeditionDetID) {
	global $db, $conf, $langs;
	$langs->load('other');

	dol_include_once('/core/lib/product.lib.php');
	dol_include_once('/societe/class/societe.class.php');

	$sql = "SELECT fk_entrepot FROM ".MAIN_DB_PREFIX."expeditiondet WHERE rowid = ".$expeditionDetID." LIMIT 1";

	$societe = new Societe($db);
	$societe->fetch('', $conf->global->MAIN_INFO_SOCIETE_NOM);

	$TWarehouses = $PDOdb->ExecuteAsArray($sql);
	$warehouseID = $TWarehouses[0]->fk_entrepot;

	$sql = "SELECT DISTINCT a.rowid
			FROM ".MAIN_DB_PREFIX.ATM_ASSET_NAME." a
			LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet_asset eda ON (eda.fk_asset = a.rowid)
			LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet ed ON (ed.rowid = eda.fk_expeditiondet)
			LEFT JOIN ".MAIN_DB_PREFIX."expedition e ON (e.rowid = ed.fk_expedition)
			WHERE a.lot_number = '".$lot_number."'
			AND a.fk_product = ".$productid;

	if(! empty($societe->id))
	{
		// Par défaut, dispatch associe un équipement réceptionné par commande fournisseur à une société qui porte le même nom que $mysoc
		$sql.= "
			AND (COALESCE(a.fk_societe_localisation, 0) IN (0, ".$societe->id."))";
	} else {
		$sql.= "
			AND COALESCE(a.fk_societe_localisation, 0) = 0";
	}

	if(! empty($warehouseID)) {
		$sql.= "
			AND a.fk_entrepot = ".$warehouseID;
	}

	$sql.= "
			GROUP BY a.rowid
			HAVING NOT(GROUP_CONCAT(e.rowid) IS NOT NULL AND ".$expeditionID." IN (GROUP_CONCAT(e.rowid)))";

	$PDOdb->Execute($sql);
	$TAssetIds = $PDOdb->Get_All();

	$Tres = array();
	foreach ($TAssetIds as $res) {

		$asset = new TAsset;
		$asset->load($PDOdb, $res->rowid);
		$asset->load_asset_type($PDOdb);

		//pre($asset,true);

		if($PDOdb->Get_field('contenancereel_value') > 0) {

			$Tres[$PDOdb->Get_field('serial_number')]['serial_number'] = $PDOdb->Get_field('serial_number');
			$Tres[$PDOdb->Get_field('serial_number')]['qty'] = $PDOdb->Get_field('contenancereel_value');
			$Tres[$PDOdb->Get_field('serial_number')]['unite_string'] = ($asset->assetType->measuring_units == 'unit') ? 'unité(s)' : measuring_units_string($PDOdb->Get_field('contenancereel_units'),$asset->assetType->measuring_units);
			$Tres[$PDOdb->Get_field('serial_number')]['unite'] = ($asset->assetType->measuring_units == 'unit') ? 'unité(s)' : $PDOdb->Get_field('contenancereel_units');
		}
	}
	return $Tres;
}

function _autocomplete_lot_number(&$PDOdb, $productid) {
	global $db, $conf, $langs;
	$langs->load('other');
	dol_include_once('/core/lib/product.lib.php');

	$sql = "SELECT DISTINCT(lot_number),rowid, SUM(contenancereel_value) as qty, contenancereel_units as unit
			FROM ".MAIN_DB_PREFIX.ATM_ASSET_NAME."
			WHERE fk_product = ".$productid." GROUP BY lot_number,contenancereel_units,rowid";
	$PDOdb->Execute($sql);

	$TLotNumber = array('');
	$PDOdb->Execute($sql);
	$Tres = $PDOdb->Get_All();
	foreach($Tres as $res){

		$asset = new TAsset;
		$asset->load($PDOdb, $res->rowid);
		$asset->load_asset_type($PDOdb);
		//pre($asset,true);exit;
		$TLotNumber[$res->lot_number]['lot_number'] = $res->lot_number;
		$TLotNumber[$res->lot_number]['label'] = $res->lot_number." / ".$res->qty." ".(($asset->assetType->measuring_units == 'unit') ? 'unité(s)' : measuring_units_string($res->unit,$asset->assetType->measuring_units));
	}
	return $TLotNumber;
}


/**
 * Mark a shipment asset detail line as prepared
 *
 * @param	TPDOdb	$PDOdb				Database connection
 * @param	int		$fk_expditiondet	ID of expedition line
 * @param	int		$is_prepared		0/1, whether the asset has been prepared or not
 *
 * @return	array		Response array with success and message fields, to be JSON-encoded
 */
function _set_line_is_prepared(TPDOdb &$PDOdb, $fk_expeditiondet, $is_prepared)
{
	global $langs;

	dol_include_once('/dispatch/class/dispatchdetail.class.php');

	$langs->load('dispatch@dispatch');

	$dispatchDetail = new TDispatchDetail;
	$dispatchLoaded = $dispatchDetail->loadBy($PDOdb, $fk_expeditiondet, 'fk_expeditiondet');

	if(empty($dispatchLoaded))
	{
		return array('success' => false, 'message' => $langs->trans('CouldNotLoadAssetDetail'));
	}

	$dispatchDetail->is_prepared = $is_prepared;

	$dispatchID = $dispatchDetail->save($PDOdb);

	if(empty($dispatchID))
	{
		return array('success' => false, 'message' => $langs->trans('CouldNotSaveAssetDetail'));
	}

	$message = $langs->trans(empty($is_prepared) ? 'AssetMarkedAsNotPrepared' : 'AssetMarkedAsPrepared');

	return array('success' => true, 'message' => $message);
}

