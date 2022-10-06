function openContractById(searchValue) {
    const server = jobRouterRestApiBaseUrl;
    const user = $JRUSER.userName;
    const resultlistId = 3;
    const filters = [ { name: "contract_id", type: "EXACT", value: searchValue } ];
    const requestObj = { username: user, viewer: "default", viewMode: "auto", downloadAllowed: 0, filters: filters };
    const toDo = (response) => { 
		const url = JSON.parse(response).jobarchiveresultlists[0][resultlistId].url;
		window.open(url, "_blank", "height=1000,width=800,modal=yes,alwaysRaised=yes"); 
	};

    getJobArchiveResultListUrl(server, user, resultlistId, requestObj, toDo);
}

function getJobArchiveResultListUrl (server, user, resultlistId, requestObj, toDo) {
	const requestBody = JSON.stringify(requestObj);
	const xhr = new XMLHttpRequest();
	const success = function () { if (this.readyState === this.DONE) { toDo(this.responseText); } };
		
	xhr.addEventListener("readystatechange", success);
	xhr.open("POST", server + "configuration/jobarchive/jobarchiveresultlists/" + resultlistId + "/integrationurl");
	xhr.setRequestHeader("Content-Type", "application/json");   
	xhr.send(requestBody);
}

openContractById(50);