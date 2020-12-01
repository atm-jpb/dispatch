<?php


set_time_limit(0);

require('../config.php');

//dol_include_once('/' . ATM_ASSET_NAME . '/config.php');
dol_include_once('/' . ATM_ASSET_NAME . '/lib/asset.lib.php');
dol_include_once('/' . ATM_ASSET_NAME . '/class/asset.class.php');
dol_include_once('/product/class/product.class.php');
dol_include_once('/product/class/html.formproduct.class.php');

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
		case 'select-warehouse-default':

			print _getSelectWarehouseWithDefaultSelected(GETPOST('fk_product'));
			break;
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
			__out(_set_line_is_prepared($PDOdb, GETPOST('fk_expeditiondet_asset', 'int'), GETPOST('is_prepared', 'int')), 'json');
			break;

		case 'set_all_lines_is_prepared':
			__out(_set_all_lines_is_prepared($PDOdb, GETPOST('fk_expedition', 'int'), GETPOST('is_prepared', 'int')), 'json');
			break;
	}
}

function _getSelectWarehouseWithDefaultSelected($fk_product) {
	global $db;
	$formproduct = new FormProduct($db);
	$prod = new Product($db);
	$prod->fetch($fk_product);
	return $formproduct->selectWarehouses($prod->fk_default_warehouse,'TLine[-1][entrepot]','',1,0,$prod->id,'',0,1);
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
	dol_include_once('/expedition/class/expedition.class.php');

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

	//Si l'équipement est attribué à une autre expédition qui a le statut brouillon ou validé, on ne le propose pas
    $exp = new Expedition($db);
    if(!empty($expeditionID)) {
        $exp->fetch($expeditionID);
        if($exp->statut == Expedition::STATUS_DRAFT || $exp->statut == Expedition::STATUS_VALIDATED ) {
            $sql.= " AND a.rowid NOT IN (SELECT eda2.fk_asset FROM ".MAIN_DB_PREFIX."expeditiondet_asset as eda2 LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet as ed2 ON (ed2.rowid = eda2.fk_expeditiondet) LEFT JOIN ".MAIN_DB_PREFIX."expedition as e2 ON (e2.rowid = ed2.fk_expedition) WHERE e2.fk_statut < 2)";
        }
    }
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
			HAVING NOT(GROUP_CONCAT(e.rowid) IS NOT NULL AND GROUP_CONCAT(e.rowid, ',') REGEXP '(^|\,)" . $expeditionID .  "(\,|$)')";

//echo $sql;

	$PDOdb->Execute($sql);
	$TAssetIds = $PDOdb->Get_All();

	$Tres = array();
	foreach ($TAssetIds as $res)
	{
		$asset = new TAsset;
		$asset->load($PDOdb, $res->rowid);
		$asset->load_asset_type($PDOdb);

		if($asset->contenancereel_value > 0)
		{
			$Tres[$asset->serial_number]['serial_number'] = $asset->serial_number;
			$Tres[$asset->serial_number]['qty'] = $asset->contenancereel_value;
			$Tres[$asset->serial_number]['unite_string'] = ($asset->assetType->measuring_units == 'unit') ? 'unité(s)' : measuring_units_string($asset->contenancereel_units, $asset->assetType->measuring_units);
			$Tres[$asset->serial_number]['unite'] = ($asset->assetType->measuring_units == 'unit') ? 'unité(s)' : $asset->contenancereel_units;
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
 * @param	TPDOdb	$PDOdb					Database connection
 * @param	int		$fk_expditiondet_asset	ID of expeditiondet_asset line
 * @param	int		$is_prepared			0/1, whether the asset has been prepared or not
 *
 * @return	array		Response array with success and message fields, to be JSON-encoded
 */
function _set_line_is_prepared(TPDOdb &$PDOdb, $fk_expeditiondet_asset, $is_prepared)
{
	global $langs;

	dol_include_once('/dispatch/class/dispatchdetail.class.php');

	$langs->load('dispatch@dispatch');

	$dispatchDetail = new TDispatchDetail;
	$dispatchLoaded = $dispatchDetail->load($PDOdb, $fk_expeditiondet_asset);

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


/**
 * Mark all shipment asset detail lines as prepared or not
 *
 * @param	TPDOdb	$PDOdb					Database connection
 * @param	int		$fk_expedition			ID of expedition
 * @param	int		$is_prepared			0/1, whether the asset has been prepared or not
 *
 * @return	array		Response array with success and message fields, to be JSON-encoded
 */
function _set_all_lines_is_prepared(TPDOdb &$PDOdb, $fk_expedition, $is_prepared)
{
	global $langs;

	dol_include_once('/dispatch/class/dispatchdetail.class.php');

	$langs->load('dispatch@dispatch');

	$sql = 'SELECT eda.rowid
			FROM ' . MAIN_DB_PREFIX . 'expeditiondet_asset eda
			INNER JOIN ' . MAIN_DB_PREFIX .'expeditiondet ed ON (ed.rowid = eda.fk_expeditiondet)
			WHERE ed.fk_expedition = ' . $fk_expedition;

	$TDispatchDetail = $PDOdb->ExecuteAsArray($sql);

	$countFail = 0;

	foreach($TDispatchDetail as $dispatchDetailStatic)
	{
		$TResult = _set_line_is_prepared($PDOdb, $dispatchDetailStatic->rowid, $is_prepared);

		if(empty($TResult['success']))
		{
			$countFail++;
		}
	}

	$message = $langs->trans(empty($is_prepared) ? 'AssetsMarkedAsNotPrepared' : 'AssetsMarkedAsPrepared');

	if($countFail > 0)
	{
		$message = $langs->trans('NAssetsMarkedCouldNotBeMarked', $countFail);
	}

	return array('success' => $countFail == 0, 'message' => $message);
}

