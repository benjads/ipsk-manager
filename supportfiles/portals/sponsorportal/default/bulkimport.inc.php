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
	$pageData['hidePskFlag'] = "";
	$randomPassword = "";
	$validInput = false;
	$deviceRandom = false;
	
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
	
	if($sanitizedInput['associationGroup'] != 0 && $sanitizedInput['wirelessSSID'] != 0 && $sanitizedInput['bulkImportType'] != 0 && $sanitizedInput['emailAddress'] != "" && $sanitizedInput['fullName'] != "" && $sanitizedInput['groupUuid'] != "") {	
		$validInput = true;
	}elseif($sanitizedInput['associationGroup'] != 0 && $sanitizedInput['wirelessSSID'] != 0 && $sanitizedInput['bulkImportType'] != 0 && $sanitizedInput['uploadkey'] != ""){
		$validInput = true;
	}
	
	$userEPCount = $ipskISEDB->getUserEndpointCount($sanitizedInput['associationGroup'], $_SESSION['logonSID']);
	
	for($count = 0; $count < $_SESSION['authorizedEPGroups']['count']; $count++) {
		if($_SESSION['authorizedEPGroups'][$count]['endpointGroupId'] == $sanitizedInput['associationGroup']){
			$epGroupMax = $_SESSION['authorizedEPGroups'][$count]['maxDevices'];
		}
	}
	
	if($epGroupMax != 0){
		if($userEPCount > $epGroupMax){
			$validInput = false;
		}
	}
	
	if($validInput){
		$endpointGroupAuthorization = $ipskISEDB->getAuthorizationTemplatesbyEPGroupId($sanitizedInput['associationGroup']);
		
		if($endpointGroupAuthorization['ciscoAVPairPSK'] == "*devicerandom*"){
			$randomPassword = $ipskISEDB->generateRandomPassword($endpointGroupAuthorization['pskLength']);
			$deviceRandom = true;
			
		}elseif($endpointGroupAuthorization['ciscoAVPairPSK'] == "*userrandom*"){
			$userPsk = $ipskISEDB->getUserPreSharedKey($sanitizedInput['associationGroup'],$_SESSION['logonSID']);
			if(!$userPsk){
				$randomPassword = $ipskISEDB->generateRandomPassword($endpointGroupAuthorization['pskLength']);
				$randomPSKList = "psk=".$randomPassword;
			}else{
				$randomPassword = $userPsk;
				$randomPSKList = "psk=".$randomPassword;
			}
		}else{
			$randomPassword = $endpointGroupAuthorization['ciscoAVPairPSK'];
			$randomPSKList = "psk=".$randomPassword;
		}
		
		if($endpointGroupAuthorization['termLengthSeconds'] == 0){
			$duration = $endpointGroupAuthorization['termLengthSeconds'];
		}else{
			$duration = time() + $endpointGroupAuthorization['termLengthSeconds'];
		}
		
		if($sanitizedInput['bulkImportType'] == 1){
			$macaddressArray = $_SESSION['bulk-import'][$sanitizedInput['uploadkey']];
			
			unset($_SESSION['bulk-import'][$sanitizedInput['uploadkey']]);
			
			if($macaddressArray){
				if($macaddressArray['count'] > 0){
					for($entryIdx = 0; $entryIdx < $macaddressArray['count']; $entryIdx++){
						$macAddressList[$entryIdx] = $macaddressArray[$entryIdx]['macAddress'];
						$fullnameList[$entryIdx] = $macaddressArray[$entryIdx]['fullname'];
						$emailaddressList[$entryIdx] = $macaddressArray[$entryIdx]['emailaddress'];
						$descriptionList[$entryIdx] = $macaddressArray[$entryIdx]['description'];
					
						if($deviceRandom){
							$randomPassword = $ipskISEDB->generateRandomPassword($endpointGroupAuthorization['pskLength']);
							$deviceRandomPSK = "psk=".$randomPassword;
							$randomPSKList[$entryIdx] = $deviceRandomPSK;
						}
					}
				}
			}
		}elseif($sanitizedInput['bulkImportType'] == 3){
			$macaddressArray = json_decode($ipskISEERS->getEndPointsByEPGroup($sanitizedInput['groupUuid']), true);
			
			$count = 0;
			
			if($macaddressArray['SearchResult']['total'] > 0){
				foreach($macaddressArray['SearchResult']['resources'] as $entry){
					$macAddressList[$count] = $entry['name'];
					$count++;
				}
			}
		}
		
		if($sanitizedInput['bulkImportType'] == 1 && $macAddressList){
			$macAddressInsertID = $ipskISEDB->addBulkEndpoints($macAddressList, $fullnameList, $descriptionList, $emailaddressList, $randomPSKList, $duration, $_SESSION['logonSID']);
		}elseif($sanitizedInput['bulkImportType'] == 3 && $macAddressList){
			$macAddressInsertID = $ipskISEDB->addBulkEndpoints($macAddressList,$sanitizedInput['fullName'], $sanitizedInput['endpointDescription'], $sanitizedInput['emailAddress'], $randomPSKList, $duration, $_SESSION['logonSID']);
		}
		
		if($macAddressInsertID){
			if($macAddressInsertID['processed'] > 0){
				//LOG::Entry
				$logData = $ipskISEDB->generateLogData(Array("sanitizedInput"=>$sanitizedInput), Array("macAddressList"=>$macAddressList));
				$logMessage = "BULKREQUEST:SUCCESS;ACTION:SPONSORCREATE;METHOD:ADD-ENDPOINT;MAC:".$sanitizedInput['macAddress'].";REMOTE-IP:".$_SERVER['REMOTE_ADDR'].";USERNAME:".$_SESSION['logonUsername'].";SID:".$_SESSION['logonSID'].";";
				$ipskISEDB->addLogEntry($logMessage, __FILE__, __FUNCTION__, __CLASS__, __METHOD__, __LINE__, $logData);
					
					
				if($ipskISEDB->addBulkEndpointAssociation($macAddressInsertID, $sanitizedInput['associationGroup'], $_SESSION['logonSID'])){
					//LOG::Entry
					$logData = $ipskISEDB->generateLogData(Array("sanitizedInput"=>$sanitizedInput), Array("macAddressList"=>$macAddressList));
					$logMessage = "BULKREQUEST:SUCCESS;ACTION:SPONSORCREATE;METHOD:ADD-ENDPOINT-ASSOCIATION;MAC:".$sanitizedInput['macAddress'].";REMOTE-IP:".$_SERVER['REMOTE_ADDR'].";USERNAME:".$_SESSION['logonUsername'].";SID:".$_SESSION['logonSID'].";";
					$ipskISEDB->addLogEntry($logMessage, __FILE__, __FUNCTION__, __CLASS__, __METHOD__, __LINE__, $logData);
					
					$pageData['createComplete'] .= "<div class=\"row\"><div class=\"col\"><h3>The Endpoint Import has completed successfully.</h3><h6></h6></div></div>";
					
					if(is_array($macAddressInsertID)){
						$insertAssociation = "";
						
						for($rowCount = 0; $rowCount < $macAddressInsertID['count']; $rowCount++){
							
							if($macAddressInsertID[$rowCount]['exists'] == true){
								$insertAssociation .= '<tr><td><div><span style="color: #ff0000" data-feather="x-circle"></span>'.$macAddressInsertID[$rowCount]['macAddress'].'</div></td><td><span class="text-danger">Endpoint Exists</span></td></tr>';
							}else{
								$insertAssociation .= '<tr><td><div><span style="color: #2d8c32" data-feather="check-circle"></span>'.$macAddressInsertID[$rowCount]['macAddress'].'</div></td><td>'.str_replace("psk=","", $macAddressInsertID[$rowCount]['psk']).'</td></tr>';
							}
						}
					}
	  
					$pageData['createComplete'] .= "<table class=\"table table-hover\"><thead><tr><th scope=\"col\">MAC Address</th><th scope=\"col\">Pre-Shared Key</th></tr></thead><tbody>$insertAssociation</tbody></table>";
					$randomPassword = "";
					$pageData['hidePskFlag'] = " d-none";
				}else{
					//LOG::Entry
					$logData = $ipskISEDB->generateLogData(Array("sanitizedInput"=>$sanitizedInput), Array("macAddressList"=>$macAddressList));
					$logMessage = "BULKREQUEST:FAILURE[unable_to_create_endpoint_association];ACTION:SPONSORCREATE;MAC:".$sanitizedInput['macAddress'].";REMOTE-IP:".$_SERVER['REMOTE_ADDR'].";USERNAME:".$_SESSION['logonUsername'].";SID:".$_SESSION['logonSID'].";";
					$ipskISEDB->addLogEntry($logMessage, __FILE__, __FUNCTION__, __CLASS__, __METHOD__, __LINE__, $logData);
					
					$pageData['createComplete'] .= "<div class=\"row\"><div class=\"col\"><h3>The Endpoint Import has failed.</h3><br><h5 class=\"text-danger\">(Error message: Unable to create associations for endpoints)</h5></div></div>";
					
					if(is_array($macAddressInsertID)){
						$insertAssociation = "";
						
						for($rowCount = 0; $rowCount < $macAddressInsertID['count']; $rowCount++){
							
							if($macAddressInsertID[$rowCount]['exists'] == true){
								$insertAssociation .= '<tr><td><div><span style="color: #ff0000" data-feather="x-circle"></span>'.$macAddressInsertID[$rowCount]['macAddress'].'</div></td><td><span class="text-danger">Endpoint Exists</span></td></tr>';
							}else{
								$insertAssociation .= '<tr><td><div><span style="color: #2d8c32" data-feather="check-circle"></span>'.$macAddressInsertID[$rowCount]['macAddress'].'</div></td><td>'.str_replace("psk=","", $macAddressInsertID[$rowCount]['psk']).'</td></tr>';
							}
						}
					}
	  
					$pageData['createComplete'] .= "<table class=\"table table-hover\"><thead><tr><th scope=\"col\">MAC Address</th><th scope=\"col\">Pre-Shared Key</th></tr></thead><tbody>$insertAssociation</tbody></table>";
					$randomPassword = "";
					$pageData['hidePskFlag'] = " d-none";
				}
			}else{
				//LOG::Entry
				$logData = $ipskISEDB->generateLogData(Array("sanitizedInput"=>$sanitizedInput), Array("macAddressList"=>$macAddressList));
				$logMessage = "BULKREQUEST:FAILURE[endpoints_exists];ACTION:SPONSORCREATE;MAC:".$sanitizedInput['macAddress'].";REMOTE-IP:".$_SERVER['REMOTE_ADDR'].";USERNAME:".$_SESSION['logonUsername'].";SID:".$_SESSION['logonSID'].";";
				$ipskISEDB->addLogEntry($logMessage, __FILE__, __FUNCTION__, __CLASS__, __METHOD__, __LINE__, $logData);
				
				$pageData['createComplete'] .= "<div class=\"row\"><div class=\"col\"><h3>The Endpoint Import has failed.</h3><h6 class=\"text-danger\">(Error message: Endpoints already exist)</h6></div></div>";
					
				if(is_array($macAddressInsertID)){
					$insertAssociation = "";
					
					for($rowCount = 0; $rowCount < $macAddressInsertID['count']; $rowCount++){
						
						if($macAddressInsertID[$rowCount]['exists'] == true){
							$insertAssociation .= '<tr><td><div><span style="color: #ff0000" data-feather="x-circle"></span>'.$macAddressInsertID[$rowCount]['macAddress'].'</div></td><td><span class="text-danger">Endpoint Exists</span></td></tr>';
						}else{
							$insertAssociation .= '<tr><td><div><span style="color: #2d8c32" data-feather="check-circle"></span>'.$macAddressInsertID[$rowCount]['macAddress'].'</div></td><td>'.str_replace("psk=","", $macAddressInsertID[$rowCount]['psk']).'</td></tr>';
						}
					}
				}
  
				$pageData['createComplete'] .= "<table class=\"table table-hover\"><thead><tr><th scope=\"col\">MAC Address</th><th scope=\"col\">Pre-Shared Key</th></tr></thead><tbody>$insertAssociation</tbody></table>";
				$randomPassword = "";
				$pageData['hidePskFlag'] = " d-none";
			}
		}else{
			//LOG::Entry
			$logData = $ipskISEDB->generateLogData(Array("sanitizedInput"=>$sanitizedInput), Array("macAddressList"=>$macAddressList));
			$logMessage = "BULKREQUEST:FAILURE[unable_to_create_endpoint];ACTION:SPONSORCREATE;MAC:".$sanitizedInput['macAddress'].";REMOTE-IP:".$_SERVER['REMOTE_ADDR'].";USERNAME:".$_SESSION['logonUsername'].";SID:".$_SESSION['logonSID'].";";
			$ipskISEDB->addLogEntry($logMessage, __FILE__, __FUNCTION__, __CLASS__, __METHOD__, __LINE__, $logData);
			
			$pageData['createComplete'] .= "<div class=\"row\"><div class=\"col\"><h3>The Endpoint Association has failed, please contact a support technician for assistance.</h3><h5 class=\"text-danger\">(Error message: Unable to create endpoint)</h5><hr>";

			$randomPassword = "";
			$pageData['hidePskFlag'] = " d-none";
		}
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
	
	print <<< HTML
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
					<div class="col-2"></div>
					<div class="col-8 mb-3 mx-auto shadow p-2 bg-white border border-primary">
						<div class="row m-auto text-left">
							{$pageData['createComplete']}
						</div>
						<div class="row">
							<div class="col{$pageData['hidePskFlag']}">
								<div class="input-group input-group-sm mb-3 shadow copied-popover" data-animation="true" data-container="body" data-trigger="manual" data-toggle="popover" data-placement="top" data-content="Pre Shared Key has been Copied!">
									<div class="input-group-prepend">
										<span class="input-group-text font-weight-bold shadow" id="basic-addon1">Pre-Shared Key</span>
									</div>
									<input type="text" id="presharedKey" class="form-control shadow" process-value="$randomPassword" value="$randomPassword" aria-label="password" aria-describedby="basic-addon1" data-lpignore="true" readonly>
									<div class="input-group-append">
										<span class="input-group-text font-weight-bold shadow" id="basic-addon1"><a id="copyPassword" href="#" data-clipboard-target="#presharedKey"><span id="passwordfeather" data-feather="copy"></span></a></span>
									</div>
								</div>
								Click on the copy button to copy the Pre Shared Key to your Clipboard.
							</div>
						</div>
						<div class="row">
							<div class="col text-center">
								<button id="newbulkAssoc" class="btn btn-primary shadow" type="button">Import Again</button>
							</div>
						</div>
					</div>
					<div class="col-2"></div>
				</div>

			<form action="login.php" method="post" class="form-signin">
			</form>
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

  </body>
  <script type="text/javascript" src="scripts/jquery.min.js"></script>
  <script type="text/javascript" src="scripts/feather.min.js"></script>
  <script type="text/javascript" src="scripts/popper.min.js"></script>
  <script type="text/javascript" src="scripts/bootstrap.min.js"></script>
  <script type="text/javascript" src="scripts/clipboard.min.js"></script>
  <script type="text/javascript" src="scripts/ipsk-portal-v1.js"></script>
  <script type="text/javascript">
	
	$(function() {	
		feather.replace()
	});
	
	var clipboard = new ClipboardJS('#copyPassword');

	clipboard.on('success', function(e) {
		$('.copied-popover').popover('show');
		$('#presharedKey').addClass('is-valid');
		notificationTimer = setInterval("clearNotification()", 7000);

		e.clearSelection();
	});
	
	function clearNotification(){
		$('.copied-popover').popover('hide');
		$('#presharedKey').removeClass('is-valid');
		clearInterval(notificationTimer);
	}
	
	$("#submitbtn").click(function() {
		$("#associationform").submit();
	});
	
	$("#createAssoc").click(function() {
		window.location.href = "/sponsor.php?portalId=$portalId";
	});
	
	$("#bulkAssoc").click(function() {
		window.location.href = "/bulk.php?portalId=$portalId";
	});
	
	$("#newbulkAssoc").click(function() {
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
	</script>
</html>
HTML;

?>