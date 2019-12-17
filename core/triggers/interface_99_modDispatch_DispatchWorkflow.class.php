<?php
/* Copyright (C) 2005-2011 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2011 Regis Houssin        <regis.houssin@capnetworks.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *  \file       htdocs/core/triggers/interface_90_all_Demo.class.php
 *  \ingroup    core
 *  \brief      Fichier de demo de personalisation des actions du workflow
 *  \remarks    Son propre fichier d'actions peut etre cree par recopie de celui-ci:
 *              - Le nom du fichier doit etre: interface_99_modMymodule_Mytrigger.class.php
 *				                           ou: interface_99_all_Mytrigger.class.php
 *              - Le fichier doit rester stocke dans core/triggers
 *              - Le nom de la classe doit etre InterfaceMytrigger
 *              - Le nom de la methode constructeur doit etre InterfaceMytrigger
 *              - Le nom de la propriete name doit etre Mytrigger
 */


/**
 *  Class of triggers for Mantis module
 */

class InterfaceDispatchWorkflow
{
    var $db;

    /**
     *   Constructor
     *
     *   @param		DoliDB		$db      Database handler
     */
    function __construct($db)
    {
        $this->db = $db;

	    global $langs;

        $this->name = preg_replace('/^Interface/i','',get_class($this));
        $this->family = "ATM";
        $this->description = $langs->trans( 'DispatchInterfaceDesc' ); // Trigger of the specific Latoxan shipping module //
        $this->version = 'dolibarr';            // 'development', 'experimental', 'dolibarr' or version
        $this->picto = 'technic';
    }


    /**
     *   Return name of trigger file
     *
     *   @return     string      Name of trigger file
     */
    function getName()
    {
        return $this->name;
    }

    /**
     *   Return description of trigger file
     *
     *   @return     string      Description of trigger file
     */
    function getDesc()
    {
        return $this->description;
    }

    /**
     *   Return version of trigger file
     *
     *   @return     string      Version of trigger file
     */
    function getVersion()
    {
        global $langs;
        $langs->load("admin");

        if ($this->version == 'development') return $langs->trans("Development");
        elseif ($this->version == 'experimental') return $langs->trans("Experimental");
        elseif ($this->version == 'dolibarr') return DOL_VERSION;
        elseif ($this->version) return $this->version;
        else return $langs->trans("Unknown");
    }


    /**
     *      Function called when a Dolibarrr business event is done.
     *      All functions "run_trigger" are triggered if file is inside directory htdocs/core/triggers
     *
     *      @param	string		$action		Event action code
     *      @param  Object		$object     Object
     *      @param  User		$user       Object user
     *      @param  Translate	$langs      Object langs
     *      @param  conf		$conf       Object conf
     *      @return int         			<0 if KO, 0 if no triggered ran, >0 if OK
     */
	function run_trigger($action,$object,$user,$langs,$conf)
	{
		global $conf,$db, $langs;

		if(!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR',true);

		if ($action == 'SHIPPING_VALIDATE') {
		    if (!empty($conf->global->STOCK_CALCULATE_ON_SHIPMENT)) {
		        $this->move_assets_according_to_shipment($object);
            }
            dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
            return 1;
		}

		if($action == 'SHIPPING_DELETE')
		{
			dol_include_once('/dispatch/config.php');
			dol_include_once('/dispatch/class/dispatchdetail.class.php');

			$PDOdb = new TPDOdb;

			foreach($object->lines as &$line) {
				$dispatchDetail = new TDispatchDetail;
				$TDetail = $dispatchDetail->LoadAllBy($PDOdb, array('fk_expeditiondet' => $line->id));

				foreach($TDetail as &$detail) {
					if(!empty($conf->global->DISPATCH_RESET_ASSET_WAREHOUSE_ON_SHIPMENT)
						&& (($conf->global->STOCK_CALCULATE_ON_SHIPMENT && $object->statut > 0)
							|| ($conf->global->STOCK_CALCULATE_ON_SHIPMENT_CLOSE && $object->statut == 2))) {
						$asset = new TAsset;
						$asset->load($PDOdb, $detail->fk_asset);

						$asset->fk_entrepot = $line->entrepot_id;
						$asset->fk_societe_localisation = 0;

						// on ne fait pas le mouvement standard qui a été traité par dolibarr à la suppression d'expédition
						$asset->save($PDOdb, $user, $langs->trans("ShipmentDeletedInDolibarr", $object->ref), $detail->weight_reel, false, 0, true);

						$stock = new TAssetStock;
						$stock->mouvement_stock($PDOdb, $user, $asset->getId(), $detail->weight_reel, $langs->trans("ShipmentDeletedInDolibarr", $object->ref), $detail->fk_expeditiondet);
					}

					$detail->delete($PDOdb);
				}
			}


		}


		if($action == 'SHIPPING_CLOSED')
		{
		    if (! empty($conf->global->DISPATCH_BLOCK_SHIPPING_CLOSING_IF_PRODUCTS_NOT_PREPARED))
            {
                if(! defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', true);
                dol_include_once('/dispatch/config.php');
                dol_include_once('/dispatch/lib/dispatch.lib.php');

                list($canBeClosed, $msg) = dispatch_shipment_can_be_closed($object);

                if(empty($canBeClosed))
                {
                    setEventMessage($langs->trans('ShipmentCannotBeClosed', $msg), 'errors');
                    return -1;
                }
			}

			if (!empty($conf->global->STOCK_CALCULATE_ON_SHIPMENT_CLOSE)) {
				$this->move_assets_according_to_shipment($object);
			}
		}

		if ($action == 'BONDERETOUR_VALIDATE')
		{
			if (!empty($conf->global->STOCK_CALCULATE_ON_BONDERETOUR_VALIDATE))
			{
				$this->move_assets_according_to_BDR($object, $action);
			}
		}

		if ($action == 'BONDERETOUR_UNVALIDATE')
		{
			if (!empty($conf->global->STOCK_CALCULATE_ON_BONDERETOUR_VALIDATE))
			{
				$this->move_assets_according_to_BDR($object, $action, true);
			}
		}

		if ($action == 'BONDERETOUR_CLOSED')
		{
			if (!empty($conf->global->STOCK_CALCULATE_ON_BONDERETOUR_CLOSE))
			{
				$this->move_assets_according_to_BDR($object, $action);
			}
		}

		if ($action == 'BONDERETOUR_REOPEN')
		{
			if (!empty($conf->global->STOCK_CALCULATE_ON_BONDERETOUR_CLOSE))
			{
				$this->move_assets_according_to_BDR($object, $action, true);
			}
		}

		if ($action == 'BONDERETOUR_DELETE')
		{
			if (($object->statut == 1 && !empty($conf->global->STOCK_CALCULATE_ON_BONDERETOUR_VALIDATE))
				|| ($object->statut == 2 && !empty($conf->global->STOCK_CALCULATE_ON_BONDERETOUR_CLOSE))
			)
			{
				$this->move_assets_according_to_BDR($object, $action, true);
			}

			dol_include_once('/dispatch/config.php');
			dol_include_once('/dispatch/class/dispatchdetail.class.php');

			$PDOdb = new TPDOdb();

			if (!empty($object->lines))
			{
				foreach ($object->lines as $line)
				{
					$d = new TRecepBDRDetail();
					$d->loadLines($PDOdb, $line->id);

					if (!empty($d->lines))
					{
						foreach ($d->lines as $detail)
						{
							$detail->delete($PDOdb);
						}
					}
				}
			}

		}

		if ($action == "LINEBONDERETOUR_UPDATE")
		{
			if (!empty($object->oldcopy))
			{
				if ($object->fk_entrepot != $object->oldcopy->fk_entrepot)
				{
					dol_include_once('/dispatch/config.php');
					dol_include_once('/dispatch/class/dispatchdetail.class.php');

					$PDOdb = new TPDOdb();

					$d = new TRecepBDRDetail();
					$d->loadLines($PDOdb, $object->id);

					if (!empty($d->lines))
					{
						foreach ($d->lines as $detail)
						{
							$detail->fk_warehouse = $object->fk_entrepot;
							$detail->save($PDOdb);
						}
					}
				}
			}
		}


		return 0;
	}

	private function create_flacon_stock_mouvement(&$PDOdb, &$linedetail, $numref,$fk_soc = 0, $exp_id = 0) {
		global $user, $langs, $conf, $db;
		dol_include_once('/' . ATM_ASSET_NAME . '/class/asset.class.php');
		dol_include_once('/product/class/product.class.php');
		dol_include_once('/expedition/class/expedition.class.php');

		$asset = new TAsset;
		$asset->load($PDOdb,$linedetail->fk_asset);

		if($conf->global->clilatoxan){
			$poids_destocke = $this->calcule_poids_destocke($PDOdb,$linedetail);
			$poids_destocke = $poids_destocke * pow(10,$asset->contenancereel_units);
		}
		else{
			$poids_destocke = $linedetail->weight_reel;
		}
		/*pre($linedetail,true);
		echo $poids_destocke;exit;*/

		//$asset->contenancereel_value = $asset->contenancereel_value - $poids_destocke;
		$asset->fk_societe_localisation = $fk_soc;
		if (!empty($conf->global->DISPATCH_RESET_ASSET_WAREHOUSE_ON_SHIPMENT)) $asset->fk_entrepot = 0;


		// Destockage Dolibarr déjà fait par à la validation de l'expédition, et impossible de ne destocker que l'équipement : on save sans rien déstocker
		$asset->save($PDOdb, $user, $langs->trans("ShipmentValidatedInDolibarr",$numref), -$poids_destocke, false, 0, true);
		
		$fk_dol_moov = 0;
		if ($conf->global->DISPATCH_LINK_ASSET_TO_STOCK_MOOV)
		{
		    $sql = "SELECT m.rowid FROM ".MAIN_DB_PREFIX."stock_mouvement as m";
		    $sql.= " WHERE m.fk_product = ".$asset->fk_product;
		    $sql.= " AND m.type_mouvement = 2";
		    $sql.= " AND m.value < 0";
		    $sql.= " AND m.origintype = 'shipping'";
		    $sql.= " AND m.fk_origin = ".$exp_id;
		    $sql.= " ORDER BY m.tms DESC LIMIT 1";
		    $res = $db->query($sql); // on utilise doliDB pour rester dans la même transaction et récupérer le bon ID de mvt dolibarr
		    if ($res && $db->num_rows($res))
		    {
		        $obj = $db->fetch_object($res);
		        $fk_dol_moov = $obj->rowid;
		    }
		}

		// Destockage équipement
		$stock = new TAssetStock;
		$stock->mouvement_stock($PDOdb, $user, $asset->getId(), -$poids_destocke, $langs->trans("ShipmentValidatedInDolibarr",$numref), $linedetail->fk_expeditiondet, $fk_dol_moov);

		return $poids_destocke;
	}

	/*private function create_standard_stock_mouvement(&$line, $qty, $numref) {
		global $user, $langs;
		require_once DOL_DOCUMENT_ROOT.'/product/stock/class/mouvementstock.class.php';

		$mouvS = new MouvementStock($this->db);
		// We decrement stock of product (and sub-products)
		// We use warehouse selected for each line
		$result=$mouvS->livraison($user, $line->fk_product, $line->entrepot_id, $qty, $line->subprice, $langs->trans("ShipmentValidatedInDolibarr",$numref));
		return $result;
	}*/

	private function calcule_poids_destocke(&$PDOdb,&$linedetail){

		$sql = "SELECT p.weight, p.weight_units
				FROM ".MAIN_DB_PREFIX."product as p
					LEFT JOIN ".MAIN_DB_PREFIX.ATM_ASSET_NAME." as a ON (a.fk_product = p.rowid)
					LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet_asset as eda ON (eda.fk_asset = a.rowid)
					LEFT JOIN ".MAIN_DB_PREFIX."expeditiondet as ed ON (ed.rowid = eda.fk_expeditiondet)
				WHERE ed.rowid = ".$linedetail->fk_expeditiondet;

		$PDOdb->Execute($sql);
		$PDOdb->Get_line();
		$weight = $PDOdb->Get_field('weight');
		$poids = (!empty($weight)) ? $weight : 1 ;
		$weight_units = $PDOdb->Get_field('weight_units');
		$poids_unite = (!empty($weight_units)) ? $weight_units : $linedetail->weight_reel_unit ;
		$poids = $poids * pow(10,$poids_unite);
		$weight_reel = $linedetail->weight_reel * pow(10,$linedetail->weight_reel_unit );

		return $weight_reel / $poids;
	}

	private function move_assets_according_to_BDR($object, $event = '', $reverse = false)
	{
		global $conf, $db, $user, $langs;

		$object->old_details = array();

		dol_include_once('/dispatch/config.php');
		dol_include_once('/dispatch/class/dispatchdetail.class.php');

		if($conf->{ ATM_ASSET_NAME }->enabled)
		{
			dol_include_once('/' . ATM_ASSET_NAME . '/class/asset.class.php');

			$PDOdb = new TPDOdb();

			if (!empty($object->lines))
			{
				foreach ($object->lines as $line) {
					$d = new TRecepBDRDetail();

					$d->loadLines($PDOdb, $line->id);

					if (!empty($d->lines))
					{
						foreach ($d->lines as $detail)
						{
							$asset = new TAsset();

							$prod = new Product($db);
							$prod->fetch($line->fk_product);

							$ret = $asset->loadReference($PDOdb, $detail->serial_number, $detail->fk_product);
							if (!$ret) // l'asset n'existe pas
							{
								$asset->fk_product = $detail->fk_product;
								$asset->serial_number = $detail->serial_number;
								$asset->weight = $detail->weight;
								$asset->weight_reel = $detail->weight_reel;
								$asset->fk_asset_type = $asset->get_asset_type($PDOdb, $prod->id);
							}

							$asset->fk_societe_localisation = 0;
                            $object->old_details[$asset->id] = $asset->fk_entrepot;
							$asset->fk_entrepot = $detail->fk_warehouse;
							$qty = $detail->weight_reel;

							if ($reverse)
							{
								$qty = -$qty;
								$asset->fk_societe_localisation = $object->socid;
								$asset->fk_entrepot = 0;
							}

							$asset->save($PDOdb, $user, $langs->trans($event."InDolibarr",$object->ref), $qty, false, 0, true);

							//dernier mouvement de stock standard dolibarr créé
							$sql = "SELECT MAX(rowid) as rowid FROM " .MAIN_DB_PREFIX. "stock_mouvement";
							$resql = $db->query($sql);

							if($obj = $db->fetch_object($resql)){
							    $last_moov = $obj->rowid;
                            }

							// Destockage équipement
							$stock = new TAssetStock;
							$stock->mouvement_stock($PDOdb, $user, $asset->getId(), $qty, $langs->trans($event."InDolibarr",$object->ref), $detail->fk_bonderetourdet, $last_moov);
						}
					}
				}
			}
		}
	}

    /**
     * @param $object
     *
     * @return bool
     */
	private function move_assets_according_to_shipment($object) {
	    global $conf, $db, $langs;
		if(!defined('INC_FROM_DOLIBARR')) define('INC_FROM_DOLIBARR', true);
		dol_include_once('/dispatch/config.php');
        dol_include_once('/dispatch/class/dispatchdetail.class.php');

        $PDOdb = new TPDOdb();

        $noDetailAlert = true;

        // Pour chaque ligne de l'expédition
        foreach($object->lines as $line) {
            // Chargement de l'objet detail dispatch relié à la ligne d'expédition
            $dd = new TDispatchDetail();

            $TIdExpeditionDet = TRequeteCore::get_id_from_what_you_want($PDOdb, MAIN_DB_PREFIX.'expeditiondet', array('fk_expedition' => $object->id, 'fk_origin_line' => $line->fk_origin_line));
            $idExpeditionDet = $TIdExpeditionDet[0];
            $nb_year_garantie = 0;

            //if(!empty($idExpeditionDet) && $dd->loadBy($PDOdb, $idExpeditionDet, 'fk_expeditiondet')) {
            if(!empty($idExpeditionDet)) {

                $dd->loadLines($PDOdb, $idExpeditionDet);

                if($conf->{ ATM_ASSET_NAME }->enabled){
                    // Création des mouvements de stock de flacon
                    foreach($dd->lines as $detail) {
                        // Création du mouvement de stock standard
                        $poids_destocke = $this->create_flacon_stock_mouvement($PDOdb, $detail, $object->ref,(empty($object->fk_soc)?$object->socid:$object->fk_soc), $object->id);
                        if($poids_destocke > 0) $noDetailAlert = false;
                        //$this->create_standard_stock_mouvement($line, $poids_destocke, $object->ref);

                        if($conf->clinomadic->enabled){
                            $asset = new TAsset;
                            $asset->load($PDOdb, $detail->fk_asset);

                            $prod = new Product($db);
                            $prod->fetch($asset->fk_product);

                            //Affectation du type d'équipement pour avoir accès aux extrafields équipement
                            $asset->fk_asset_type = $asset->get_asset_type($PDOdb, $prod->id);
                            $asset->load_asset_type($PDOdb);

                            //Localisation client
                            $asset->fk_societe_localisation = $object->socid;

                            if(!empty($object->linkedObjects['commande'][0]->array_options['options_duree_pret'])){
                                $asset->etat = 2; //Prêté
                                $asset->set_date('date_deb_pret', $object->date_valid);
                                $asset->set_date('date_fin_pret', strtotime('+'.$object->commande[0]->array_options['options_duree_pret'].'year',$object->date_valid));
                            }
                            else{
                                $asset->etat = 1; //Vendu
                            }

                            foreach($object->linkedObjects['commande'][0]->lines as $line){
                                if($line->fk_product == $asset->fk_product){
                                    $extension_garantie = $line->array_options['options_extension_garantie'];
                                }
                            }

                            $nb_year_garantie+=$prod->array_options['options_duree_garantie_client'];

                            $asset->date_fin_garantie_cli = strtotime('+'.$nb_year_garantie.'year', $object->date_valid);
                            $asset->date_fin_garantie_cli = strtotime('+'.$extension_garantie.'year', $asset->date_fin_garantie_cli);

                            $asset->save($PDOdb);
                        }
                    }
                }
                //exit;
            } // if(!empty($idExpeditionDet))
            /* else { // Pas de détail, on déstocke la quantité comme Dolibarr standard
                $this->create_standard_stock_mouvement($line, $line->qty, $object->ref);
            }*/
        }

        if(! empty($conf->global->DISPATCH_SHIPPING_VALIDATE_ALERT_IF_NO_DETAIL) && $noDetailAlert) {
            $langs->load('dispatch@dispatch');
            setEventMessage('DispatchExpeditionNoDetail');
        }

    }
}
