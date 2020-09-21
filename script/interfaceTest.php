<?php

define('INC_FROM_CRON_SCRIPT', true);
set_time_limit(0);

require('../config.php');

//dol_include_once('/' . ATM_ASSET_NAME . '/config.php');
dol_include_once('/' . ATM_ASSET_NAME . '/lib/asset.lib.php');
dol_include_once('/' . ATM_ASSET_NAME . '/class/asset.class.php');
dol_include_once('/expedition/class/expedition.class.php' );
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

$JsonOutput = new stdClass();

// on genere l'UI autoCompletée avec le bouton enregistrer

// on flag l'expedition à traitée

// le client reload la page  et affiche les expé non traité.

// LoadLinesExpedition
if (isset($action) && $action == 'loadExpeLines'){

	$currentExp = new Expedition($db);
	$currentExp->fetch($idexpe);

	$output  = load_fiche_titre($langs->trans("NbItemCountInReception" ). ' '.$currentExp->ref);

	$JsonOutput->html = $output;
	getEquipmentsFromSupplier($currentExp);
	$JsonOutput->html .= '<form action='.dol_buildpath('dispatch/reception.php?id='.$idCommand, 1).' method="POST" name="products-dispatch">';
	$JsonOutput->html .= formatDisplayTableProductsHeader();
	$JsonOutput->html .= formatDisplayTableProducts($currentExp,$entity, $idCommand);
	$JsonOutput->html .= '</form>';
}
print json_encode($JsonOutput);
/**
 * la commande client générée automatiquement chez (Entité A)
 * depuis une commande fournisseur passée par entité B (pour son founisseur Entité A)
 * ne possède pas le descriptif des equipements.
 * nous devons le loader pour exploitation  de l'expedition courante
 * @param $currentExpe
 *
 */
function getEquipmentsFromSupplier(&$currentExpe){
	global $langs,$db;


	foreach ($currentExpe->lines as $currentLineExp) {

		// $currentLineExp donne les infos suivantes :   product id // qty // qty shiped  // label

		// on remonte les equipements si l'expedition en possède ...
		$sql = "SELECT * FROM ".MAIN_DB_PREFIX."expeditiondet_asset AS ea, ".MAIN_DB_PREFIX."assetatm AS AT WHERE fk_expeditiondet = ".$currentLineExp->id." AND ea.fk_asset = AT.rowid" ;


		$resultsetEquipments = $db->query($sql);
		$num = $db->num_rows($resultsetEquipments);

		$i = 0;
		$objs = array();
		while( $i < $num){
			$objs[$i]['obj'] = $db->fetch_object($resultsetEquipments);
			$i++;
		}
		// on ajoute les lignes d'infos équipements présents
		$currentLineExp->equipement = $objs;
	}

}


function formatDisplayTableProductsHeader(){
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

	//$parameters=array('commande'=>$commande);
	//$reshook=$hookmanager->executeHooks('printFieldListTitle',$parameters);    // Note that $action and $object may have been modified by hook
	//print $hookmanager->resPrint;

	$output .='<td>&nbsp;</td>';
	$output .='</tr>';

return $output;

}
function formatDisplayTableProducts(&$currentExp,$entity, $idCommand){

	global $conf, $langs, $db;
	dol_include_once('/core/class/html.form.class.php');

	$form = new TFormCore();
	$prod = new Product($db);
	$output = '';

	foreach ($currentExp->lines as $k=>$line) {
		$prod->fetch($line->fk_product);
		//print_r ($line);
		if ($line->equipement){

			foreach ($line->equipement as $key=>$eq){
				// equipements
				var_dump($eq);
				exit;

				// $asset=new TAsset;
				$output .="<tr class='dispatchAssetLine oddeven' id='dispatchAssetLine'".$key."' data-fk-product='".$prod->id."'>";
				$output .="<td>".$prod->getNomUrl(1).$form->hidden('TLine['.$key.'][fk_product]', $prod->id).$form->hidden('TLine['.$key.'][ref]', $prod->ref)." - ".$prod->label."</td>";
				$output .='<td>';

				$output .=$form->texte('','TLine['.$key.'][numserie]', $eq['obj']->serial_number, 30);
				//$warning_asset = true;
				//$output .= $form->hidden('TLine['.$k.'][commande_fournisseurdet_asset]', $line->commande_fournisseurdet_asset, 30);
				$output .= '</td>';

				// ENTREPOT
				$output .='<td rel="entrepotChild" fk_product="'.$prod->id.'">';
				dol_include_once('/product/class/html.formproduct.class.php');

				$formproduct=new FormProduct($db);
				$backupEntity = $conf->entity;

				$conf->entity = $entity;
				$formproduct->loadWarehouses();

				if (count($formproduct->cache_warehouses) > 1) {
							  //$formproduct->selectWarehouses($lines[$i]->entrepot_id, 'entl'.$line_id, '', 1, 0, $lines[$i]->fk_product, '', 1)
					$output .=$formproduct->selectWarehouses($line->fk_warehouse, 'TLine['.$key.'][entrepot]','',1,0,$prod->id,'',1);
				} elseif  (count($formproduct->cache_warehouses)==1) {
					$output .=$formproduct->selectWarehouses($line->fk_warehouse, 'TLine['.$key.'][entrepot]','',0,0,$prod->id,'',0,1);
				} else {
					$output .= $langs->trans("NoWarehouseDefined");
				}

				$output .='</td>';

				// qty
				$output .='<td>1</td>';
			}

		}else{
			// product
			$output .="<tr class='dispatchAssetLine oddeven' id='dispatchAssetLine'".$k."' data-fk-product='".$prod->id."'>";
			$output .="<td>".$prod->getNomUrl(1).$form->hidden('TLine['.$k.'][fk_product]', $prod->id).$form->hidden('TLine['.$k.'][ref]', $prod->ref)." - ".$prod->label."</td>";
			$output .='<td></td>';

			// ENTREPOT
			$output .='<td rel="entrepotChild" fk_product="'.$prod->id.'">';
			dol_include_once('/product/class/html.formproduct.class.php');

			$formproduct=new FormProduct($db);
			$backupEntity = $conf->entity;

			$conf->entity = $entity;
			$formproduct->loadWarehouses();

			if (count($formproduct->cache_warehouses) > 1) {

				$output .=$formproduct->selectWarehouses($line->fk_warehouse, 'TLine['.$k.'][entrepot]','',1,0,$prod->id,'',1);
			} elseif  (count($formproduct->cache_warehouses)==1) {
			    $formproduct->selectWarehouses($line->fk_warehouse, 'TLine['.$k.'][entrepot]','',0,0,$prod->id,'',1);
			} else {
				$output .= $langs->trans("NoWarehouseDefined");
			}

			$output .='</td>';




			// qty
			$output .='<td>'.$line->qty_shipped.'</td>';

			//$output .= $form->hidden('TLine['.$k.'][commande_fournisseurdet_asset]', $line->commande_fournisseurdet_asset, 30);

		}


		//LOTS
		if(! empty($conf->global->USE_LOT_IN_OF)) {
			 $output .= "<td>".$form->texte('','TLine['.$k.'][lot_number]', $line->lot_number, 30)."</td>";
		}

			//dluo
			if(!empty($conf->global->ASSET_SHOW_DLUO)){
					//$output .='<td>'.$form->calendrier('','TLine['.$k.'][dluo]', date('d/m/Y',strtotime($line['dluo']))).'</td>';
			}

			if(empty($conf->global->DISPATCH_USE_ONLY_UNIT_ASSET_RECEPTION)) {

			//$output .='<td>'.$form->texte('','TLine['.$k.'][quantity]', $line->quantity, 10).'</td>';

//					if(!empty($conf->global->DISPATCH_SHOW_UNIT_RECEPTION)) {
//						echo '<td>'. ($commande->statut < 5) ? $formproduct->select_measuring_units('TLine['.$k.'][quantity_unit]','weight',$line['quantity_unit']) : measuring_units_string($line['quantity_unit'],'weight').'</td>';
//					}
			}
			else{
					$output .= $form->hidden('TLine['.$k.'][quantity]', $line->quantity);
					$output .=$form->hidden('TLine['.$k.'][quantity_unit]',$line->quantity_unit);
				}

			if($conf->global->clinomadic->enabled){

				$output .='<td>'.$form->texte('','TLine['.$k.'][imei]', $line->imei, 30).'</td>';
				$output .='<td>'.$form->texte('','TLine['.$k.'][firmware]', $line->firmware, 30).'</td>';
			}
//		$parameters=array('line' => $line, 'prod' => $prod, 'k' => $k);
//		$reshook=$hookmanager->executeHooks('printFieldListValue',$parameters);    // Note that $action and $object may have been modified by hook
//		print $hookmanager->resPrint;

		$output .='<td>';

//					if($commande->statut < 5 && $line['commande_fournisseurdet_asset'] > 0){
//						echo '<a href="?action=DELETE_LINE&k='.$k.'&id='.$commande->id.'&rowid='.$line['commande_fournisseurdet_asset'].'">'.img_delete().'</a>';
//					}

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
	$output .=  '</td></tr></div>';

	$warning_asset = false;
	return $output;

}

