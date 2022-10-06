function openDriversLicense(docId) {
	const viewerMode = 'V';
	const resultListId = '0215e29b-62b8-4b64-9888-27e2bc15b3c6';
	const serverUrl = 'http://cpdw02';

	const url = buildDocuWareIntegrationUrlByDocId(serverUrl, resultListId, viewerMode, docId);
	//console.log(url);
	let params = `status=no,location=no,toolbar=no,menubar=no,width=900,height=1000,left=1000,top=-1000`;
	window.open(url, 'DriversLicense', params);
}

function buildDocuWareIntegrationUrlByDocId(serverUrl, resultListId, viewerMode, docId) {
	return `${serverUrl}/DocuWare/Platform/WebClient/1/Integration?p=${viewerMode}&rl=${resultListId}&did=${docId}`;
	return url;
}

function buildDocuWareIntegrationUrlByQuery(serverUrl, resultListId, viewerMode, rawQuery) {
	//const rawQuery = `[${col}]=${val}`;
    const b64Query = btoa(rawQuery);
	const query = (b64Query.indexOf('==') !== -1) ? b64Query.replace('==', '') + '2' : (b64Query.indexOf('=') !== -1) ? b64Query.replace('=', '') + '1' : b64Query += '0';
	return `${serverUrl}/DocuWare/Platform/WebClient/1/Integration?p=${viewerMode}&rl=${resultListId}&q=${query}`;
}
