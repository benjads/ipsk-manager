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
	$pageData['endpointAssociationList'] = "";
	$pageData['hidePskFlag'] = "";
	$randomPassword = "";
	$wifiSsid = '';
	
	if(!ipskLoginSessionCheck()){
		$portalId = $_GET['portalId'];
		$_SESSION = null;
		session_destroy();
		header("Location: /index.php?portalId=".$portalId);
		die();
	}
	
	if($_SESSION['portalAuthorization']['create'] == false){
		header("Location: /manage.php?portalId=".$portalId);
		die();
	}
	
	$userEPCount = $ipskISEDB->getUserEndpointCount($sanitizedInput['associationGroup'], $_SESSION['logonSID']);
	
	for($count = 0; $count < $_SESSION['authorizedEPGroups']['count']; $count++) {
		if($_SESSION['authorizedEPGroups'][$count]['endpointGroupId'] == $sanitizedInput['associationGroup']){
			$epGroupMax = $_SESSION['authorizedEPGroups'][$count]['maxDevices'];
		}
	}
	
	if($userEPCount < $epGroupMax || $epGroupMax == 0){
		
		$smtpSettings = $ipskISEDB->getSmtpSettings();
		
		if(isset($sanitizedInput['associationGroup']) && isset($sanitizedInput['macAddress']) && isset($sanitizedInput['endpointDescription']) && isset($sanitizedInput['emailAddress']) && isset($sanitizedInput['fullName'])) {	
			$endpointGroupAuthorization = $ipskISEDB->getAuthorizationTemplatesbyEPGroupId($sanitizedInput['associationGroup']);
			
			if($endpointGroupAuthorization['ciscoAVPairPSK'] == "*devicerandom*"){
				$randomPassword = $ipskISEDB->generateRandomPassword($endpointGroupAuthorization['pskLength']);
				$randomPSK = "psk=".$randomPassword;
			}elseif($endpointGroupAuthorization['ciscoAVPairPSK'] == "*userrandom*"){
				$userPsk = $ipskISEDB->getUserPreSharedKey($sanitizedInput['associationGroup'],$_SESSION['logonSID']);
				if(!$userPsk){
					$randomPassword = $ipskISEDB->generateRandomPassword($endpointGroupAuthorization['pskLength']);
					$randomPSK = "psk=".$randomPassword;
				}else{
					$randomPassword = $userPsk;
					$randomPSK = "psk=".$randomPassword;
				}
			}else{
				$randomPassword = $endpointGroupAuthorization['ciscoAVPairPSK'];
				$randomPSK = "psk=".$randomPassword;
			}
			
			if($endpointGroupAuthorization['termLengthSeconds'] == 0){
				$duration = $endpointGroupAuthorization['termLengthSeconds'];
			}else{
				$duration = time() + $endpointGroupAuthorization['termLengthSeconds'];
			}
			
			$wirelessNetwork = $ipskISEDB->getWirelessNetworkById($sanitizedInput['wirelessSSID']);
			
			if($wirelessNetwork){
				$wifiSsid = $wirelessNetwork['ssidName'];
			}

            if($_SESSION['portalAuthorization']['attribute']){
               $fullName = $sanitizedInput['fullName'];
               $emailAddress = $sanitizedInput['emailAddress'];
            } else {
                $fullName = $_SESSION['fullName'];
                $emailAddress = $_SESSION['emailAddress'];
            }

            $endpointId = false;
            $duplicate = $ipskISEDB->getEndpointByMacAddress($sanitizedInput['macAddress']);
            if (!$duplicate) {
                $endpointId = $ipskISEDB->addEndpoint($sanitizedInput['macAddress'], $fullName, $sanitizedInput['endpointDescription'], $emailAddress, $randomPSK, $duration, $_SESSION['logonSID']);
            }

			if($endpointId){
				//LOG::Entry
				$logData = $ipskISEDB->generateLogData(Array("sanitizedInput"=>$sanitizedInput));
				$logMessage = "REQUEST:SUCCESS;ACTION:SPONSORCREATE;METHOD:ADD-ENDPOINT;MAC:".$sanitizedInput['macAddress'].";REMOTE-IP:".$_SERVER['REMOTE_ADDR'].";USERNAME:".$_SESSION['logonUsername'].";SID:".$_SESSION['logonSID'].";";
				$ipskISEDB->addLogEntry($logMessage, __FILE__, __FUNCTION__, __CLASS__, __METHOD__, __LINE__, $logData);
					
					
				if($ipskISEDB->addEndpointAssociation($endpointId, $sanitizedInput['macAddress'], $sanitizedInput['associationGroup'], $_SESSION['logonSID'])){
					//LOG::Entry
					$logData = $ipskISEDB->generateLogData(Array("sanitizedInput"=>$sanitizedInput));
					$logMessage = "REQUEST:SUCCESS;ACTION:SPONSORCREATE;METHOD:ADD-ENDPOINT-ASSOCIATION;MAC:".$sanitizedInput['macAddress'].";REMOTE-IP:".$_SERVER['REMOTE_ADDR'].";USERNAME:".$_SESSION['logonUsername'].";SID:".$_SESSION['logonSID'].";";
					$ipskISEDB->addLogEntry($logMessage, __FILE__, __FUNCTION__, __CLASS__, __METHOD__, __LINE__, $logData);
					
					if($ipskISEDB->emailEndpointGroup($sanitizedInput['associationGroup'])){
						sendHTMLEmail($sanitizedInput['emailAddress'], $portalSettings['portalName'], $randomPassword, $wifiSsid, $sanitizedInput['macAddress'], $endpointGroupAuthorization['groupName'], $sanitizedInput['endpointDescription'], $sanitizedInput['fullName'], $_SESSION['fullName'], $smtpSettings);
						/*
						 *Second Method to Send Email.  (Plain Text)
						 *
						 *sendEmail($sanitizedInput['emailAddress'],"iPSK Wi-Fi Credentials","You have been successfully setup to connect to the Wi-Fi Network, please use the following Passcode:".$randomPassword."\n\nThank you!",$smtpSettings);
						 */
					}
					$pageData['createComplete'] .= "<h3>The enrollment has successfully completed.</h3><h6>The uniquely generated password for the device is:</h6>";
				}else{
					//LOG::Entry
					$logData = $ipskISEDB->generateLogData(Array("sanitizedInput"=>$sanitizedInput));
					$logMessage = "REQUEST:FAILURE[unable_to_create_endpoint_association];ACTION:SPONSORCREATE;MAC:".$sanitizedInput['macAddress'].";REMOTE-IP:".$_SERVER['REMOTE_ADDR'].";USERNAME:".$_SESSION['logonUsername'].";SID:".$_SESSION['logonSID'].";";
					$ipskISEDB->addLogEntry($logMessage, __FILE__, __FUNCTION__, __CLASS__, __METHOD__, __LINE__, $logData);
					
					$pageData['createComplete'] .= "<h3>The enrollment has failed, please contact a support technician for assistance.</h3><h5 class=\"text-danger\">(Error message: Unable to create endpoint association)</h5>";
					$randomPassword = "";
					$pageData['hidePskFlag'] = " d-none";
				}
			}else{
				//LOG::Entry
				$logData = $ipskISEDB->generateLogData(Array("sanitizedInput"=>$sanitizedInput));
				$logMessage = "REQUEST:FAILURE[unable_to_create_endpoint];ACTION:SPONSORCREATE;MAC:".$sanitizedInput['macAddress'].";REMOTE-IP:".$_SERVER['REMOTE_ADDR'].";USERNAME:".$_SESSION['logonUsername'].";SID:".$_SESSION['logonSID'].";";
				$ipskISEDB->addLogEntry($logMessage, __FILE__, __FUNCTION__, __CLASS__, __METHOD__, __LINE__, $logData);

                if ($duplicate) {
                    $pageData['createComplete'] .= "<h3>The device has already been enrolled. If this is a mistake, please contact a support technician for assistance.</h3><h5 class=\"text-danger\">(Error message: duplicate enrollment)</h5>";
                } else {
                    $pageData['createComplete'] .= "<h3>The enrollment has failed, please contact a support technician for assistance.</h3><h5 class=\"text-danger\">(Error message: Unable to create endpoint)</h5>";
                }
				$randomPassword = "";
				$pageData['hidePskFlag'] = " d-none";
			}
		}
	}

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
										<span class="input-group-text font-weight-bold shadow" id="basic-addon1">Password</span>
									</div>
									<input type="text" id="presharedKey" class="form-control shadow" process-value="$randomPassword" value="$randomPassword" aria-label="password" aria-describedby="basic-addon1" data-lpignore="true" readonly>
									<div class="input-group-append">
										<span class="input-group-text font-weight-bold shadow" id="basic-addon1"><a id="copyPassword" href="#" data-clipboard-target="#presharedKey"><span id="passwordfeather" data-feather="copy"></span></a></span>
									</div>
								</div>
								Click on the copy button to copy the password to your clipboard.
							</div>
						</div>
						<div class="row">
							<div class="col text-center">
								<button id="newAssoc" class="btn btn-primary shadow" type="button">Create New</button>
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
		window.location.href = "/sponsor.php?portalId=$portalId&eg={$sanitizedInput['associationGroup']}";
	});
	
	$("#bulkAssoc").click(function() {
		window.location.href = "/bulk.php?portalId=$portalId";
	});
	
	$("#newAssoc").click(function() {
		window.location.href = "/sponsor.php?portalId=$portalId&eg={$sanitizedInput['associationGroup']}";
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