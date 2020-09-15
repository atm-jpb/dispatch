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
global $langs;

header('Content-Type: application/json');

// $idexpe = GETPOST('idexpe');
$idexpe = dol_htmlentitiesbr(GETPOST('idexpe'));
$refexpe = dol_htmlentitiesbr(GETPOST('refexpe'));
$action = dol_htmlentitiesbr(GETPOST('action'));

//var_dump($refexpe,$idexpe);

// on a une expedition

// on récupère les expeditiondet
//$sql = "SELECT * FROM ".MAIN_DB_PREFIX."expedition AS e,".MAIN_DB_PREFIX."expeditiondet AS ed WHERE e.rowid = ".$idexpe." AND e.rowid = ed.fk_expedition";
//var_dump($sql);

$currentExp = new Expedition($db);
$currentExp->fetch($idexpe);
//var_dump($currentExp);

foreach ($currentExp->lines as $currentLineExp) {
	//var_dump($currentLineExp);

	// $currentLineExp donne les infos suivantes :   product id // qty // qty shiped  // label

	// on remonte les equipements si le produit en possède ...
	$sql = "SELECT * FROM ".MAIN_DB_PREFIX."expeditiondet_asset AS ea, ".MAIN_DB_PREFIX."assetatm AS AT WHERE fk_expeditiondet = ".$currentLineExp->id." AND ea.fk_asset = AT.rowid" ;
	//var_dump($sql);

	$resultsetEquipments = $db->query($sql);
	$num = $db->num_rows($resultsetEquipments);
	//var_dump('here :' . $num);
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

//print json_encode($currentExp);
print json_encode($currentExp->lines);

// on genere l'UI autoCompletée avec le bouton enregistrer

// on flag l'expedition à traitée

// le client reload la page  et affiche les expé non traité.



// LoadLinesExpedition
if (isset($action) && $action == ''){


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
