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
			
			global $conf;
			
			if(isset($parameters['object']) && get_class($object) == 'Expedition'){
				
				$PDOdb = new TPDOdb;
				
				foreach($object->lines as &$line){
					$sql = 'SELECT DISTINCT(ea.lot_number),ea.rowid,a.serial_number FROM '.MAIN_DB_PREFIX.'expeditiondet_asset as ea LEFT JOIN '.MAIN_DB_PREFIX.'asset as a ON (a.rowid = ea.fk_asset) WHERE ea.fk_expeditiondet = '.$line->line_id;
					$PDOdb->Execute($sql);
				
					$TRes = $PDOdb->Get_All();
					
					if(count($TRes)>0){
						$line->desc .= "<br>Produit(s) expédié(s) : ";
						foreach($TRes as $res){
							$dispatchDetail = new TDispatchDetail;
							$dispatchDetail->load($PDOdb, $res->rowid);
							
							$asset = new TAsset;
							$asset->load($PDOdb, $dispatchDetail->fk_asset);
							$asset->load_asset_type($PDOdb);
							
							$unite = (($asset->assetType->measuring_units == 'unit') ? 'unité(s)' : measuring_units_string($dispatchDetail->weight_reel_unit, $asset->assetType->measuring_units));
							if(empty($res->lot_number)){
								$desc = "<br>- N° Serie : ".$res->serial_number;
							}else{
								$desc = "<br>- ".$res->lot_number." x ".$dispatchDetail->weight_reel." ".$unite;
							}
							if(! empty($conf->global->ASSET_SHOW_DLUO) && empty($conf->global->DISPATCH_HIDE_DLUO_PDF)) $desc.= ' (DLUO : '.$asset->get_date('dluo').')';
							
							$line->desc.=$desc;
							
						}	
					}
				}
			}
			
			//pre($object,true);exit;
		}
		
    }
	
}
