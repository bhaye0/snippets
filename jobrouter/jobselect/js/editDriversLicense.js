function editDriversLicense(employeeId) {
	const serverUrl = 'https://cpjobrouter.cpbau.de';
	const resource = '/jobrouter/index.php?cmd=Step&jrprocessname=drivers_license&jrstep=310&employee_jrid=';

	const url = serverUrl + resource + employeeId;
	//console.log(url);
	let params = `status=no,location=no,toolbar=no,menubar=no,width=1200height=800`;
	window.open(url, 'DriversLicense', params);
}

