<?php

class className extends JobRouter\Engine\Runtime\PhpFunction\RuleExecutionFunction
{
	public function execute($rowId = null) {
	    $type = (empty($this->getTableValue('invoice_id_int'))) ? "insert" : "update";
	    $data = $this->getAccountingData();
        $data['accountingText'] = ($type === "insert") ? $this->getProcessId() : $this->getTableValue('buchungstext');
	    
        $xmlContent = $this->buildBausuXmlContent($data, $type);
        
        $processId = $this->getProcessId();

        $bausuXmlFilePath = "\\\\srv-file\\c$\\Bau-SU\\XML-Server\\00DW_JR\\Quelle\\";
        $this->createXmlFile($bausuXmlFilePath, $xmlContent, $processId);
        
        $bausuXmlFilePathCopy = "c:/jobrouter/bausuxml_kopie/";
        $this->createXmlFile($bausuXmlFilePathCopy, $xmlContent, $processId);
	}
	
	private function getAccountingData() {
        return array(
            'bausuId' => $this->getTableValue('bausu_id'),
            'vendorId' => str_pad($this->getTableValue('vendor_id'), 8, "0", STR_PAD_LEFT), //lieferant_id
            'invoiceDate' => $this->getTableValue('belegdatum'), // belegdatum
            'invoiceNoInt' => $this->getTableValue('invoice_id_int'),
            'invoiceNoExt' => $this->getTableValue('extbelegnr'), // extbelegnr
            'costCentre' => $this->getTableValue('kost'),
            'accountImpersonal' => str_pad($this->getTableValue('sachkonto'), 8, "0", STR_PAD_LEFT),
            'taxKey' => $this->getTableValue('steuerschluessel'),
            'amountTax' => $this->getTableValue('steuer'), // steuer
            'amountGross' => $this->getTableValue('brutto'), // brutto
            'amountDiscountable' =>  $this->getTableValue('skontierbetrag'),
            'amountSecurityService' => "",
            'valutaDate' => date('Y-m-d', $this->getStartDate()),
            'discountId' => $this->getTableValue('skonto_staffel'),
            'accountingPeriod' => $this->getTableValue('buchungsperiode'),
            'accountingText' => "",
            'verificationStatus' => $this->getTableValue('verification_status'),
            'verificationDate' => $this->getTableValue('verification_date'),
            'verificationUser' => $this->getTableValue('verification_user')
        );
    }

    private function createXmlFile($xmlFilePath, $xmlContent, $fileName) {
        $xmlFilePath = $xmlFilePath . $fileName . '.xml';
        $xmlFile = fopen($xmlFilePath, "w");
        $xmlFileResult = fwrite($xmlFile, $xmlContent, 5000);
        fclose($xmlFile);
        //error_log("$xmlFilePath -> $xmlFileResult \bytes written to file");
        
        if (!$xmlFileResult) throw new JobRouterException("bausu file $xmlFilePath could not be written.");
        
    }

    private function buildBausuXmlContent($data, $type) {
        $typeValue = ($type === "insert") ? "insertonly" : "updateonly";
        $updateIdentifier = ($type === "insert") ? "" : "<ID>{$data['bausuId']}</ID>";
        
        return 
"<?xml version=\"1.0\" encoding=\"ISO-8859-1\"?>
<bausuappserver>
    <description>
        <type>5</type>
    </description>
    <obj_ERKONTROLLE action=\"$typeValue\">
        $updateIdentifier
        <BELEG>{$data['invoiceNoInt']}</BELEG>
        <HAUPTKONTO>{$data['vendorId']}</HAUPTKONTO>
        <BRUTTO>{$data['amountGross']}</BRUTTO>
        <BELEGDATUM>{$data['invoiceDate']}</BELEGDATUM>
        <EXTBELEGNR>{$data['invoiceNoExt']}</EXTBELEGNR>
        <SKSTAFFEL>{$data['discountId']}</SKSTAFFEL>
        <SKFBETRAG>{$data['amountDiscountable']}</SKFBETRAG>
        <GEGENKONTO>{$data['accountImpersonal']}</GEGENKONTO>
        <SICHERHBETRAG>{$data['amountSecurityService']}</SICHERHBETRAG>
        <KOST>{$data['costCentre']}</KOST>
        <PERIODE>{$data['accountingPeriod']}</PERIODE>
        <STEUERSATZID>{$data['taxKey']}</STEUERSATZID>
        <STEUERBETRAG>{$data['amountTax']}</STEUERBETRAG>
        <TEXT>{$data['accountingText']}</TEXT>
        <ERFDATUM>{$data['valutaDate']}</ERFDATUM>
        <STATUS>{$data['verificationStatus']}</STATUS>
        <DATUMABGEZ>{$data['verificationDate']}</DATUMABGEZ>
        <ABGEZEICHNET>{$data['verificationUser']}</ABGEZEICHNET>
    </obj_ERKONTROLLE>
</bausuappserver>";
    }
}
?>