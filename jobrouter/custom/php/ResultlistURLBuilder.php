<?php

require_once "../../includes/central.php";

class ResultlistUrlBuilder extends JobRouter\Engine\Runtime\PhpFunction\AbstractFunction
{
	public function getLanguage() {}
	
	public function build($data) {
		$urlBuilder = $this->getJobArchiveResultListUrlBuilder($data['resultlistId']);
		//$urlBuilder->setUsername($username);
		$urlBuilder->setViewMode('auto');
        $urlBuilder->addExactFilter('documentrevision_id', $data['docId']);
        return $urlBuilder->getUrl();
	}
}

$data = json_decode(file_get_contents('php://input'), true);

echo (new ResultlistUrlBuilder(null))->build($data);

?>