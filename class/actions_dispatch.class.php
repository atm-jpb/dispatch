<?php
class ActionsDispatch
{ 
     /** Overloading the doActions function : replacing the parent's function with the one below 
      *  @param      parameters  meta datas of the hook (context, etc...) 
      *  @param      object             the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...) 
      *  @param      action             current action (if set). Generally create or edit or null 
      *  @return       void 
      */
	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		if(in_array('ordersuppliercard', explode(':',$parameters['context'])))
		{
			$id = GETPOST('id');
			$targetUrl = dol_buildpath('/dispatch/reception.php', 2).'?id='.$id
			?>
			<script>
				$(document).ready(function() {
					$('a[href*="fourn/commande/dispatch.php"]').attr('href', '<?php print dol_escape_js($targetUrl, 1); ?>');
				});
			</script>
			<?php

			return 0;
		}
	}

	function beforePDFCreation($parameters, &$object, &$action, $hookmanager) {

		// pour implementation dans Dolibarr 3.7
		if (in_array('pdfgeneration',explode(':',$parameters['context']))) {
			
			define('INC_FROM_DOLIBARR',true);
			dol_include_once('/dispatch/config.php');
			dol_include_once('/asset/class/asset.class.php');
			dol_include_once('/dispatch/class/dispatchdetail.class.php');
			dol_include_once('/dispatch/class/dispatchasset.class.php');
			dol_include_once('/core/lib/product.lib.php');
			
			global $conf;
			
			if(! empty($parameters['object']) && get_class($object) == 'Expedition') {
				
				$PDOdb = new TPDOdb;
				
				foreach($object->lines as &$line){
					
					$details = new TDispatchDetail;
					$TRecepDetail = $details->LoadAllBy($PDOdb, array('fk_expeditiondet' => $line->id));

					if(count($TRecepDetail) > 0) {
						$line->desc .= "<br>Produit(s) expédié(s) : ";

						foreach($TRecepDetail as $detail) {
							$asset = new TAsset;
							$asset->load($PDOdb, $detail->fk_asset);
							$asset->load_asset_type($PDOdb);

							$this->_addAssetToLineDesc($line, $detail, $asset); continue;
						}
					}
				}
			}

			if(! empty($parameters['object']) && get_class($object) == 'CommandeFournisseur') {

				$PDOdb = new TPDOdb;

				foreach($object->lines as &$line){
					$details = new TRecepDetail;
					$TRecepDetail = $details->LoadAllBy($PDOdb, array('fk_commandedet' => $line->id));

					if(count($TRecepDetail) > 0) {
						$line->desc .= "<br>Produit(s) reçu(s) : ";

						foreach($TRecepDetail as $detail) {
							$asset = new TAsset;
							$asset->loadBy($PDOdb, $detail->serial_number, 'serial_number');
							$asset->load_asset_type($PDOdb);

							$this->_addAssetToLineDesc($line, $detail, $asset); continue;
						}
					}
				}
			}
		}
	}

	function _addAssetToLineDesc(&$line, $detail, $asset)
	{
		global $conf;

		$unite = (($asset->assetType->measuring_units == 'unit') ? 'unité(s)' : measuring_units_string($detail->weight_reel_unit, $asset->assetType->measuring_units));

		if(empty($res->lot_number)) {
			$desc = "<br>- N° série : ".$asset->serial_number;
		} else {
			$desc = "<br>- ".$asset->lot_number." x ".$detail->weight_reel." ".$unite;
		}

		if(! empty($conf->global->ASSET_SHOW_DLUO) && empty($conf->global->DISPATCH_HIDE_DLUO_PDF)) $desc.= ' (DLUO : '.$asset->get_date('dluo').')';

		$line->desc.= $desc;
	}
}
