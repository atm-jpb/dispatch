<?php

define('INC_FROM_CRON_SCRIPT', true);
set_time_limit(0);

require('../config.php');

dol_include_once('/' . ATM_ASSET_NAME . '/lib/asset.lib.php');
dol_include_once('/' . ATM_ASSET_NAME . '/class/asset.class.php');
require_once DOL_DOCUMENT_ROOT. '/expedition/class/expedition.class.php';

//Interface qui renvoie les emprunts de ressources d'un utilisateur
$PDOdb=new TPDOdb;
// Load traductions files requiredby by page
$langs->loadLangs(array("dispatch@dispatch", "other", 'main'));

header('Content-Type: application/json');

// $idexpe = GETPOST('idexpe');
$idexpe = dol_htmlentitiesbr(GETPOST('idexpe'));
$refexpe = dol_htmlentitiesbr(GETPOST('refexpe'));
$entity = dol_htmlentitiesbr(GETPOST('entity'));
$action = dol_htmlentitiesbr(GETPOST('action'));
$idCommand = dol_htmlentitiesbr(GETPOST('comFourn'));
$idWarehouse = dol_htmlentitiesbr(GETPOST('idWarehouse'));

$JsonOutput = new stdClass();


// LoadLinesExpedition
if (isset($action) && $action == 'loadExpeLines'){

	$currentExp = new Expedition($db);
	$currentExp->fetch($idexpe);

	$output  = load_fiche_titre($langs->trans("NbItemCountInReception" ). ' '.$currentExp->ref);

	$JsonOutput->html = $output;
	_getEquipmentsFromSupplier($currentExp, $JsonOutput);
	$JsonOutput->html .= '<form action='.dol_buildpath('dispatch/receptionofsom.php?id='.$idCommand, 1).' method="POST" name="products-dispatch">';
	$JsonOutput->html .= _formatDisplayTableProductsHeader();
	$JsonOutput->html .= _formatDisplayTableProducts($currentExp,$entity, $idCommand, $idWarehouse);
	$JsonOutput->html .= '</form>';
}
print json_encode($JsonOutput);


/**
 * La commande client générée automatiquement chez (Entité A)
 * depuis une commande fournisseur passée par entité B (pour son founisseur Entité A)
 * ne possède pas le descriptif des equipements.
 * Nous devons le loader pour exploitation  de l'expedition courante
 * @var Expedition $currentExpe
 * @param $currentExpe
 *
 */
function _getEquipmentsFromSupplier(&$currentExpe, &$JsonOutput)
{
	global $langs, $db;

	if (!empty($currentExpe->lines)){
		foreach ($currentExpe->lines as $currentLineExp) {

			// On remonte les equipements si l'expedition en possède ...
			$sql = "SELECT * FROM " . MAIN_DB_PREFIX . "expeditiondet_asset AS ea ";
			$sql .= " INNER JOIN " . MAIN_DB_PREFIX . "assetatm AS at ON ea.fk_asset = at.rowid ";
			$sql .= " WHERE ea.fk_expeditiondet = " . $currentLineExp->id;

			$resultsetEquipments = $db->query($sql);
			if (!$resultsetEquipments) {
				$JsonOutput->error = $langs->trans('ResultSetEquipmentError');
			}

			$num = $db->num_rows($resultsetEquipments);
			$i = 0;
			$objs = array();
			while ($i < $num) {
				$objs[$i]['obj'] = $db->fetch_object($resultsetEquipments);
				$i++;
			}
			// On ajoute les lignes d'infos équipements présents
			$currentLineExp->equipement = $objs;
		}
	}
}


/**
 * @return string
 */
function _formatDisplayTableProductsHeader(){
	global $conf, $langs,$db;

	$output = "";
	$output .= "<table width='100%' class='noborder' id='dispatchAsset'>";
	$output .='<tr class="liste_titre">';
	$output .='<td>'.$langs->trans('Product') .'</td>';
	$output .='<td>'.$langs->trans('DispatchSerialNumber').'</td>';
	if(! empty($conf->global->USE_LOT_IN_OF)) {
		$output .='<td>'.$langs->trans('DispatchBatchNumber').'</td>';
	}
	$output .='<td>'.$langs->trans('Warehouse').'</td>';
	if($conf->global->ASSET_SHOW_DLUO){
		$output .='<td>DLUO</td>';
	}
	if(empty($conf->global->DISPATCH_USE_ONLY_UNIT_ASSET_RECEPTION)) {
		$output .='<td>'.$langs->trans('Quantity').'</td>';
		if ( ! empty($conf->global->DISPATCH_SHOW_UNIT_RECEPTION) ) {
			$output .= '<td>' . $langs->trans('Unit') . '</td>';
		}
	}
	if($conf->global->clinomadic->enabled){
		$output .='<td>IMEI</td>';
		$output .='<td>Firmware</td>';
	}

	$output .='<td>&nbsp;</td>';
	$output .='</tr>';

	return $output;

}

/**
 * @param Expedition $currentExp
 * @param $entity
 * @return string
 */
function _formatDisplayTableProducts(&$currentExp,$entity, $idCommand, $idWarehouse){

	global $conf, $langs, $db;

	$form = new TFormCore();
	$prod = new Product($db);
	$output = '';

	$TEquipements = array();
	$TStandard = array();

	if (!empty($currentExp->lines)) {
		foreach ($currentExp->lines as $k => $line) {

			if ($line->equipement) {
				foreach ($line->equipement as $key => $eq) {
					$eq['obj']->ref = $line->ref;
					$eq['obj']->qty = 1;
					$TEquipements[] = $eq;
				}
			} else {
				$TStandard[] = $line;
			}
		}
	}

	$TAllProductsAndAssets = array_merge($TEquipements, $TStandard);

	foreach ($TAllProductsAndAssets as $key=>$line) {
		if (is_array($line)) {
			$fk_asset = $line['obj']->fk_asset;
		}else $fk_asset = "standardProduct";

		is_array($line) ? $prod->fetch($line['obj']->fk_product) : $prod->fetch($line->fk_product);

		$output .="<tr class='dispatchAssetLine oddeven' id='dispatchAssetLine'".$key."' data-fk-product='".$prod->id."'>";
		$output .="<td>".
			$prod->getNomUrl(1).
			$form->hidden('TLine['.$key.'][fk_product]', $prod->id).
			$form->hidden('TLine['.$key.'][ref]', $prod->ref)." - ".
			$prod->label.
			$form->hidden('TLine['.$key.'][fk_asset]', $fk_asset).
			"</td>";
		$output .='<td>';
		if (is_object($line)) {
			$output .= $form->hidden('TLine['.$key.'][subprice]', $line->subprice);
			$output .= $form->hidden('TLine['.$key.'][supplier_price]', $line->supplier_price);
			$output .= $form->hidden('TLine['.$key.'][supplier_qty]', $line->supplier_qty);
			$output .= $form->hidden('TLine['.$key.'][generate_supplier_tarif]', $line->generate_supplier_tarif);

		}

		if (is_array($line)) {
			$output .= $form->texte('', 'TLine[' . $key . '][numserie]', $line['obj']->serial_number, 30);

            $output .= $form->hidden('TLine['.$key.'][numlog]', $line['obj']->numlog);
            $output .= $form->hidden('TLine['.$key.'][codebarre]', $line['obj']->codebarre);
            $output .= $form->hidden('TLine['.$key.'][vpn]', $line['obj']->vpn);
            $output .= $form->hidden('TLine['.$key.'][notes]', $line['obj']->notes);
		}
		$output .= '</td>';

		// ENTREPOT
		$output .='<td rel="entrepotChild" fk_product="'.$prod->id.'">';
		require_once DOL_DOCUMENT_ROOT.'/product/class/html.formproduct.class.php';

		$formproduct=new FormProduct($db);
		$backupEntity = $conf->entity;

		$conf->entity = $entity;
		$formproduct->loadWarehouses();

		if (count($formproduct->cache_warehouses) > 1) {

			$output .=$formproduct->selectWarehouses($idWarehouse, 'TLine['.$key.'][entrepot]','',1,0,$prod->id,'',1);
		} elseif  (count($formproduct->cache_warehouses)==1) {
			$output .=$formproduct->selectWarehouses($idWarehouse, 'TLine['.$key.'][entrepot]','',0,0,$prod->id,'',0,1);
		} else {
			$output .= $langs->trans("NoWarehouseDefined");
		}

		$output .='</td>';

		// QTY
		if (is_array($line)) {
			$output .= "<td>" . $form->hidden('TLine['.$key.'][quantity]', 1) . "1</td>";
		}
		else {
			$output .='<td>'. $form->hidden('TLine['.$key.'][quantity]', $line->qty_shipped). $line->qty_shipped.'</td>';
		}

		// LOTS
		if(! empty($conf->global->USE_LOT_IN_OF)) {
			$output .= "<td>".$form->texte('','TLine['.$key.'][lot_number]', $line->lot_number, 30)."</td>";
		}

		// DLUO
		if(!empty($conf->global->ASSET_SHOW_DLUO)){
			$output .='<td>'.$form->calendrier('','TLine['.$k.'][dluo]', date('d/m/Y',strtotime($line['dluo']))).'</td>';
		}

		if(empty($conf->global->DISPATCH_USE_ONLY_UNIT_ASSET_RECEPTION)) {
			//					if(!empty($conf->global->DISPATCH_SHOW_UNIT_RECEPTION)) {
			//						echo '<td>'. ($commande->statut < 5) ? $formproduct->select_measuring_units('TLine['.$k.'][quantity_unit]','weight',$line['quantity_unit']) : measuring_units_string($line['quantity_unit'],'weight').'</td>';
			//					}
		}
		else{
			if (is_array($line)) {
				$output .= "<td>" . $form->hidden('TLine['.$key.'][quantity]', 1) . "1</td>";
			}
			else {
				$output .='<td>'. $form->hidden('TLine['.$key.'][quantity]', $line->qty_shipped). $line->qty_shipped.'</td>';
			}
			$output .=$form->hidden('TLine['.$key.'][quantity_unit]',$line->quantity_unit);
		}

		if($conf->global->clinomadic->enabled){

			$output .='<td>'.$form->texte('','TLine['.$key.'][imei]', $line->imei, 30).'</td>';
			$output .='<td>'.$form->texte('','TLine['.$key.'][firmware]', $line->firmware, 30).'</td>';
		}

		$output .='<td>';
		$output .='</td>';
		$output .='</tr>';
		$conf->entity =  $backupEntity;
	}


	$output .= '<tr><td></td><td></td>';
	$output .= '<td></td><br/>';
	$output .= '<td><a class="butActionDelete pull-right " >'.$langs->trans("Annuler").'</a></td><br/>';
	$output .= '</tr>';


	$output .=  '<tr><td colspan="4"><div id="actionVentilation">';
	$output .=  $langs->trans("DispatchDateReception").' : '.$form->calendrier('', 'date_recep', time());

	$output .=  $langs->trans("Comment").' : '.$form->texte('', 'comment', !empty($comment)?$comment:'', 60,128);

	$output .=  $form->btsubmit($langs->trans('AssetVentil'), 'bt_create', '', 'butAction butValidateVentilation');
	$output .=  $form->hidden('data-shipment-treated-id', $currentExp->id);

	// On remonte l'entité liée à la société
	$soc = new Societe($db);
	//var_dump($currentExp->id);
	$res = $soc->fetch($currentExp->socid);
	if ($res){
		$output .=  $form->hidden('data-shipment-entity', $soc->entity);
	}
	$output .=  '</td></tr></div>';
	$warning_asset = false;
	return $output;

}
