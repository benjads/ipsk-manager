<?php

/**
 *@license
 *
 *Copyright 2021 Cisco Systems, Inc. or its affiliates
 *
 *Licensed under the Apache License, Version 2.0 (the "License");
 *you may not use this file except in compliance with the License.
 *You may obtain a copy of the License at
 *
 *  http://www.apache.org/licenses/LICENSE-2.0
 *
 *Unless required by applicable law or agreed to in writing, software
 *distributed under the License is distributed on an "AS IS" BASIS,
 *WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 *See the License for the specific language governing permissions and
 *limitations under the License.
 */

	
	//Clear Variables and set to blank
	$pageData['errorMessage'] = "";
    $pageData['createComplete'] = "";
	$pageData['endpointGroupList'] = "";
	$pageData['wirelessSSIDList'] = "";
	$pageData['endpointAssociationList'] = "";
	$pageData['pageinationOutput'] = '';
	$totalPages = 0;
	$currentPage = 0;
	$currentPageSizeSelection = "";
	
	
	if(!ipskLoginSessionCheck()){
		$portalId = $_GET['portalId'];
		$_SESSION = null;
		session_destroy();
		header("Location: /index.php?portalId=".$portalId);
		die();
	}

	$pageSize = (isset($_GET['pageSize'])) ? $_GET['pageSize'] : 25;
	$currentPage = (isset($_GET['currentPage'])) ? $_GET['currentPage'] : 1;
	$pageNotice = (isset($_GET['notice'])) ? $_GET['notice'] : 0;
	
	$queryDetails = "pageSize=$pageSize&currentPage=$currentPage";
	
	$listCount = 0;

	$endpointAssociationList = $ipskISEDB->getEndPointAssociationList($_SESSION['authorizationGroups'], $_SESSION['portalSettings']['id'], $_SESSION['portalAuthorization']['viewall'], $_SESSION['portalAuthorization']['viewallDn']);

	if($endpointAssociationList){
		$pageData['endpointAssociationList'] .= '<table class="table table-hover"><thead><tr><th scope="col"><div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input" base-value="1" value="0" id="allCheck"><label class="custom-control-label" for="allCheck">MAC Address</label></div></th><!--<th scope="col">Endpoint Group</th>--><th scope="col">Expiration Date</th><th scope="col">View</th><th scope="col">Actions</th></tr></thead><tbody>';
		
		for($idxId = 0; $idxId < $endpointAssociationList['count']; $idxId++) {
			$viewEnabled = false;
			
			if($_SESSION['portalAuthorization']['viewall'] == true){
				$viewEnabled = true;
			}elseif($endpointAssociationList[$idxId]['viewPermissions'] & 4){
				$viewEnabled = true;
			}elseif($endpointAssociationList[$idxId]['viewPermissions'] & 2){
				for($groupCount = 0; $groupCount < $_SESSION['authorizedEPGroups']['count']; $groupCount++){
					if($endpointAssociationList[$idxId]['epGroupId'] == $_SESSION['authorizedEPGroups'][$groupCount]['endpointGroupId']){
						if($_SESSION['authorizedEPGroups'][$groupCount]['viewPermissions'] & 2){
							$viewEnabled = true;
						}
					}
				}
			}elseif($endpointAssociationList[$idxId]['viewPermissions'] & 1){
				if($endpointAssociationList[$idxId]['createdBy'] == $_SESSION['logonSID']){
					$viewEnabled = true;
				}
			}
			
			if($viewEnabled == true){
				
				if($endpointAssociationList[$idxId]['accountEnabled'] == 1){
					if($endpointAssociationList[$idxId]['expirationDate'] == 0){
						$expiration = "Never";
					}elseif($endpointAssociationList[$idxId]['expirationDate'] < time()){
						$expiration = '<span class="text-danger">Expired</span>';
					}else{
						$expiration = date($globalDateOutputFormat,$endpointAssociationList[$idxId]['expirationDate']);
					}
				}else{
					$expiration = "Suspended";
				}
			
			
				$actionRowData = "";
				
				//Suspend Permission
				if($endpointAssociationList[$idxId]['groupPermissions'] & 16){
					$actionRowData .= '<a class="dropdown-item action-tableicons" module="suspend" row-id="'.$endpointAssociationList[$idxId]['id'].'" href="#">Suspend</a>';
				}
				
				//Activate Permission
				if($endpointAssociationList[$idxId]['groupPermissions'] & 32){
					$actionRowData .= '<a class="dropdown-item action-tableicons" module="activate" row-id="'.$endpointAssociationList[$idxId]['id'].'" href="#">Activate</a>';
				}
				
				//Extend Permission
				if($endpointAssociationList[$idxId]['groupPermissions'] & 128){
					$actionRowData .= '<a class="dropdown-item action-tableicons" module="extend" row-id="'.$endpointAssociationList[$idxId]['id'].'" href="#">Extend</a>';
				}
				
				//Edit Permission
				if($endpointAssociationList[$idxId]['groupPermissions'] & 256){
					$actionRowData .= '<a class="dropdown-item action-tableicons" module="edit" row-id="'.$endpointAssociationList[$idxId]['id'].'" href="#">Edit</a>';
				}	
				
				//Delete Permission
				if($endpointAssociationList[$idxId]['groupPermissions'] & 64){
					$actionRowData .= '<a class="dropdown-item action-tableicons" module="delete" row-id="'.$endpointAssociationList[$idxId]['id'].'" href="#">Delete</a>';
				}
				
				if($actionRowData != ""){
					$actionRow = '<div class="dropdown"><a class="dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" href="#"><span data-feather="more-vertical"></span></a><div class="dropdown-menu" aria-labelledby="dropdownMenuButton">'.$actionRowData.'</div></div>';
				}else{
					$actionRow = '<div></div>';
				}			
				
				$associationList[$listCount]['view'] = '<a class="action-tableicons" module="view" row-id="'.$endpointAssociationList[$idxId]['id'].'" href="#"><span data-feather="zoom-in"></span></a>';
				$associationList[$listCount]['action'] = $actionRow;
				$associationList[$listCount]['macAddress'] = '<div class="custom-control custom-checkbox"><input type="checkbox" class="custom-control-input checkbox-update endpointCheckBox" name="multiEndpoint" base-value="'.$endpointAssociationList[$idxId]['id'].'" value="0" id="multiEndpoint-'.$endpointAssociationList[$idxId]['id'].'"><label class="custom-control-label" for="multiEndpoint-'.$endpointAssociationList[$idxId]['id'].'">'.$endpointAssociationList[$idxId]['macAddress'].'</label></div>';
				$associationList[$listCount]['epGroupName'] = $endpointAssociationList[$idxId]['groupName'];
				$associationList[$listCount]['expiration'] = $expiration;
				$associationList[$listCount]['id'] = $endpointAssociationList[$idxId]['id'];

				$listCount++;
			}
			
		}
	}
	
	$pageSizes = Array(25, 50, 75, 100);
	
	foreach($pageSizes as $entry){
		if($entry == $pageSize){
			$currentPageSizeSelection .= '<option value="'.$entry.'" selected>'.$entry.'</option>';
		}else{
			$currentPageSizeSelection .= '<option value="'.$entry.'">'.$entry.'</option>';
		}
	}
	
	$associationList['count'] = $listCount;
	
	$totalPages = ceil($associationList['count'] / $pageSize);
	
	if($currentPage > $totalPages){
		$currentPage = $totalPages;
	}
		
	$nextPage = $currentPage + 1;
	
	if($currentPage == 0 || $currentPage == 1){
		$currentPage = 1;
		
		$pageStart = 0;
		$pageEnd = $pageStart + $pageSize;
		
		if($pageEnd > $associationList['count']){
			$pageEnd = $associationList['count'];
		}
		
	}else{
		$pageStart = ($currentPage - 1) * $pageSize;
		$pageEnd = $pageStart + $pageSize;
		
		$previousPage = $currentPage - 1;
		
		$pageData['pageinationOutput'] .= '<a class="action-pageicons mx-1" page="1" href="#"><span data-feather="chevrons-left"></span></a>';
		$pageData['pageinationOutput'] .= '<a class="action-pageicons mx-1" page="'.$previousPage.'" href="#"><span data-feather="chevron-left"></span></a>';		
		
		if($pageEnd > $associationList['count']){
			$pageEnd = $associationList['count'];
		}
	}
	
	$pageData['pageinationOutput'] .= "<strong>".$currentPage."</strong>";
	
	if($currentPage != $totalPages && $totalPages != 0){
		$pageData['pageinationOutput'] .= '<a class="action-pageicons mx-1" page="'.$nextPage.'" href="#"><span data-feather="chevron-right"></span></a>';
		$pageData['pageinationOutput'] .= '<a class="action-pageicons mx-1" page="'.$totalPages.'" href="#"><span data-feather="chevrons-right"></span></a>';
	}
	
	for($assocId = $pageStart;$assocId < $pageEnd; $assocId++){
		$pageData['endpointAssociationList'] .= '<tr>';
		$pageData['endpointAssociationList'] .= '<td>'.$associationList[$assocId]['macAddress'].'</td>';
//		$pageData['endpointAssociationList'] .= '<td>'.$associationList[$assocId]['epGroupName'].'</td>';
		$pageData['endpointAssociationList'] .= '<td>'.$associationList[$assocId]['expiration'].'</td>';
		$pageData['endpointAssociationList'] .= '<td>'.$associationList[$assocId]['view'].'</td>';
		$pageData['endpointAssociationList'] .= '<td>'.$associationList[$assocId]['action'].'</td>';
		$pageData['endpointAssociationList'] .= '</tr>';
	}
	
	$pageData['endpointAssociationList'] .= "</tbody></table>";

    if($_SESSION['portalAuthorization']['create'] == true){
        $pageData['createButton'] = '<div class="col py-1"><button id="createAssoc" class="btn btn-primary shadow" type="button">Enroll a device</button></div>';
    }else{
        $pageData['createButton'] = '';
    }

    if($_SESSION['portalAuthorization']['bulkcreate'] == true){
        $pageData['bulkButton'] = '<div class="col py-1"><button id="bulkAssoc" class="btn btn-primary shadow" type="button">Bulk enroll</button></div>';
    }else{
        $pageData['bulkButton'] = '';
    }
	
	if($pageNotice){
		$pageData['pageNotice'] = '<div class="row"><div class="col-1"></div><div class="col"><span class="h5 text-danger"><strong>Notice:</strong> You have exceeded your allotment of devices you are allowed to enroll</span></div><div class="col-1"></div></div>';
	}else{
		$pageData['pageNotice'] = "";
	}
	
	print <<< HTML
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="icon" href="images/favicon.png">
	
	<title>{$portalSettings['portalName']}</title>
    

    <!-- Bootstrap core CSS -->
    <link href="styles/bootstrap.min.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="styles/sponsor.css" rel="stylesheet">
  </head>

  <body>
	<div class="container">
		<div class="float-rounded mx-auto shadow-lg p-2 bg-white text-center">
			{$pageData['pageNotice']}
			<div class="mt-2 mb-4">
				<img src="images/ucsc-logo-ipsk.png" height="50px" />
			</div>
			<h1 class="h3 mt-2 mb-4 font-weight-normal">{$portalSettings['portalName']}</h1>
			<div class="mb-3 mx-auto shadow p-2 bg-white border border-primary">
				<div class="container">
					<div class="row">
						{$pageData['createButton']}
						{$pageData['bulkButton']}
						<div class="col py-1">
							<button id="manageAssoc" class="btn btn-primary shadow" type="button">Manage enrollments</button>
						</div>
						<div class="col py-1">
							<button id="signOut" class="btn btn-primary shadow" type="button">Sign out</button>
						</div>
					</div>
				</div>
			</div>
			<div class="row text-left">
				<div class="col-sm"></div>
				<div class="col-10 col-sm-10 mt-2 shadow mx-auto p-2 bg-white border border-primary text-center">
					<h4 class="h4">Manage device enrollments</h4>
				</div>
				<div class="col-sm"></div>
			</div>
			<div id="bulkOptions" class="row text-left d-none">
				<div class="col-sm"></div>
				<div class="col-10 mt-2 shadow mx-auto p-2 bg-white border border-primary text-center">
					<h5 class="h5 text-danger">Bulk Selected Options</h5>
					<div class="row">
						<div class="col"><button class="btn btn-primary shadow bulkaction-button" module="bulkupdate" sub-module="suspend" type="button">Suspend</button></div>
						<div class="col"><button class="btn btn-primary shadow bulkaction-button" module="bulkupdate" sub-module="activate" type="button">Activate</button></div>
						<div class="col"><button class="btn btn-primary shadow bulkaction-button" module="bulkupdate" sub-module="delete" type="delete">Delete</button></div>
					</div>
				</div>
				<div class="col-sm"></div>
			</div>
			<div class="overflow-auto row text-left">
				<div class="col-sm"></div>
				<div class="col-10 mt-2 shadow mx-auto p-2 bg-white border border-primary">
					<div class="table-responsive">
						{$pageData['endpointAssociationList']}
					</div>
					<div class="row">
						<div class="col"><hr></div>
					</div>
					<div class="row">
						<div class="col-4">
							<label class="font-weight-bold" for="pageSize">Items per Page:</label>
							<select id="pageSize">$currentPageSizeSelection</select>
						</div>
						<div class="col text-center"><strong>Total Items: ({$associationList['count']})  Total Pages: $totalPages</strong></div>
						<div class="col-4 text-right">
							{$pageData['pageinationOutput']}
						</div>
					</div>
				</div>
				<div class="col-sm"></div>
			</div>
		</div>
		<div class="m-0 mx-auto p-2 bg-white text-center">
			<p>For assistance, email resnet@ucsc.edu, call (831) 459-4638, or visit <a href="https://its.ucsc.edu/resnet" target="_blank">UCSC Residential Network Services</a>.</p>
			<div class="row justify-content-center pb-2">
			    <div class="col-5 col-md-2 border-right">
			        <a href="https://its.ucsc.edu/resnet/get-connected/enroll-device.html" target="_blank">Instructions</a>
			    </div>
			    <div class="col-6 col-md-3">
			        <a href="https://its.ucsc.edu/policies/resnet-rup.html" target="_blank">Responsible Use Policy</a>
			    </div>
			</div>
		</div>
	</div>
  <div id="popupcontent"></div>
  </body>
  <script type="text/javascript" src="scripts/jquery.min.js"></script>
  <script type="text/javascript" src="scripts/feather.min.js"></script>
  <script type="text/javascript" src="scripts/popper.min.js"></script>
  <script type="text/javascript" src="scripts/bootstrap.min.js"></script>
  <script type="text/javascript" src="scripts/ipsk-portal-v1.js"></script>
  <script type="text/javascript">
	var formData;
	var stillChecked;
	
	$(function() {	
		feather.replace()
	});
	
	$(".action-tableicons").click(function(event) {
		$.ajax({
			url: "/" + $(this).attr('module') + ".php?portalId=$portalId&$queryDetails",
			
			data: {
				id: $(this).attr('row-id')
			},
			type: "POST",
			dataType: "html",
			success: function (data) {
				$('#popupcontent').html(data);
				//alert("success");
			}
		});
		
		event.preventDefault();
	});
	
	$(".action-pageicons").click(function(event) {
		window.location.href = "/manage.php?portalId=$portalId&pageSize=" + $("#pageSize").val() + "&currentPage=" + $(this).attr("page");
	});
	
	$("#createAssoc").click(function() {
		window.location.href = "/sponsor.php?portalId=$portalId";
	});
	
	$("#bulkAssoc").click(function() {
		window.location.href = "/bulk.php?portalId=$portalId";
	});
	
	$("#manageAssoc").click(function() {
		window.location.href = "/manage.php?portalId=$portalId";
	});
	
	$("#pageSize").change(function() {
		window.location.href = "/manage.php?portalId=$portalId&pageSize=" + $(this).val();
	});
		
	$("#signOut").click(function(event) {
		$.ajax({
			url: "/logoff.php?portalId=$portalId",
			
			data: {
				logoff: true
			},
			type: "POST",
			dataType: "html",
			success: function (data) {
				window.location = "/index.php?portalId=$portalId";
			}
		});
		
		event.preventDefault();
	});
	
	$("#allCheck").change(function(){
		if($(this).prop('checked')){
			$(this).attr('value', $(this).attr('base-value'));
			$(".endpointCheckBox").each(function () {
				$(this).attr('value', $(this).attr('base-value'));
				$(this).prop( "checked", true );
			});
			$("#bulkOptions").removeClass('d-none');
		}else{
			$(this).attr('value', '0');
			$(".endpointCheckBox").each(function () {
				$(this).attr('value', '0');
				$(this).prop( "checked", false );
			});
			$("#bulkOptions").addClass('d-none');
		}
	});

	$(".checkbox-update").change(function(){
		stillChecked = false;

		if($(this).prop('checked')){
			$(this).attr('value', $(this).attr('base-value'));
			$("#bulkOptions").removeClass('d-none');
		}else{
			$(this).attr('value', '0');

			$("#allCheck").prop( "checked", false );

			$(".checkbox-update").each(function () {
				if($(this).val() != 0){
					stillChecked = true;
				}
			});

			if(stillChecked){
				$("#bulkOptions").removeClass('d-none');
			}else{
				$("#bulkOptions").addClass('d-none');
			}
		}
	});

	$(".bulkaction-button").click(function(event) {
		formData = new FormData();
		var multiSelect;

		formData.append('sub-module', $(this).attr('sub-module'));

		$(".endpointCheckBox").each(function() {
			if($(this).val() != 0){
				formData.append('id[]', $(this).val());
				multiSelect = true;
			}
		});

		if(multiSelect){
			$.ajax({
				url: "/" + $(this).attr('module') + ".php?portalId=$portalId&$queryDetails",

				data: formData,
				processData: false,
				contentType: false,
				type: "POST",
				dataType: "html",
				success: function (data) {
					$('#popupcontent').html(data);
				}
			});
		}

		event.preventDefault();
	});

	</script>
</html>
HTML;

?>