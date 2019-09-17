<?php
class ActionsDispatch
{
	/**
	 * Overloading the doActions function : replacing the parent's function with the one below
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	function doActions($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;

		$TContexts = explode(':', $parameters['context']);

		if(in_array('bonderetourcard', $TContexts))
		{
//			var_dump($conf->global->STOCK_CALCULATE_ON_BONDERETOUR_VALIDATE, $conf->global->STOCK_CALCULATE_ON_BONDERETOUR_CLOSE);
			//if (! empty($conf->global->STOCK_CALCULATE_ON_BONDERETOUR_VALIDATE)) $conf->global->STOCK_CALCULATE_ON_BONDERETOUR_VALIDATE = 0;
			//if (! empty($conf->global->STOCK_CALCULATE_ON_BONDERETOUR_CLOSE)) $conf->global->STOCK_CALCULATE_ON_BONDERETOUR_CLOSE = 0;
//			var_dump($conf->global->STOCK_CALCULATE_ON_BONDERETOUR_VALIDATE, $conf->global->STOCK_CALCULATE_ON_BONDERETOUR_CLOSE);
//			exit('la');
		}

		return 0;
	}

     /** Overloading the addMoreActionsButtons function : replacing the parent's function with the one below
      *  @param      parameters  meta datas of the hook (context, etc...)
      *  @param      object             the object you want to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
      *  @param      action             current action (if set). Generally create or edit or null
      *  @return       void
      */
	function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;

		$TContexts = explode(':', $parameters['context']);

		// Add more cols on card lines table
		$this->addMoreColsOnCardTable($parameters, $object, $action, $hookmanager);


		if(in_array('ordersuppliercard', $TContexts))
		{
			$id = GETPOST('id');
			$targetUrl = dol_buildpath('/dispatch/reception.php', 2).'?id='.$id;
			?>
			<script>
				$(document).ready(function() {
					$('a[href*="fourn/commande/dispatch.php"]').attr('href', '<?php print dol_escape_js($targetUrl, 1); ?>');
				});
			</script>
			<?php
		}

		if(in_array('expeditioncard', $TContexts) && $object->statut == Expedition::STATUS_VALIDATED && ! empty($conf->global->DISPATCH_BLOCK_SHIPPING_CLOSING_IF_PRODUCTS_NOT_PREPARED))
		{
			if(! defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', true);
			dol_include_once('/dispatch/config.php');
			dol_include_once('/dispatch/lib/dispatch.lib.php');

			list($canBeClosed, $msg) = dispatch_shipment_can_be_closed($object);

			if(empty($canBeClosed))
			{
				global $langs;

				$langs->load('dispatch@dispatch');

				$message = dol_escape_js($langs->transnoentities('ShipmentCannotBeClosed', $msg), 1);
?>
				<script>
					$(document).ready(function()
					{
						$('a.butAction[href*=action\\=classifyclosed]').removeClass('butAction').addClass('butActionRefused').prop('href', '#').prop('title', '<?php echo $message; ?>');
					});
				</script>
<?php
			}
		}

		return 0;
	}

	function beforePDFCreation($parameters, &$object, &$action, $hookmanager) {

		// pour implementation dans Dolibarr 3.7
		if (in_array('pdfgeneration',explode(':',$parameters['context']))) {

			define('INC_FROM_DOLIBARR',true);
			dol_include_once('/dispatch/config.php');
			dol_include_once('/' . ATM_ASSET_NAME . '/class/asset.class.php');
			dol_include_once('/dispatch/class/dispatchdetail.class.php');
			dol_include_once('/dispatch/class/dispatchasset.class.php');
			dol_include_once('/core/lib/product.lib.php');

			global $conf;
			if(! empty($parameters['object']) && (get_class($object) == 'Expedition' || get_class($object) == 'Livraison')) {

				$PDOdb = new TPDOdb;

				$expedition = $object;
				if(get_class($object) == 'Livraison') {
					$expedition = new Expedition($object->db);
					$expedition->fetch($object->origin_id);
				}

				foreach($object->lines as &$line){

					$details = new TDispatchDetail;

					$fkExpeditionLine = $line->id;

					if(get_class($object) == 'Livraison') {
						$fkExpeditionLine = 0;

						foreach($expedition->lines as $lineExpe) {
							if($lineExpe->fk_origin_line == $line->fk_origin_line) { // La ligne d'origine de la livraison est la ligne de commande et non la ligne d'expédition
								$fkExpeditionLine = $lineExpe->id;
								break;
							}
						}
					}

					if(! empty($fkExpeditionLine)) {
						$TRecepDetail = $details->LoadAllBy($PDOdb, array('fk_expeditiondet' => $fkExpeditionLine));

						if(count($TRecepDetail) > 0) {
							if(!empty($line->description) && $line->description != $line->desc) $line->desc.=$line->description.'<br />'; // Sinon Dans certains cas desc écrase description
							$line->desc .= "<br>Produit(s) expédié(s) : ";

							foreach($TRecepDetail as $detail) {
								$asset = new TAsset;
								$asset->load($PDOdb, $detail->fk_asset);
								$asset->load_asset_type($PDOdb);

								$this->_addAssetToLineDesc($line, $detail, $asset);
							}
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

							$this->_addAssetToLineDesc($line, $detail, $asset);
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

		if(empty($asset->lot_number)) {
			$desc = "<br>- N° série : ".$asset->serial_number;
		} else {
			$desc = "<br>- ".$asset->lot_number." x ".$detail->weight_reel." ".$unite;
		}

		if(! empty($conf->global->ASSET_SHOW_DLUO) && empty($conf->global->DISPATCH_HIDE_DLUO_PDF) && ! empty($asset->date_dluo)) $desc.= ' (DLUO : '.$asset->get_date('dluo').')';

		$line->desc.= $desc;
	}



	/*
	 * Overloading the addMoreActionsButtons function
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          $action         Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	public function addMoreColsOnCardTable(&$parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $langs, $db;

		if(intval(DOL_VERSION) < 11 && empty($conf->global->DISPATCH_SHOW_BATCH_COL_IN_CARD)){
			return 0;
		}

		if(empty($conf->assetatm->enabled)){
			return 0;
		}


		$contextArray = explode(':',$parameters['context']);

		if(in_array('expeditioncard',$contextArray))
		{

			define('INC_FROM_DOLIBARR', true);
			dol_include_once('/dispatch/config.php');
			dol_include_once('/' . ATM_ASSET_NAME . '/class/asset.class.php');
			dol_include_once('/dispatch/class/dispatchdetail.class.php');
			dol_include_once('/dispatch/class/dispatchasset.class.php');

			$expedition = $object;

			$jsonObjectData =array();
			foreach($object->lines as $i => $line)
			{
				$jsonObjectData[$line->id] = new stdClass();
				$jsonObjectData[$line->id]->id = $line->id;

				$product = new Product($db);
				if(!empty($line->fk_product)){
					$product->fetch($line->fk_product);
				}

				$jsonObjectData[$line->id]->batch = '';

				// GET DISPATCH INFOS
				$PDOdb = new TPDOdb;
				$details = new TDispatchDetail();

				$fkExpeditionLine = $line->id;

				if(get_class($object) == 'Livraison') {
					$fkExpeditionLine = 0;

					foreach($expedition->lines as $lineExpe) {
						if($lineExpe->fk_origin_line == $line->fk_origin_line) { // La ligne d'origine de la livraison est la ligne de commande et non la ligne d'expédition
							$fkExpeditionLine = $lineExpe->id;
							break;
						}
					}
				}

				if(! empty($fkExpeditionLine)) {
					$TRecepDetail = $details->LoadAllBy($PDOdb, array('fk_expeditiondet' => $fkExpeditionLine));

					if(count($TRecepDetail) > 0) {
						if(!empty($line->description) && $line->description != $line->desc) $line->desc.=$line->description.'<br />'; // Sinon Dans certains cas desc écrase description
						$batch = '';

						foreach($TRecepDetail as $detail) {
							$asset = new TAsset;
							$asset->load($PDOdb, $detail->fk_asset);
							$asset->load_asset_type($PDOdb);

							$unite = (($asset->assetType->measuring_units == 'unit') ? 'unité(s)' : measuring_units_string($detail->weight_reel_unit, $asset->assetType->measuring_units));

							if(empty($asset->lot_number)) {
								$batch = $asset->serial_number;
							} else {
								$batch = $asset->lot_number." x ".$detail->weight_reel." ".$unite;
							}

							if(! empty($conf->global->ASSET_SHOW_DLUO) && empty($conf->global->DISPATCH_HIDE_DLUO_PDF) && ! empty($asset->date_dluo)) $batch.= ' (DLUO : '.$asset->get_date('dluo').')';
							$jsonObjectData[$line->id]->batch = $batch;
						}
					}
				}

			}

			$totalAddedCols = 1;
			?>
			<script>

                $( document ).ready(function(){
                    var jsonObjectData = <?php print json_encode($jsonObjectData) ; ?> ;

                    // ADD NEW COLS
                    $("#tablelines tr").each(function( index ){
						//console.log($( this ).data( "product_type" ));
                        if($( this ).hasClass( "liste_titre" )){
                            // PARTIE TITRE
                            $('<td align="center" class="linecoldispatch"><?php print $langs->transnoentities('colDispatch'); ?></td>').insertAfter($( this ).find("td:first"));
                        }
                        else if($( this ).data( "product_type" ) == "9"){
                            $( this ).find("td[colspan]:first").attr('colspan',    parseInt($( this ).find("td[colspan]:first").attr('colspan')) + <?php print $totalAddedCols; ?>  );
                        }
                        else
                        {
                            // PARTIE LIGNE
                            var nobottom = '';
                            if($( this ).hasClass( "liste_titre_create" ) || $( this ).attr("data-element") == "extrafield" ){
                                nobottom = ' nobottom ';
                            }

                            $('<td align="center" class="linecoldispatch' + nobottom + '"></td>').insertAfter($( this ).find("td.linecoldescription"));

                            if($( this ).hasClass( "liste_titre_create" )){
                                $( this ).find("td.linecoledit").attr('colspan',    parseInt($( this ).find("td.linecoledit").attr('colspan')) + <?php print $totalAddedCols; ?>  );
                            }

                            if($( this ).attr("data-element") == "extrafield"){
                                $( this ).find("td[colspan]:first").attr('colspan',    parseInt($( this ).find("td[colspan]:first").attr('colspan')) + <?php print $totalAddedCols; ?>  );
                            }
                        }
                    });

                    // Affichage des données
                    $.each(jsonObjectData, function(i, item) {
                        // Dimensions
                        $("#row-" + jsonObjectData[i].id + " .linecoldispatch:first").html(jsonObjectData[i].batch);

                    });

                });
			</script>
			<?php
		}

		return 0;
	}
}
