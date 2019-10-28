<?php
	
	require 'config.php';
	dol_include_once('/dispatch/class/dispatchasset.class.php');
	dol_include_once('/contrat/class/contrat.class.php');
	dol_include_once('/product/class/product.class.php');
	dol_include_once('/product/class/html.formproduct.class.php' );
	dol_include_once('/fichinter/class/fichinter.class.php' );
	dol_include_once('/' . ATM_ASSET_NAME . '/class/asset.class.php');
	dol_include_once('/core/lib/product.lib.php');
	
	$PDOdb=new TPDOdb;
	
	$type_object=GETPOST('type_object');
	$id = (int)GETPOST('id');
	
	$dispatch=new TDispatch;
	$dispatch->loadByObject($PDOdb,$id,$type_object);
	
	$action = GETPOST('action');
	
	switch ($action) {
		case 'save':
			
			$TLine = GETPOST('TLine');
			if(!empty($TLine[-1]['serial_number']) && (!empty($TLine[-1]['fk_object']) || GETPOST('type_object') === 'ticketsup')) 
			// Si type_object == ticketsup on n'empêche pas l'ajout si aucune ligne est sélectionnée car aucun sens d'associer un asset à un message sur un ticket
			{
				
				$asset = new TAsset;
				$asset->loadReference($PDOdb, $TLine[-1]['serial_number']);
				
				if($asset->getId()>0) {
					$k=$dispatch->addChild($PDOdb, 'TDispatchAsset');
					$dispatch->TDispatchAsset[$k]->fk_asset = $asset->getId();
					$dispatch->TDispatchAsset[$k]->fk_object = $TLine[-1]['fk_object'];
					$dispatch->TDispatchAsset[$k]->type_object = $type_object;
					$dispatch->TDispatchAsset[$k]->asset = $asset;
					
					$dispatch->save($PDOdb);
				}
				
			}
				
			break;
		case 'delete-line':
			$k = (int)GETPOST('k');
			$dispatch->TDispatchAsset[$k]->to_delete=true;
			
			$dispatch->save($PDOdb);
			break;
	}
	
	_fiche($PDOdb,$dispatch);
	
function _fiche(&$PDOdb,&$dispatch) {
	global $db,$conf,$langs;

	llxHeader();

	$form=new TFormCore('auto','asset','post');
	echo $form->hidden('action', 'save');
	echo $form->hidden('id', $dispatch->fk_object);
	echo $form->hidden('type_object', $dispatch->type_object);

	$object = _header($dispatch->fk_object,$dispatch->type_object);
	$pListe[0] = "Sélectionnez une ligne";
	foreach($object->lines as $k=>&$line){
		$label = !empty($line->label) ? $line->label : $line->libelle;
		if(empty($label) && !empty($line->desc))$label = $line->desc;
		
		$pListe[$line->id] = ($k+1).'/ '.$label;
	}
	
	print $langs->trans( 'COUNT_REL_EQUIPMENT',  count($dispatch->TDispatchAsset) ).'<br />';

	?>
	<table width="100%" class="border">
		<tr class="liste_titre">
			<?php
				if(GETPOST('type_object') !== 'ticketsup') print '<td>' . $langs->trans('AFFECTED_ROW' ) . '</td>';
			?>
			<td><?php print $langs->trans('Equipment'); // Equipement ?></td>
			<?php
				if(!empty($conf->global->USE_LOT_IN_OF)) {
				?>
				<td><?php print $langs->trans('DispatchBatchNumber'); ?></td>
				<td><?php print $langs->trans('EatByDate'); // DLUO ?></td>
				<?php
				}
			?>
			<?php
			if($conf->global->clinomadic->enabled){
				?>
				<td>IMEI</td>
				<td>Firmware</td>
				<?php
			}
			?>
			<td>&nbsp;</td>
		</tr>
		
	<?php

	$canLink = ! empty($conf->global->DISPATCH_CAN_LINK_ASSET_TO_OBJECT_IN_ANY_STATUS) || $dispatch->type_object == 'contrat' || $object->statut == 0;
	
	foreach($dispatch->TDispatchAsset as $k=>&$da) {
		
		if($da->to_delete) continue;
		
		?><tr class="oddeven">
			<?php if(GETPOST('type_object') !== 'ticketsup') echo '<td>'.$pListe[$da->fk_object].'</td>'; ?>
			<td><?php echo $da->asset->getNomUrl(1,0,1); ?></td>
			<?php
				if(!empty($conf->global->USE_LOT_IN_OF)) {
				?>
				<td><?php echo $da->asset->lot_number; ?></td>
				<td><?php echo $da->asset->dluo ? dol_print_date($da->asset->dluo) : 'N/A'; ?></td>
				<?php
				}
			?>
			
			
			
			<?php
			if($conf->global->clinomadic->enabled){
				?>
				<td>IMEI</td>
				<td>Firmware</td>
				<?php
			}
			?>
			<td><?php
			
				if($canLink) echo '<a href="?action=delete-line&k='.$k.'&id='.$object->id.'&type_object='.$dispatch->type_object.'">'.img_delete().'</a>';
						
			?></td>
		</tr>
		
		<?php
		
	}
	
	
	
	$formproduct=new FormProduct($db);
	if($canLink) {
		$newAssetSerialNumber = ! empty($_REQUEST['new-asset']) ? GETPOST('new-asset') : '';
		
	?><tr style="background-color: lightblue;">
			<?php if(GETPOST('type_object') !== 'ticketsup') echo '<td>'.$form->combo('', 'TLine[-1][fk_object]', $pListe, '').'</td>'; ?>
			<td><?php echo $form->texte('','TLine[-1][serial_number]', $newAssetSerialNumber, 30); ?></td>
			<?php
				if(!empty($conf->global->USE_LOT_IN_OF)) {
					?><td>&nbsp;</td><?php
				}
			?>
			<td>&nbsp;</td>
			<?php
			if($conf->global->clinomadic->enabled){
				?>
				<td>&nbsp;</td>
				<td>&nbsp;</td>
				<?php
			}
			?>
			<td>Nouveau
			</td>
	</tr><?php
	
	}
	
	?>
	</table>
	<script type="text/javascript">
		$(document).ready(function() {
			    $( "input[name='TLine[-1][serial_number]']" ).autocomplete({
			      source: "<?php echo dol_buildpath('/dispatch/script/interface.php',1) ?>?get=serial_number",
			      minLength: 1,
			      select: function( event, ui ) {
			        
			      }
			    });
		});
		
			
		</script>
	<?php
	
	echo $form->btsubmit($langs->trans('Save'), 'bt_new');
	
	dol_fiche_end();
	
	$form->end();
	
	llxFooter();
	
	
}
function _header($id,$type_object) {
	global $db,$langs;
	
	$langs->load('interventions');
	$langs->load('contracts');
	
	if($type_object == 'contrat') {
		$object=new Contrat($db);
		$object->fetch($id);
		dol_include_once('/core/lib/contract.lib.php');
		$head = contract_prepare_head($object);
        dol_fiche_head($head, 'dispatchAsset', $langs->trans("Contract"), 0, 'contract');
		
		
		
	}
	else if($type_object=='intervention') {
		$object=new Fichinter($db);
		$object->fetch($id);
		dol_include_once('/core/lib/fichinter.lib.php');
		$head = fichinter_prepare_head($object);
		dol_fiche_head($head, 'dispatchAsset', $langs->trans("InterventionCard"), 0, 'intervention');
	}
	else if($type_object=='ticketsup') {
		dol_include_once('/ticketsup/class/ticketsup.class.php');
		dol_include_once('/ticketsup/lib/ticketsup.lib.php');
		$object = new Ticketsup($db);
		$object->fetch($id);
		$head = ticketsup_prepare_head($object);
		dol_fiche_head($head, 'dispatchAsset', $langs->trans("Ticket"), 0, 'ticketsup@ticketsup');
	}
	else if($type_object=='commande') {
		dol_include_once('/commande/class/commande.class.php');
		dol_include_once('/core/lib/order.lib.php');
		$object = new Commande($db);
		$object->fetch($id);
		$head = commande_prepare_head($object);
		dol_fiche_head($head, 'dispatchAsset', $langs->trans("CustomerOrder"), 0, 'order');
	}

	return $object;
	
}
	
