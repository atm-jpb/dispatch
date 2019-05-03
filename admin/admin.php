<?php
	
	require '../config.php';
	//require('../lib/asset.lib.php');
	require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
	
	global $user,$langs,$db,$const,$conf;
	
	$langs->load('dispatch@dispatch');
	$langs->load('admin');
	
	if (!($user->admin)) accessforbidden();
	
	$action=__get('action','');

	if($action=='save') {
		
		foreach($_REQUEST['TDispatch'] as $name=>$param) {
			
			dolibarr_set_const($db, $name, $param, 'chaine', 0, '', $conf->entity);
			
		}
		
		setEventMessage("Configuration enregistrée");
	}

	if($action == 'setconst') {
		$const = GETPOST('const', 'alpha');
		dolibarr_set_const($db,$const,GETPOST($const,'alpha'),'chaine',0,'',$conf->entity);
	}
	

	llxHeader('','Gestion des détails Réception/Expédidion, à propos', '');
	
	//$head = assetPrepareHead();
	$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php">'.$langs->trans("BackToModuleList").'</a>';
	dol_fiche_head($head, 1, $langs->trans("Dispatch"), 0, '');
	print_fiche_titre($langs->trans("DispatchSetup"),$linkback);
	
	print '<table class="noborder" width="100%">';
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Parameters").'</td>'."\n";
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";
	
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("UseImportFile").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="center" width="300">';
	print ajax_constantonoff('DISPATCH_USE_IMPORT_FILE');
	print '</td></tr>';
	
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("DispatchRecepAutoQuantity").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="center" width="300">';
	print ajax_constantonoff('DISPATCH_RECEP_AUTO_QUANTITY');
	print '</td></tr>';
	
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("DISPATCH_HIDE_DLUO_PDF").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="center" width="300">';
	print ajax_constantonoff('DISPATCH_HIDE_DLUO_PDF');
	print '</td></tr>';
	
	$form=new TFormCore;
	
	print '<tr class="liste_titre">';
	print '<td>'.$langs->trans("Réception commande fournisseur").'</td>'."\n";
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="center" width="100">'.$langs->trans("Value").'</td>'."\n";
	print '<tr>';
	
	// Champ supplémentaire contenant le code comptable produit pour les ventes CEE
	$var=! $var;
	$form = new TFormCore($_SERVER["PHP_SELF"],'const_dluo_by_default');
	print $form->hidden('action','setconst');
	print $form->hidden('const','DISPATCH_DLUO_BY_DEFAULT');
	print '<tr '.$bc[$var].'><td>';
	print $langs->trans("DispatchDLUOByDefault");
	print '</td><td align="right">';
	print $form->texte('', 'DISPATCH_DLUO_BY_DEFAULT',$conf->global->DISPATCH_DLUO_BY_DEFAULT,30,255);
	print '</td><td align="center">';
	print '<input type="submit" class="button" value="'.$langs->trans("Modify").'" />';
	print "</td></tr>\n";
	$form->end();
	
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("DISPATCH_UPDATE_ORDER_PRICE_ON_RECEPTION").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="center" width="300">';
	print ajax_constantonoff("DISPATCH_UPDATE_ORDER_PRICE_ON_RECEPTION");
	print '</td></tr>';
	
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("DISPATCH_CREATE_SUPPLIER_PRICE").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="center" width="300">';
	print ajax_constantonoff("DISPATCH_CREATE_SUPPLIER_PRICE");
	print '</td></tr>';

	
        $var=!$var;
        print '<tr '.$bc[$var].'>';
        print '<td>'.$langs->trans("DISPATCH_USE_ONLY_UNIT_ASSET_RECEPTION").'</td>';
        print '<td align="center" width="20">&nbsp;</td>';
        print '<td align="center" width="300">';
        print ajax_constantonoff("DISPATCH_USE_ONLY_UNIT_ASSET_RECEPTION");
        print '</td></tr>';

        $var=!$var;
        print '<tr '.$bc[$var].'>';
        print '<td>'.$langs->trans("DISPATCH_SHOW_UNIT_RECEPTION").'</td>';
        print '<td align="center" width="20">&nbsp;</td>';
        print '<td align="center" width="300">';
        print ajax_constantonoff("DISPATCH_SHOW_UNIT_RECEPTION");
        print '</td></tr>';

	
	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans("DISPATCH_CREATE_NUMSERIE_ON_RECEPTION_IF_LOT").'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="center" width="300">';
	print ajax_constantonoff('DISPATCH_CREATE_NUMSERIE_ON_RECEPTION_IF_LOT');
	print '</td></tr>';

	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans('DISPATCH_CREATE_NUMSERIE_ON_RECEPTION_FROM_FIRST_INPUT').'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="center" width="300">';
	print ajax_constantonoff('DISPATCH_CREATE_NUMSERIE_ON_RECEPTION_FROM_FIRST_INPUT');
	print '</td></tr>';

	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans('DISPATCH_CAN_LINK_ASSET_TO_OBJECT_IN_ANY_STATUS').'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="center" width="300">';
	print ajax_constantonoff('DISPATCH_CAN_LINK_ASSET_TO_OBJECT_IN_ANY_STATUS');
	print '</td></tr>';

	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans('DISPATCH_SHIPPING_VALIDATE_ALERT_IF_NO_DETAIL').'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="center" width="300">';
	print ajax_constantonoff('DISPATCH_SHIPPING_VALIDATE_ALERT_IF_NO_DETAIL');
	print '</td></tr>';

    $var=!$var;
    print '<tr '.$bc[$var].'>';
    print '<td>'.$langs->trans('DISPATCH_SHIPPING_LINES_CAN_BE_CHECKED_PREPARED').'</td>';
    print '<td align="center" width="20">&nbsp;</td>';
    print '<td align="center" width="300">';
    print ajax_constantonoff('DISPATCH_SHIPPING_LINES_CAN_BE_CHECKED_PREPARED');
    print '</td></tr>';

	$var=!$var;
	print '<tr '.$bc[$var].'>';
	print '<td>'.$langs->trans('DISPATCH_BLOCK_SHIPPING_CLOSING_IF_PRODUCTS_NOT_PREPARED').'</td>';
	print '<td align="center" width="20">&nbsp;</td>';
	print '<td align="center" width="300">';
	print ajax_constantonoff('DISPATCH_BLOCK_SHIPPING_CLOSING_IF_PRODUCTS_NOT_PREPARED');
	print '</td></tr>';

	
	print "</table>";

	dol_fiche_end();
	llxFooter();
