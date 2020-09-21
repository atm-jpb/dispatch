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
			$objs[$i]['sql'] = $sql;
			$objs[$i]['num'] = $num;
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


	return $output;
	$warning_asset = false;
	/*

				<tr class="dispatchAssetLine oddeven" id="dispatchAssetLine<?php print $k; ?>" data-fk-product="<?php print $prod->id; ?>">
				<td><?php echo $prod->getNomUrl(1).$form->hidden('TLine['.$k.'][fk_product]', $prod->id).$form->hidden('TLine['.$k.'][ref]', $prod->ref)." - ".$prod->label; ?></td>
				<td><?php
						$asset=new TAsset;

						if(empty($line['numserie'])) {
							echo $form->texte('','TLine['.$k.'][numserie]', $line['numserie'], 30).' '.img_picto($langs->trans('SerialNumberNeeded'), 'warning.png');
							$warning_asset = true;
						}
						else if($asset->loadReference($PDOdb, $line['numserie'], $line['fk_product'])) {
							if($commande->statut >= 5 || $commande->statut<=2) {
								echo $asset->getNomUrl(1);
							} else {
								echo $form->texte('','TLine['.$k.'][numserie]', $line['numserie'], 30).' '.img_picto($langs->trans('AssetAlreadyLinked'), 'warning.png');
							}
						}
						else {
							echo $form->texte('','TLine['.$k.'][numserie]', $line['numserie'], 30).' '.img_picto($langs->trans('NoAssetCreated'), 'info.png');
							$warning_asset = true;
						}
						echo $form->hidden('TLine['.$k.'][commande_fournisseurdet_asset]', $line['commande_fournisseurdet_asset'], 30)
						?>
					</td>
					<?php if(! empty($conf->global->USE_LOT_IN_OF)) { ?>
						<td><?php echo $form->texte('','TLine['.$k.'][lot_number]', $line['lot_number'], 30);   ?></td>
					<?php } ?>
					<td rel="entrepotChild" fk_product="<?php echo $prod->id ?>"><?php

						$formproduct=new FormProduct($db);
						$formproduct->loadWarehouses();

						if (count($formproduct->cache_warehouses)>1)
						{
							print $formproduct->selectWarehouses($line['fk_warehouse'], 'TLine['.$k.'][entrepot]','',1,0,$prod->id,'',0,1);
						}
						elseif  (count($formproduct->cache_warehouses)==1)
						{
							print $formproduct->selectWarehouses($line['fk_warehouse'], 'TLine['.$k.'][entrepot]','',0,0,$prod->id,'',0,1);
						}
						else
						{
							print $langs->trans("NoWarehouseDefined");
						}

						?></td>
					<?php if(!empty($conf->global->ASSET_SHOW_DLUO)){ ?>
						<td><?php echo $form->calendrier('','TLine['.$k.'][dluo]', date('d/m/Y',strtotime($line['dluo'])));   ?></td>
					<?php }

					if(empty($conf->global->DISPATCH_USE_ONLY_UNIT_ASSET_RECEPTION)) {
						?>
						<td><?php echo $form->texte('','TLine['.$k.'][quantity]', $line['quantity'], 10);   ?></td><?php

						if(!empty($conf->global->DISPATCH_SHOW_UNIT_RECEPTION)) {
							echo '<td>'. ($commande->statut < 5) ? $formproduct->select_measuring_units('TLine['.$k.'][quantity_unit]','weight',$line['quantity_unit']) : measuring_units_string($line['quantity_unit'],'weight').'</td>';
						}
					}
					else{
						echo $form->hidden('TLine['.$k.'][quantity]', $line['quantity']);
						echo $form->hidden('TLine['.$k.'][quantity_unit]',$line['quantity_unit']);
					}

					if($conf->global->clinomadic->enabled){
						?>
						<td><?php echo $form->texte('','TLine['.$k.'][imei]', $line['imei'], 30)   ?></td>
						<td><?php echo $form->texte('','TLine['.$k.'][firmware]', $line['firmware'], 30)   ?></td>
						<?php
					}
					$parameters=array('line' => $line, 'prod' => $prod, 'k' => $k);
					$reshook=$hookmanager->executeHooks('printFieldListValue',$parameters);    // Note that $action and $object may have been modified by hook
					print $hookmanager->resPrint;
					?>
					<td>test
						<?php
						if($commande->statut < 5 && $line['commande_fournisseurdet_asset'] > 0){
							echo '<a href="?action=DELETE_LINE&k='.$k.'&id='.$commande->id.'&rowid='.$line['commande_fournisseurdet_asset'].'">'.img_delete().'</a>';
						}
						?>
					</td>
					</tr>
					<?php

				}
			}

		if($commande->statut < 5 && $commande->statut>2){

				$TProducts = array($langs->transnoentities('DispatchSelectProduct'));
				foreach($commande->lines as $line){
					if($line->fk_product) $TProducts[$line->fk_product] = $line->product_ref." - ".$line->product_label;
				}

				$defaultDLUO = '';
				if($conf->global->DISPATCH_DLUO_BY_DEFAULT){
					$defaultDLUO = date('d/m/Y',strtotime(date('Y-m-d')." ".$conf->global->DISPATCH_DLUO_BY_DEFAULT));
				}

				echo $defaultDLUO;

				?><tr style="background-color: lightblue;">
				<td><?php print $form->combo('', 'new_line_fk_product', $TProducts, ''); ?></td>
				<td><?php echo $form->texte('','TLine[-1][numserie]', '', 30); ?></td>
				<?php if(! empty($conf->global->USE_LOT_IN_OF)) { ?>
					<td><?php echo $form->texte('','TLine[-1][lot_number]', '', 30);   ?></td>
				<?php } ?>
				<td><?php

					$formproduct=new FormProduct($db);
					$formproduct->loadWarehouses();

					if (count($formproduct->cache_warehouses)>1)
					{
						print $formproduct->selectWarehouses('', 'TLine[-1][entrepot]','',1,0,$prod->id,'',0,1);
					}
					elseif  (count($formproduct->cache_warehouses)==1)
					{
						print $formproduct->selectWarehouses('', 'TLine[-1][entrepot]','',0,0,$prod->id,'',0,1);
					}
					else
					{
						print $langs->trans("NoWarehouseDefined");
					}

					?></td>
				<?php if(!empty($conf->global->ASSET_SHOW_DLUO)){ ?>
					<td><?php echo $form->calendrier('','TLine[-1][dluo]',$defaultDLUO);  ?></td>
				<?php }

				if(empty($conf->global->DISPATCH_USE_ONLY_UNIT_ASSET_RECEPTION)) {
					?>
					<td><?php echo $form->texte('','TLine[-1][quantity]', '', 10);   ?></td><?php

					if(!empty($conf->global->DISPATCH_SHOW_UNIT_RECEPTION)) {
						echo '<td>'.$formproduct->select_measuring_units('TLine[-1][quantity_unit]','weight').'</td>';
					}

				}

				if($conf->global->clinomadic->enabled){
					?>
					<td><?php echo $form->texte('','TLine[-1][imei]', '', 30);   ?></td>
					<td><?php echo $form->texte('','TLine[-1][firmware]', '', 30);   ?></td>
					<?php
				}

				$parameters=array('line' => $line, 'prod' => $prod, 'k' => -1);
				$reshook=$hookmanager->executeHooks('printFieldListValue',$parameters);    // Note that $action and $object may have been modified by hook
				print $hookmanager->resPrint;

				?>
				<td>Nouveau
				</td>
				</tr>
				<?php
			}



		</table>
		if(is_array($TImport)){
			foreach ($TImport as $k=>$line) {

				if($prod->id==0 || $line['ref']!= $prod->ref) {
					if(empty($line['fk_product']) === false) {
						$prod->fetch($line['fk_product']);
					} else if (empty($line['ref']) === false) {
						$prod->fetch('', $line['ref']);
					} else {
						continue;
					}
				}

				?><tr class="dispatchAssetLine oddeven" id="dispatchAssetLine<?php print $k; ?>" data-fk-product="<?php print $prod->id; ?>">
				<td><?php echo $prod->getNomUrl(1).$form->hidden('TLine['.$k.'][fk_product]', $prod->id).$form->hidden('TLine['.$k.'][ref]', $prod->ref)." - ".$prod->label; ?></td>
				<td><?php
					$asset=new TAsset;

					if(empty($line['numserie'])) {
						echo $form->texte('','TLine['.$k.'][numserie]', $line['numserie'], 30).' '.img_picto($langs->trans('SerialNumberNeeded'), 'warning.png');
						$warning_asset = true;
					}
					else if($asset->loadReference($PDOdb, $line['numserie'], $line['fk_product'])) {
						if($commande->statut >= 5 || $commande->statut<=2) {
							echo $asset->getNomUrl(1);
						} else {
							echo $form->texte('','TLine['.$k.'][numserie]', $line['numserie'], 30).' '.img_picto($langs->trans('AssetAlreadyLinked'), 'warning.png');
						}
					}
					else {
						echo $form->texte('','TLine['.$k.'][numserie]', $line['numserie'], 30).' '.img_picto($langs->trans('NoAssetCreated'), 'info.png');
						$warning_asset = true;
					}
					echo $form->hidden('TLine['.$k.'][commande_fournisseurdet_asset]', $line['commande_fournisseurdet_asset'], 30)
					?>
				</td>
				<?php if(! empty($conf->global->USE_LOT_IN_OF)) { ?>
					<td><?php echo $form->texte('','TLine['.$k.'][lot_number]', $line['lot_number'], 30);   ?></td>
				<?php } ?>
				<td rel="entrepotChild" fk_product="<?php echo $prod->id ?>"><?php

					$formproduct=new FormProduct($db);
					$formproduct->loadWarehouses();

					if (count($formproduct->cache_warehouses)>1)
					{
						print $formproduct->selectWarehouses($line['fk_warehouse'], 'TLine['.$k.'][entrepot]','',1,0,$prod->id,'',0,1);
					}
					elseif  (count($formproduct->cache_warehouses)==1)
					{
						print $formproduct->selectWarehouses($line['fk_warehouse'], 'TLine['.$k.'][entrepot]','',0,0,$prod->id,'',0,1);
					}
					else
					{
						print $langs->trans("NoWarehouseDefined");
					}

					?></td>
				<?php if(!empty($conf->global->ASSET_SHOW_DLUO)){ ?>
					<td><?php echo $form->calendrier('','TLine['.$k.'][dluo]', date('d/m/Y',strtotime($line['dluo'])));   ?></td>
				<?php }

				if(empty($conf->global->DISPATCH_USE_ONLY_UNIT_ASSET_RECEPTION)) {
					?>
					<td><?php echo $form->texte('','TLine['.$k.'][quantity]', $line['quantity'], 10);   ?></td><?php

					if(!empty($conf->global->DISPATCH_SHOW_UNIT_RECEPTION)) {
						echo '<td>'. ($commande->statut < 5) ? $formproduct->select_measuring_units('TLine['.$k.'][quantity_unit]','weight',$line['quantity_unit']) : measuring_units_string($line['quantity_unit'],'weight').'</td>';
					}
				}
				else{
					echo $form->hidden('TLine['.$k.'][quantity]', $line['quantity']);
					echo $form->hidden('TLine['.$k.'][quantity_unit]',$line['quantity_unit']);
				}

				if($conf->global->clinomadic->enabled){
					?>
					<td><?php echo $form->texte('','TLine['.$k.'][imei]', $line['imei'], 30)   ?></td>
					<td><?php echo $form->texte('','TLine['.$k.'][firmware]', $line['firmware'], 30)   ?></td>
					<?php
				}
				$parameters=array('line' => $line, 'prod' => $prod, 'k' => $k);
				$reshook=$hookmanager->executeHooks('printFieldListValue',$parameters);    // Note that $action and $object may have been modified by hook
				print $hookmanager->resPrint;
				?>
				<td>test
					<?php
					if($commande->statut < 5 && $line['commande_fournisseurdet_asset'] > 0){
						echo '<a href="?action=DELETE_LINE&k='.$k.'&id='.$commande->id.'&rowid='.$line['commande_fournisseurdet_asset'].'">'.img_delete().'</a>';
					}
					?>
				</td>
				</tr>
				<?php

			}
		} */

}
function printLine() {

	global $langs,$conf;

	print load_fiche_titre($langs->trans("DispatchItemCountReception", count($TImport)), '', '');

	?>
	<script type="text/javascript">
		$(document).ready(function() {
			$("#dispatchAsset").change(function() {
				$("#actionVentilation").addClass("error").html("<?php echo $langs->trans('SaveBeforeVentil') ?>");
			});
		});
	</script>
	<table width="100%" class="noborder" id="dispatchAsset">
		<tr class="liste_titre">
			<td><?php echo $langs->trans('Product') ?></td>
			<td><?php print $langs->trans('DispatchSerialNumber'); ?></td>
			<?php if(! empty($conf->global->USE_LOT_IN_OF)) { ?>
				<td><?php print $langs->trans('DispatchBatchNumber'); ?></td>
			<?php } ?>
			<td><?php echo $langs->trans('Warehouse'); ?></td>
			<?php if($conf->global->ASSET_SHOW_DLUO){ ?>
				<td>DLUO</td>
			<?php }
			if(empty($conf->global->DISPATCH_USE_ONLY_UNIT_ASSET_RECEPTION)) { ?>
				<td><?php print $langs->trans('Quantity'); ?></td>
				<?php
				if ( ! empty($conf->global->DISPATCH_SHOW_UNIT_RECEPTION) ) {
					echo '<td>' . $langs->trans('Unit') . '</td>';
				}
			}
			if($conf->global->clinomadic->enabled){
				?>
				<td>IMEI</td>
				<td>Firmware</td>
				<?php
			}

			$parameters=array('commande'=>$commande);
			$reshook=$hookmanager->executeHooks('printFieldListTitle',$parameters);    // Note that $action and $object may have been modified by hook
			print $hookmanager->resPrint;
			?>
			<td>&nbsp;</td>
		</tr>

		<?php

		$prod = new Product($db);

		$warning_asset = false;

		if(is_array($TImport)){
			foreach ($TImport as $k=>$line) {

				if($prod->id==0 || $line['ref']!= $prod->ref) {
					if(empty($line['fk_product']) === false) {
						$prod->fetch($line['fk_product']);
					} else if (empty($line['ref']) === false) {
						$prod->fetch('', $line['ref']);
					} else {
						continue;
					}
				}

				?><tr class="dispatchAssetLine oddeven" id="dispatchAssetLine<?php print $k; ?>" data-fk-product="<?php print $prod->id; ?>">
				<td><?php echo $prod->getNomUrl(1).$form->hidden('TLine['.$k.'][fk_product]', $prod->id).$form->hidden('TLine['.$k.'][ref]', $prod->ref)." - ".$prod->label; ?></td>
				<td><?php
					$asset=new TAsset;

					if(empty($line['numserie'])) {
						echo $form->texte('','TLine['.$k.'][numserie]', $line['numserie'], 30).' '.img_picto($langs->trans('SerialNumberNeeded'), 'warning.png');
						$warning_asset = true;
					}
					else if($asset->loadReference($PDOdb, $line['numserie'], $line['fk_product'])) {
						if($commande->statut >= 5 || $commande->statut<=2) {
							echo $asset->getNomUrl(1);
						} else {
							echo $form->texte('','TLine['.$k.'][numserie]', $line['numserie'], 30).' '.img_picto($langs->trans('AssetAlreadyLinked'), 'warning.png');
						}
					}
					else {
						echo $form->texte('','TLine['.$k.'][numserie]', $line['numserie'], 30).' '.img_picto($langs->trans('NoAssetCreated'), 'info.png');
						$warning_asset = true;
					}
					echo $form->hidden('TLine['.$k.'][commande_fournisseurdet_asset]', $line['commande_fournisseurdet_asset'], 30)
					?>
				</td>
				<?php if(! empty($conf->global->USE_LOT_IN_OF)) { ?>
					<td><?php echo $form->texte('','TLine['.$k.'][lot_number]', $line['lot_number'], 30);   ?></td>
				<?php } ?>
				<td rel="entrepotChild" fk_product="<?php echo $prod->id ?>"><?php

					$formproduct=new FormProduct($db);
					$formproduct->loadWarehouses();

					if (count($formproduct->cache_warehouses)>1)
					{
						print $formproduct->selectWarehouses($line['fk_warehouse'], 'TLine['.$k.'][entrepot]','',1,0,$prod->id,'',1);
					}
					elseif  (count($formproduct->cache_warehouses)==1)
					{
						print $formproduct->selectWarehouses($line['fk_warehouse'], 'TLine['.$k.'][entrepot]','',0,0,$prod->id,'',1);
					}
					else
					{
						print $langs->trans("NoWarehouseDefined");
					}

					?></td>
				<?php if(!empty($conf->global->ASSET_SHOW_DLUO)){ ?>
					<td><?php echo $form->calendrier('','TLine['.$k.'][dluo]', date('d/m/Y',strtotime($line['dluo'])));   ?></td>
				<?php }

				if(empty($conf->global->DISPATCH_USE_ONLY_UNIT_ASSET_RECEPTION)) {
					?>
					<td><?php echo $form->texte('','TLine['.$k.'][quantity]', $line['quantity'], 10);   ?></td><?php

					if(!empty($conf->global->DISPATCH_SHOW_UNIT_RECEPTION)) {
						echo '<td>'. ($commande->statut < 5) ? $formproduct->select_measuring_units('TLine['.$k.'][quantity_unit]','weight',$line['quantity_unit']) : measuring_units_string($line['quantity_unit'],'weight').'</td>';
					}
				}
				else{
					echo $form->hidden('TLine['.$k.'][quantity]', $line['quantity']);
					echo $form->hidden('TLine['.$k.'][quantity_unit]',$line['quantity_unit']);
				}

				if($conf->global->clinomadic->enabled){
					?>
					<td><?php echo $form->texte('','TLine['.$k.'][imei]', $line['imei'], 30)   ?></td>
					<td><?php echo $form->texte('','TLine['.$k.'][firmware]', $line['firmware'], 30)   ?></td>
					<?php
				}
				$parameters=array('line' => $line, 'prod' => $prod, 'k' => $k);
				$reshook=$hookmanager->executeHooks('printFieldListValue',$parameters);    // Note that $action and $object may have been modified by hook
				print $hookmanager->resPrint;
				?>
				<td>test
					<?php
					if($commande->statut < 5 && $line['commande_fournisseurdet_asset'] > 0){
						echo '<a href="?action=DELETE_LINE&k='.$k.'&id='.$commande->id.'&rowid='.$line['commande_fournisseurdet_asset'].'">'.img_delete().'</a>';
					}
					?>
				</td>
				</tr>
				<?php

			}
		}

		if($commande->statut < 5 && $commande->statut>2){

			$TProducts = array($langs->transnoentities('DispatchSelectProduct'));
			foreach($commande->lines as $line){
				if($line->fk_product) $TProducts[$line->fk_product] = $line->product_ref." - ".$line->product_label;
			}

			$defaultDLUO = '';
			if($conf->global->DISPATCH_DLUO_BY_DEFAULT){
				$defaultDLUO = date('d/m/Y',strtotime(date('Y-m-d')." ".$conf->global->DISPATCH_DLUO_BY_DEFAULT));
			}

			echo $defaultDLUO;

			?><tr style="background-color: lightblue;">
			<td><?php print $form->combo('', 'new_line_fk_product', $TProducts, ''); ?></td>
			<td><?php echo $form->texte('','TLine[-1][numserie]', '', 30); ?></td>
			<?php if(! empty($conf->global->USE_LOT_IN_OF)) { ?>
				<td><?php echo $form->texte('','TLine[-1][lot_number]', '', 30);   ?></td>
			<?php } ?>
			<td><?php

				$formproduct=new FormProduct($db);
				$formproduct->loadWarehouses();

				if (count($formproduct->cache_warehouses)>1)
				{
					print $formproduct->selectWarehouses('', 'TLine[-1][entrepot]','',1,0,$prod->id,'',1);
				}
				elseif  (count($formproduct->cache_warehouses)==1)
				{
					print $formproduct->selectWarehouses('', 'TLine[-1][entrepot]','',0,0,$prod->id,'',1);
				}
				else
				{
					print $langs->trans("NoWarehouseDefined");
				}

				?></td>
			<?php if(!empty($conf->global->ASSET_SHOW_DLUO)){ ?>
				<td><?php echo $form->calendrier('','TLine[-1][dluo]',$defaultDLUO);  ?></td>
			<?php }

			if(empty($conf->global->DISPATCH_USE_ONLY_UNIT_ASSET_RECEPTION)) {
				?>
				<td><?php echo $form->texte('','TLine[-1][quantity]', '', 10);   ?></td><?php

				if(!empty($conf->global->DISPATCH_SHOW_UNIT_RECEPTION)) {
					echo '<td>'.$formproduct->select_measuring_units('TLine[-1][quantity_unit]','weight').'</td>';
				}

			}

			if($conf->global->clinomadic->enabled){
				?>
				<td><?php echo $form->texte('','TLine[-1][imei]', '', 30);   ?></td>
				<td><?php echo $form->texte('','TLine[-1][firmware]', '', 30);   ?></td>
				<?php
			}

			$parameters=array('line' => $line, 'prod' => $prod, 'k' => -1);
			$reshook=$hookmanager->executeHooks('printFieldListValue',$parameters);    // Note that $action and $object may have been modified by hook
			print $hookmanager->resPrint;

			?>
			<td>Nouveau
			</td>
			</tr>
			<?php
		}
		?>


	</table>
	<?php
	if($commande->statut < 5 || $warning_asset){

		if($commande->statut < 5 ) {
			echo '<div class="tabsAction">'.$form->btsubmit($langs->transnoentities('Save'), 'bt_save', '', 'butAction').'</div>';
		}


		$form->type_aff = 'edit';
		?>
		<hr />
		<?php
		echo '<div id="actionVentilation">';
		echo $langs->trans("DispatchDateReception").' : '.$form->calendrier('', 'date_recep', time());

		echo ' - '.$langs->trans("Comment").' : '.$form->texte('', 'comment', !empty($comment)?$comment:$langs->trans("DispatchSupplierOrder",$commande->ref), 60,128);

		echo ' '.$form->btsubmit($langs->trans('AssetVentil'), 'bt_create', '', 'butAction');
		echo '</div>';
	}
}
