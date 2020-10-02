<?php
/* Copyright (C) 2020 JpB
/* Copyright (C) 2020 Kévin GIUGA
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Library javascript to enable Browser notifications
 */

if (!defined('NOREQUIREUSER'))  define('NOREQUIREUSER', '1');
if (!defined('NOREQUIREDB'))    define('NOREQUIREDB','1');
if (!defined('NOREQUIRESOC'))   define('NOREQUIRESOC', '1');
if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);
if (!defined('NOLOGIN'))        define('NOLOGIN', 1);
if (!defined('NOREQUIREMENU'))  define('NOREQUIREMENU', 1);
if (!defined('NOREQUIREHTML'))  define('NOREQUIREHTML', 1);
if (!defined('NOREQUIREAJAX'))  define('NOREQUIREAJAX','1');


/**
 * \file    js/dispatch.js.php
 * \ingroup dispatch
 * \brief   JavaScript file for module dispatch.
 */

// Load Dolibarr environment
$res=0;
// Try main.inc.php into web root known defined into CONTEXT_DOCUMENT_ROOT (not always defined)
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include($_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php");
// Try main.inc.php into web root detected using web root caluclated from SCRIPT_FILENAME
$tmp=empty($_SERVER['SCRIPT_FILENAME'])?'':$_SERVER['SCRIPT_FILENAME'];$tmp2=realpath(__FILE__); $i=strlen($tmp)-1; $j=strlen($tmp2)-1;
while($i > 0 && $j > 0 && isset($tmp[$i]) && isset($tmp2[$j]) && $tmp[$i]==$tmp2[$j]) { $i--; $j--; }
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/main.inc.php")) $res=@include(substr($tmp, 0, ($i+1))."/main.inc.php");
if (! $res && $i > 0 && file_exists(substr($tmp, 0, ($i+1))."/../main.inc.php")) $res=@include(substr($tmp, 0, ($i+1))."/../main.inc.php");
// Try main.inc.php using relative path
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");
if (! $res) die("Include of main fails");

// Define js type
header('Content-Type: application/javascript');
// Important: Following code is to cache this file to avoid page request by browser at each Dolibarr page access.
// You can use CTRL+F5 to refresh your browser cache.
if (empty($dolibarr_nocache)) header('Cache-Control: max-age=3600, public, must-revalidate');
else header('Cache-Control: no-cache');


// Load traductions files requiredby by page
$langs->loadLangs(array("dispatch@dispatch","other"));
?>

/* Javascript library of module dispatch */
$(document).ready(function() {

	$(document).on("click", ".butActionDelete", function (e) {
		e.preventDefault();
		$(".shipment-details").remove();
		$('.ventileBtn').removeClass('butActionRefused');
	});

	$(document).on("click", ".ventileBtn", function (e) {
		console.log($( "select:first" ).val());
		console.log($('#TLine[-1][entrepot]'));
		console.log($( "selected" ).val());
		idWareHouse =  -1 ;
		idWareHouse = $( "select:first" ).val();

		$('.ventileBtn').removeClass('butActionRefused');
		$(this).addClass('butActionRefused');

		let comFourn = $(this).attr('data-commandFourn-id');
		let expeid = $(this).attr('data-shipment-id');
		let experef = $(this).attr('data-shipment-ref');
		let entity = $(this).attr('data-shipment-entity');

		var className = "shipment-details";
		if (document.getElementsByClassName(className).length == 0) {
			d = document.createElement("div");
			d.className = className;
			$(".tabBar").append(d);
		}

		let data = {
			comFourn : comFourn,
			idexpe: expeid,
			refexpe: experef,
			entity : entity,
			idWarehouse : idWareHouse,
			action: "loadExpeLines"
		};

		$.ajax({
			url: "<?php print dol_buildpath('dispatch/script/interface_expedition_handler.php', 1)?>",
			method: "POST",
			dataType: "json",
			data: data,
			success: function (data) {
				if(!data.error) {
					$(".shipment-details").html(data.html);
				}
			}
		})
	});
});
