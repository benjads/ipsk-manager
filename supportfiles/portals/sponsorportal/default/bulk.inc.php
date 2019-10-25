<?php

/**
 *@license
 *Copyright (c) 2019 Cisco and/or its affiliates.
 *
 *This software is licensed to you under the terms of the Cisco Sample
 *Code License, Version 1.1 (the "License"). You may obtain a copy of the
 *License at
 *
 *			   https://developer.cisco.com/docs/licenses
 *
 *All use of the material herein must be in accordance with the terms of
 *the License. All rights not expressly granted by the License are
 *reserved. Unless required by applicable law or agreed to separately in
 *writing, software distributed under the License is distributed on an "AS
 *IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express
 *or implied.
 */
	
	
	//Clear Variables and set to blank
	$pageData['endpointGroupList'] = "";
	$pageData['wirelessSSIDList'] = "";
	$pageData['endpointAssociationList'] = "";
	$pageData['iseEndpointGroups'] = "";
	
	
	if(!ipskLoginSessionCheck()){
		$portalId = $_GET['portalId'];		
		$_SESSION = null;
		session_destroy();
		header("Location: /index.php?portalId=".$portalId);
		die();
	}
	
	if($_SESSION['portalAuthorization']['bulkcreate'] == false){
		header("Location: /manage.php?portalId=".$portalId);
		die();
	}
	
	if(is_array($_SESSION['authorizedEPGroups'])){
		$pageData['endpointGroupList'] .= '<select name="associationGroup" id="associationGroup" class="form-control mt-2 mb-3 shadow">';
				
		for($count = 0; $count < $_SESSION['authorizedEPGroups']['count']; $count++) {
			if(!isset($trackSeenObjects[$_SESSION['authorizedEPGroups'][$count]['endpointGroupId']])){
				//Check if User is authorized for Bulk Create on EndPoint Group
				if($_SESSION['authorizedEPGroups'][$count]['groupPermissions'] & 2048){
					if($ipskISEDB->getUserEndpointCount($_SESSION['authorizedEPGroups'][$count]['endpointGroupId'], $_SESSION['logonSID']) < $_SESSION['authorizedEPGroups'][$count]['maxDevices']){
				
						if($_SESSION['authorizedEPGroups'][$count]['termLengthSeconds'] == 0){
							$termLength = "No Expiry";
						}else{
							$termLength = ($_SESSION['authorizedEPGroups'][$count]['termLengthSeconds'] / 60 / 60 / 24) . " Days";
						}
						
						if($_SESSION['authorizedEPGroups'][$count]['ciscoAVPairPSK'] == "*userrandom*"){
							$keyType = "Randomly Chosen per User";
						}elseif($_SESSION['authorizedEPGroups'][$count]['ciscoAVPairPSK'] == "*devicerandom*"){
							$keyType = "Randomly Chosen per Device";
						}else{
							$keyType = "Common PSK";
						}
						
						$pageData['endpointGroupList'] .= "<option data-keytype=\"$keyType\" data-term=\"$termLength\" value=\"".$_SESSION['authorizedEPGroups'][$count]['endpointGroupId']."\">".$_SESSION['authorizedEPGroups'][$count]['groupName']."</option>";
						$trackSeenObjects[$_SESSION['authorizedEPGroups'][$count]['endpointGroupId']] = true;
					}
				}
			}
		}
		$pageData['endpointGroupList'] .= "</select>";
		unset($trackSeenObjects);
	}
	
	if(is_array($_SESSION['authorizedWirelessNetworks'])){
		$pageData['wirelessSSIDList'] .= '<select name="wirelessSSID" class="form-control mt-2 mb-3 shadow">';
	
		for($count = 0; $count < $_SESSION['authorizedWirelessNetworks']['count']; $count++) {
			if(!isset($trackSeenObjects[$_SESSION['authorizedWirelessNetworks'][$count]['wirelessSSIDId']])){
				$pageData['wirelessSSIDList'] .= "<option value=\"".$_SESSION['authorizedWirelessNetworks'][$count]['wirelessSSIDId']."\">".$_SESSION['authorizedWirelessNetworks'][$count]['ssidName']."</option>";
				$trackSeenObjects[$_SESSION['authorizedWirelessNetworks'][$count]['wirelessSSIDId']] = true;
			}
		}
		$pageData['wirelessSSIDList'] .= "</select>";
		unset($trackSeenObjects);
	}
	
	if($_SESSION['portalAuthorization']['create'] == true){
		$pageData['createButton'] = '<button id="createAssoc" class="btn btn-primary shadow" type="button">Create Associations</button>';
	}else{
		$pageData['createButton'] = '';
	}
	
	if($_SESSION['portalAuthorization']['bulkcreate'] == true){
		$pageData['bulkButton'] = '<button id="bulkAssoc" class="btn btn-primary shadow" type="button">Bulk Associations</button>';
	}else{
		$pageData['bulkButton'] = '';
	}

	if($iseERSIntegrationAvailable){
		$pageData['bulkOption'] = '<option value="3">Cisco ISE "Endpoint Group" Import</option>';
	}else{
		$pageData['bulkOption'] = '';
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
			<form id="bulkAssociationform" action="bulkimport.php?portalId=$portalId" method="post">
				<div class="mt-2 mb-4">
					<img src="images/iPSK-Logo.svg" width="108" height="57" />
				</div>
				<h1 class="h3 mt-2 mb-4 font-weight-normal">{$portalSettings['portalName']}</h1>
				<h2 class="h6 mt-2 mb-3 font-weight-normal">Manage Identity Pre-Shared Keys ("iPSK") Associations</h2>
				<div class="mb-3 mx-auto shadow p-2 bg-white border border-primary">
					<div class="row">
						<div class="col-3">				
						{$pageData['createButton']}
						</div>
						<div class="col-3">				
						{$pageData['bulkButton']}
						</div>
						<div class="col-3">				
							<button id="manageAssoc" class="btn btn-primary shadow" type="button">Manage Associations</button>
						</div>
						<div class="col-3">				
							<button id="signOut" class="btn btn-primary shadow" type="button">Sign Out</button>
						</div>
					</div>
				</div>
				<div class="row text-left">
					<div class="col-2"></div>
					<div class="col-8 mt-2 shadow mx-auto p-2 bg-white border border-primary">
								<h6>Association type:</h6>
								{$pageData['endpointGroupList']}
								<div class="row">
									<div class="col pr-0">
										<p><small>
											Maximum access duration:&nbsp;<span id="duration" class="text-danger count">-</span>
										</small></p>
									</div>
									<div class="col pl-0">
										<p><small>
											Pre Shared Key Type:&nbsp;<span id="keyType" class="text-danger count">-</span>
										</small></p>
									</div>
								</div>
								<h6>Wireless SSID:</h6>
								{$pageData['wirelessSSIDList']}
					</div>
					<div class="col-2"></div>
				</div>
				<div class="row text-left">
					<div class="col-2"></div>
						<div class="col-8 mt-2 shadow mx-auto p-2 bg-white border border-primary">
							<h6>Bulk Import Type:</h6>
							<div class="row">
								<div class="col">
									<select name="bulkImportType" id="bulkImportType" class="form-control mt-2 mb-3 shadow"><option value="0">(Select an Import Option)</option>{$pageData['bulkOption']}</select>
								</div>
							</div>
						</div>
					<div class="col-2"></div>
				</div>
				<div id="textBulkImport" class="d-none row text-left">
					<div class="col-2"></div>
						<div class="col-8 mt-2 shadow mx-auto p-2 bg-white border border-primary">
							<h6>Select the file you would like to import:</h6>
							<div class="row">
								<div class="col">
									<div class="form-group">
										
									</div>
								</div>
							</div>
						</div>
					<div class="col-2"></div>
				</div>
				<div id="iseBulkImport" class="d-none row text-left">
					<div class="col-2"></div>
						<div class="col-8 mt-2 shadow mx-auto p-2 bg-white border border-primary">
							<h6>Select the Endpoint Identity Group you would like to import:</h6>
							<div class="row">
								<div class="col">
									<div class="form-group">
										<select name="groupUuid" id="iseEPGroups" class="form-control mt-2 mb-3 shadow"></select>
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col pr-0">
									<p><small>
										Description:&nbsp;<span id="iseepgDescription" class="text-danger count">-</span>
									</small></p>
								</div>
								<div class="col-4 pl-0">
									<p><small>
										Endpoint Count:&nbsp;<span id="iseepgCount" class="text-danger count">-</span>
									</small></p>
								</div>
								<div class="col-2 pl-0">
									<p><small>
										<button class="btn btn-secondary shadow" id="getCount" type="button">Get Count</button>
									</small></p>
								</div>
							</div>
						</div>
					<div class="col-2"></div>
				</div>
				<div id="associationDetails" class="d-none row text-left">
					<div class="col-2"></div>
						<div class="col-8 mt-2 shadow mx-auto p-2 bg-white border border-primary">
							<h6>Association Details:</h6>	
							<div class="row">
								<div class="col">
									<div class="form-group">
										<label for="endpointDescription">Endpoint Description</label>
										<input type="text" class="form-control mt-2 mb-3 user-input shadow" value="" name="endpointDescription" id="endpointDescription" placeholder="Device Description">
									</div>
								</div>
							</div>
							<div class="row">
								<div class="col">
									<div class="form-group">
										<label for="fullName">Full Name</label>
										<input type="text" class="form-control mt-2 mb-3 user-input shadow form-validation" validation-state="required" value="" name="fullName" id="fullName" placeholder="John Smith">
										<div class="invalid-feedback">Please enter your Full Name</div>
									</div>
								</div>
							</div>
							<div class="row"> 
								<div class="col">
									<div class="form-group">
										<label for="emailAddress">Email address</label>
										<input type="email" class="form-control mt-2 mb-3 user-input shadow form-validation" validation-state="required" value="{$sessionData['emailAddress']}" name="emailAddress" placeholder="john@company.com">
										<div class="invalid-feedback">Please enter a valid email address</div>
									</div> 
								</div>
							</div>
							<div class="form-group text-center">
								<button class="btn btn-primary shadow" id="submitbtn" type="button">Import</button>
							</div>
						</div>
					<div class="col-2"></div>
				</div>
			</form>
		</div>
		<div class="m-0 mx-auto p-2 bg-white text-center">
			<p>Copyright &copy; 2019 Cisco and/or its affiliates.</p>
		</div>
		
	</div>

  </body>
  <script type="text/javascript" src="scripts/jquery-3.3.1.min.js"></script>
  <script type="text/javascript" src="scripts/ipsk-portal-v1.js"></script>
    <script type="text/javascript">
	
	var failure;
	
	$("#submitbtn").click(function() {
		event.preventDefault();
		
		failure = formFieldValidation();
		
		if(failure){
			return false;
		}
		$(this).prop("disabled", true);
		$("#bulkAssociationform").submit();
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
	
	$("#bulkImportType").change(function(){
		event.preventDefault();
		
		$("#iseBulkImport").addClass('d-none');
		$("#textBulkImport").addClass('d-none');
		$("#associationDetails").addClass('d-none');
		$( "#iseepgDescription" ).html( "-" );
		$( "#iseepgCount" ).html( "-" );
		
		if($(this).find('option:selected').val() == 33){
			
			$("#textBulkImport").removeClass('d-none');
			$("#associationDetails").removeClass('d-none');
			
		}else if($(this).find('option:selected').val() == 3){
		
			$.ajax({
				url: "query.php?portalId=$portalId",
				data: {
					'id': $(this).find('option:selected').val(),
					'action': 'get_endpoint_groups'
				},
				type: "POST",
				dataType: "json",
				success: function (epglist) {
					$("#iseEPGroups").find("option").remove(),
					$.each(epglist.SearchResult.resources, function(index, element) {
						temp = $('<option>', {value: element.id, description: element.description}),
						$("#iseEPGroups").append(temp.html(element.name));
					}),
					$("#iseBulkImport").removeClass('d-none');
					$("#associationDetails").removeClass('d-none');
					$("#iseEPGroups").trigger("change");
				}
			});
			
		}
	});
		
	$("#getCount").click(function(){
		event.preventDefault();

		$.ajax({
			url: "query.php?portalId=$portalId",
			data: {
				'groupUuid': $("#iseEPGroups").find('option:selected').val(),
				'action': 'get_endpoint_count'
			},
			type: "POST",
			dataType: "text",
			success: function (epCount) {
				$( "#iseepgCount" ).html( epCount );
			}
		});
	});	
	
	$("#iseEPGroups").change(function() {
		var description = "";
		$( "#iseEPGroups option:selected" ).each(function() {
			name = $(this).html();
			description = $(this).attr("description");
			$( "#iseepgDescription" ).html( description );
			$( "#endpointDescription" ).val( "[IMPORTED] ISE Endpoint Group: " + name );
			$( "#fullName" ).val( "[ISE-IMPORTED] Endpoint" );
			$( "#iseepgCount" ).html( "-" );
		});
	});
		
	$("#associationGroup").change(function() {
		var duration = "";
		var keyType = "";
		$( "#associationGroup option:selected" ).each(function() {
			duration = $(this).attr("data-term");
			keyType = $(this).attr("data-keytype");
			$( "#duration" ).html( duration );
			$( "#keyType" ).html( keyType );
		});
	});
	
	$("#associationGroup").trigger("change");
	</script>
</html>


HTML;

?>
