<?php


/**
 * Determines whether a shipment can be closed
 *
 * @param	Expedition	$shipment	Shipment to be examined
 *
 * @return	boolean			true if shipment can be closed, false otherwise
 */
function dispatch_shipment_can_be_closed(Expedition &$shipment)
{
    global $langs;

	dol_include_once('/dispatch/class/dispatchdetail.class.php');
	dol_include_once('/product/class/product.class.php');

	$PDOdb = new TPDOdb;
	$errors = array();

	if(empty($shipment->lines) && method_exists($shipment, 'fetch_lines'))
	{
		$shipment->fetch_lines();
	}

	foreach($shipment->lines as $line)
	{
		if(empty($line->fk_product))
		{
			continue;
		}

		$dispatchDetailStatic = new TDispatchDetail;

		$TDetail = $dispatchDetailStatic->LoadAllBy($PDOdb, array('fk_expeditiondet' => $line->id));

		if(empty($TDetail))
		{
			$product = new Product($shipment->db);
			$product->fetch($line->fk_product);

			if(empty($product->array_options) && method_exists($product, 'fetch_optionals'))
			{
				$product->fetch_optionals();
			}

			// Si type d'équipement renseigné pour ce produit, il doit être sérialisé
			if(! empty($product->array_options['options_type_asset']))
			{
			    if (!in_array($langs->transnoentities('ShipmentCannotBeClosedSerializedLinesHaveNoDetail'), $errors))
			        $errors[] = $langs->transnoentities('ShipmentCannotBeClosedSerializedLinesHaveNoDetail');
				/*$PDOdb->close();

				return false;*/
			}
		}
		else
		{
			$qty = 0;

			foreach ($TDetail as $dispatchDetail)
			{
				if (empty($dispatchDetail->is_prepared))
				{
                    if (!in_array($langs->transnoentities('ShipmentCannotBeClosedAssetsNotPrepared'), $errors))
                        $errors[] = $langs->transnoentities('ShipmentCannotBeClosedAssetsNotPrepared');
					/*$PDOdb->close();

					return false;*/
				}
				else $qty += $dispatchDetail->weight_reel;
			}

			if($qty < $line->qty)
			{
			    // ShipmentCannotBeClosedQtyDoesntMatch
                if (!in_array($langs->transnoentities('ShipmentCannotBeClosedAssetsNotPrepared'), $errors))
                    $errors[] = $langs->transnoentities('ShipmentCannotBeClosedAssetsNotPrepared');

				/*$PDOdb->close();

				return false;*/
			}
		}
	}

	$PDOdb->close();
	if (empty($errors))
    {
        $return = array(true, '');
    }
	else
    {
        $msg = "\n".implode("\n", $errors);
        $return = array(false, $msg);
    }

	return $return;
}
