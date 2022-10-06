<?php

require_once "includes/central.php";

class UrlBuilder extends JobRouter\Engine\Runtime\PhpFunction\AbstractFunction
{
	public function getLanguage() {}
	
	public function build($username, $searchValue) {
		$urlBuilder = $this->getJobArchiveResultListUrlBuilder(3);
		$urlBuilder->setUsername($username);
		$urlBuilder->setViewMode('auto');
        $urlBuilder->addExactFilter('contract_id', $searchValue);
        $url = $urlBuilder->getUrl();

        return $url;
	}
}

$data = json_decode(file_get_contents('php://input'), true);

echo (new UrlBuilder(null))->build($data['username'], $data['searchvalue']);

?>