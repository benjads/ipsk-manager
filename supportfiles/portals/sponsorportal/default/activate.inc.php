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
	
	if(!ipskLoginSessionCheck()){
		$portalId = $_GET['portalId'];
		$_SESSION = null;
		session_destroy();
		print "<script>window.location = \"/index.php?portalId=$portalId\";</script>";
		die();
	}

	$pageSize = (isset($_GET['pageSize'])) ? $_GET['pageSize'] : 25;
	$currentPage = (isset($_GET['currentPage'])) ? $_GET['currentPage'] : 1;
	
	$queryDetails = "pageSize=$pageSize&currentPage=$currentPage";

	if(is_numeric($sanitizedInput['id']) && $sanitizedInput['id'] != 0 && $sanitizedInput['confirmaction']){
		$endpointPermissions = $ipskISEDB->getEndPointAssociationPermissions($sanitizedInput['id'],$_SESSION['authorizationGroups'], $_SESSION['portalSettings']['id']);
		
		if($endpointPermissions){
			if($endpointPermissions[0]['advancedPermissions'] & 32){
				
				$endPointAssociation = $ipskISEDB->getEndPointAssociationById($sanitizedInput['id']);
				$ipskISEDB->activateEndpointAssociationbyId($endPointAssociation['endpointId']);

				//LOG::Entry
				$logData = $ipskISEDB->generateLogData(Array("sanitizedInput"=>$sanitizedInput));
				$logMessage = "REQUEST:SUCCESS;ACTION:SPONSORACTIVATE;METHOD:ACTIVATE-ENDPOINT;MAC:".$sanitizedInput['macAddress'].";REMOTE-IP:".$_SERVER['REMOTE_ADDR'].";USERNAME:".$_SESSION['logonUsername'].";SID:".$_SESSION['logonSID'].";";
				$ipskISEDB->addLogEntry($logMessage, __FILE__, __FUNCTION__, __CLASS__, __METHOD__, __LINE__, $logData);

				print <<<HTML
<script>
	window.location = "/manage.php?portalId=$portalId&$queryDetails";
</script>
HTML;
			}
		}
	}else{
		print <<<HTML
<!-- Modal -->
<div class="modal fade" id="endpointactivate" tabindex="-1" role="dialog" aria-labelledby="ModalCenterTitle" aria-hidden="true">
	<div class="modal-dialog modal-dialog-centered" role="document">
		<div class="modal-content">
			<div class="modal-header shadow alert alert-danger">
				<h5 class="modal-title font-weight-bold" id="modalLongTitle">Activate device's access?</h5>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close">
				  <span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<p class="h5">Are you sure you want to activate the device?</p>
			</div>
			<div class="modal-footer">
				<button type="button" module="endpoints" id="activateBtn" class="btn btn-danger font-weight-bold shadow">Yes</button>
				<button type="button" class="btn btn-secondary shadow" data-dismiss="modal">No</button>
			</div>
		</div>
	</div>
</div>
<script>
	$("#endpointactivate").modal({keyboard: false,backdrop: 'static',show: true});

	$("#activateBtn").click(function(){
		event.preventDefault();
		
		$('.modal-backdrop').remove();
		
		$.ajax({
			url: "/activate.php?portalId=$portalId&$queryDetails",
			
			data: {
				confirmaction: 1,
				id: '{$sanitizedInput['id']}'
			},
			type: "POST",
			dataType: "html",
			success: function (data) {
				$('#popupcontent').html(data);
			}
		});
		

	});

</script>
HTML;

	}
?>