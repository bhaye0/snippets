function openOrderDelete(orderNumber) {
	const server = 'https://docuware01/jobrouter/index.php?cmd=Step&jrprocessname=order_management&jrstep=200&orderNumber=';
	const url = server + orderNumber;
	
	const height = 200;
	const width = 400;
	
	const left = Number((screen.width/2)-(width/2));
	const top = Number((screen.height/2)-(height/2));
	
	let params = `status=no,location=no,toolbar=no,menubar=no,resizable=no,scrollbar=no,titlebar=no,width=${width},height=${height},left=${left},top=${top}`;
	window.open(url, `Bestellung ${orderNumber} entfernen`, params);
}