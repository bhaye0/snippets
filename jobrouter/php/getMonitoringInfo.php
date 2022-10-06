<?php

require_once "includes/central.php";

class MonitoringInfo extends JobRouter\Engine\Runtime\PhpFunction\AbstractFunction
{
	public function getLanguage() {}
	
	public function buildStatusSummary() {
        $jobDB = $this->getJobDB();
        $numberOfErrors = 0;
        $status = [ 'info' => 0 ];

        $sql = "SELECT module_name, error_date, errormsg FROM jrmodulestatus WHERE errormsg <> '';";
        $status['modules'] = $this->getErrorQueryResult($jobDB, $sql);
        $status['info'] += count($status['module']);

        $sql = "SELECT processname, incident, step, errormsg, indate FROM jrincidents WHERE errormsg <> '' and status = -1;";
        $status['incidents'] = $this->getErrorQueryResult($jobDB, $sql);
        $status['info'] += count($status['incidents']);

        $sql = "SELECT mail_id, from_email, to_email, subject, emailtext, error_text, send_begin_date, indate FROM JRMAILLOG WHERE error_text <> '';";
        $status['mails'] = $this->getErrorQueryResult($jobDB, $sql);
        $status['info'] += count($status['mails']);

        $sql = "SELECT id, processname, import_date, errordesc, error_count FROM [jobrouter].[dbo].[JRJOBIMPORT] WHERE error_count > 0;";
        $status['imports'] = $this->getErrorQueryResult($jobDB, $sql);
        $status['info'] += count($status['imports']);

        return json_encode($status, JSON_PRETTY_PRINT);
	}

    private function getErrorQueryResult($jobDB, $sql) {
        $result = $jobDB->query($sql);
        return ($result === false) ? $jobDB->getErrorMessage() : $jobDB->fetchAll($result);
    }
}

$user = $_GET['usr'] = 'monitor';
$pwd = $_GET['pwd'];

//$hash = hash('sha512', $pwd);
//echo password_hash($hash, PASSWORD_DEFAULT);

$hash = '$2y$10$OfIWOT0mdZ/Gq3cDYUsr7.6y5wlQZ8V/bGFccC8AFvFFJ2SmrJTAS';

$verify = hash('sha512', $pwd);
$verify = password_verify($verify, $hash);

if ($user = 'monitor' && $verify) 
    echo (new MonitoringInfo(null))->buildStatusSummary();

?>