<?php
// utils_php build 0116

declare(strict_types=1);
set_time_limit(30);
//error_reporting(-1);

class JRUtilities {   
    public $u, $src, $logger, $dbMap;
    private $dateUtils;

    public function __construct($source) { global $u, $src, $dbMap;
        $u = $this;
        $src = $source;
        $dbMap = $this->loadDatabaseMap();

        //MISC::getObjectDetails($u);
        //MISC::getObjectDetails($src);
    }

    private function loadDatabaseMap() { global $u, $src;
        $processName = $src->getProcessName();

        $sql = "
            SELECT subtable, field_name as field, field_type as type FROM jrsubtablefields WHERE processname = '$processName'
            UNION
            SELECT 'pt' as subtable, field_name as field, field_type as type FROM jrtablefields WHERE processname = '$processName'
        ";

        return $u->query($sql);
    }

    public function log($level, $msg, $type = 'str') { global $logger;
	    if (!isset($logger)) $this->initLogger("DEBUG", "FILE");
	    $logger->log($level, $msg, $type); 
	}

	public function resetlogFile() { global $logger;
        $logger->createNewLogFile(); 
    }

    public function initLogger($logLevel, $logChannel) { global $u, $src, $logger;
        $logger = new JRLogger($u, $src, $logChannel, $logLevel); 
    }

    public function getValue($target) {
        if (strpos($target, '.') !== false) {
            $targetAry = explode('.', $target);
            $type = $this->getTargetType($targetAry[0], $targetAry[1]);

            $result = ($targetAry[2] === "*")
                ? $this->getIncidentArray($target, $targetAry, $type)
                : $this->getIncidentValue($target, $targetAry, $type);
        }
        else {
            $type = $this->getTargetType('pt', $target);
            $result = $this->getIncidentValue($target, null, $type);
        }
        
        return $result;
    }

    private function getTargetType($table, $field) { global $dbMap;
        foreach ($dbMap as $item) {
            if ($item['subtable'] === $table && $item['field'] === $field) return $item['type'];
        }

        throw new Exception("Target '$table.$field' does not exist.");
    }

    private function getIncidentValue($target, $targetAry, $type) { global $src;
        $value = (isset($targetAry))
            ? $src->getSubtableValue($targetAry[0], intval($targetAry[2]), $targetAry[1], true)
            : $src->getTableValue($target, true);
        
        if (empty($value)) return null;

        switch($type) {
            case 'varchar': $typedValue = $value; break;
            case 'int': $typedValue = $this->toInt($value); break;
            case 'decimal': $typedValue = $this->toFloat($value); break;
            case 'datetime': $typedValue = $this->toDate($value); break;
            case 'file': $typedValue = $value; break;
            default: throw new Exception("unknown type '$type' at '$target");
        }

        return $typedValue;
    }

    private function getIncidentArray($target, $targetAry, $type) { global $src;
        $subtableRowIds = $src->getSubtableRowIds($target[0]);
        $result = array();

        foreach ($subtableRowIds as $row) {
            $targetAry[2] = $row;
            $value = $this->getIncidentValue($target, $targetAry, $type);
            array_push($result, $value);
        }

        return $result;
    }
    
    public function setValue($target, $value) { 
        if (strpos($target, '.') !== false) {
            $targetAry = explode('.', $target);

            if ($targetAry[2] === "*") $this->setIncidentArray($targetAry, $value);
            else $this->setIncidentValue($targetAry, $value);
        }
        else $this->setIncidentValue($target, $value);
    }
    
    private function setIncidentValue($target, $value) { global $src;
        if (is_array($target)) $src->setSubtableValue($target[0], intval($target[2]), $target[1], $value);
        else $src->setTableValue($target, $value);
    }

    private function setIncidentArray($target, $value) { global $src;
        $subtableRowIds = $src->getSubtableRowIds($target[0]);

        foreach ($subtableRowIds as $row) {
            $target[2] = $row;
            $this->setIncidentValue($target, $value);
        }
    }

    public function insertStRow($subtable, $rowData, $prepend) { global $src;
        $rowId = ($prepend) ? 1: intval($src->getSubtableCount($subtable)) + 1;
        $src->insertSubtableRow($subtable, $rowId, $rowData);
    }

    public function filterArray($srcArray, $filters, $distinct) {
        $tarArray = array();
        $values = array();

        if ($filters === null) return $srcArray;

        foreach ($srcArray as $key => $value) {
            if (in_array($key, $filters) || ($distinct && in_array($value, $values))) continue;
            $tarArray[$key ] = $value;
            array_push($values, $value);
        }
        
        return $tarArray;
    }
	
	public function toAmount($numRaw, $toStr = false, $separator = '.') {
	    $num = strval($numRaw);
	    $isNegative = (strpos($num, "-") === 0) ? true : false;
	    
        $dotPos = strrpos($num, '.');
        $commaPos = strrpos($num, ',');
        $sep = (($dotPos > $commaPos) && $dotPos) ? $dotPos :
            ((($commaPos > $dotPos) && $commaPos) ? $commaPos : false);
      
        $result = (!$sep) ? 
            preg_replace("/[^0-9]/", "", $num) :
            preg_replace("/[^0-9]/", "", substr($num, 0, $sep)) . $separator .preg_replace("/[^0-9]/", "", substr($num, $sep+1, strlen($num)));
        
        if ($isNegative) $result = "-" . $result;
        
        return ($toStr) ? $result : floatval($result);
    }
    
    public function getType($value, $returnClass = true) { 
        if (isset($value)) {
            $baseType = gettype($value);
            $resultType = ($baseType === 'object' && $returnClass) ? get_class($value) : $baseType;
            $resultTypeNormalized = strtolower(trim($resultType));
        }
        //else throw new Exception("value is not set");
        else return 'string';
        
        return $resultTypeNormalized;
    }

    public function removeLeadingZeroes($str) { 
        return ltrim($str, '0'); 
    }

    public function addLeadingZeroes($str, $strLength) { 
        return str_pad($str, $strLength, '0', STR_PAD_LEFT); 
    }

    public function toInt($val): int {
        if (!$this->safeInt($val)) throw new Exception("Value could not be converted to int");
        else return (int)$val;
    }
    
    public function toFloat($val): float {
        if (!$this->safeFloat($val)) throw new Exception("Value could not be converted to float");
        else return (float)$val;
    }
    
    public function toString($val): string {
        if (!$this->safeString($val)) throw new Exception("Value could not be converted to string");
        else return (string)$val;
    }

    public function toDate($val): DateTime { 
        return new DateTime($val); 
    }

    public function toPath($path) {
        $path = str_replace( '\\', '/', $path );
        $path = preg_replace('|(?<=.)/+|', '/', $path);
        return ( ':' === substr( $path, 1, 1 ) ) ? ucfirst( $path ) : $path;
    }
    
    public function toSql($data) { global $u;
        if (!isset($data)) throw new Exception("data is not set");
        
        $sqlData = [];
        
        foreach($data as $key => $value) {
            if (empty($value)) {
                $sqlData[$key] = 'NULL';
                continue;
            }

            $type = $u->getType($value);
            
            switch ($type) {
                case 'string': $result = "'$value'"; break;
                case 'datetime': $result = "'" . $value->format('Y-m-d H:i:s') . "'"; break;
                default: $result = $value;
            }
            
            $sqlData[$key] = $result;
        }
        
        return $sqlData;
    }

    private function safeInt($val): bool {
        switch (gettype($val)) {
            case "integer": return true;
            case "double": return $val === (float)(int)$val;
            case "string":
                $losslessCast = (string)(int)$val;
                if ($val !== $losslessCast && $val !== "+$losslessCast") return false;
                return $val <= PHP_INT_MAX && $val >= PHP_INT_MIN;
            default: return false;
        }
    }
    
    private function safeFloat($val): bool {
        switch (gettype($val)) {
            case "double":
            case "integer": return true;
            case "string":
                if (strlen($val) > 1 && $val[0] === "0" && $val[1] !== ".") return false;
    
                $lnum    = "[0-9]+";
                $dnum    = "([0-9]*[\.]{$lnum})|({$lnum}[\.][0-9]*)";
                $expDnum = "/^[+-]?(({$lnum}|{$dnum})[eE][+-]?{$lnum})$/";
    
                return preg_match("/^[+-]?{$lnum}$/", $val) || preg_match("/^[+-]?{$dnum}$/", $val) || preg_match($expDnum, $val);
            default: return false;
        }
    }
    
    private function safeString($val): bool {
        switch (gettype($val)) {
            case "string":
            case "integer":
            case "double": return true;
            case "object": return method_exists($val, "__toString");
            default: return false;
        }
    }
    
    public function getDate($value = 'NOW', $toStr = false, $format = 'Y-m-d H:i:s')  {
        $date = new DateTime($value);
        return ($toStr) ? $date->format($format) : $date;
    }

    public function getTimestamp($value = 'NOW') {
        $date = new DateTime($value);
        return $date->getTimestamp();
    }
    
    public function strToDate($value, $format)  { return DateTime::createFromFormat($format, $value);}
    
    public function dateToStr($value, $format)  { return $value->format($format); }

    public function query($sqlRaw, $dbName = 'jr') { global $src;
		$db = ($dbName === 'jr') ? $src->getJobDB() : $src->getDBConnection($dbName);
		
		$sql = str_replace(["\r","\n","\t","   ", "  "], ["","",""," "," "], $sqlRaw);

        $result = $db->query($sql);

        if (!empty($db->getErrorMessage())) {
            $db->free();
            throw new Exception($db->getErrorMessage());
        }

        $returnValue = $db->fetchAll($result);
        $db->free();

        return $returnValue;
	}
	
	public function sQuery($sql, $params, $dbName = 'jr') { global $src;
		$db = ($dbName === 'jr') ? $src->getJobDB() : $src->getDBConnection($dbName);
		
        $result = (strtolower(strtok(trim($sql), " ")) === 'select') 
            ? $db->preparedSelect($sql, $params)
            : $db->preparedExecute($sql, $params);

        if (!empty($db->getErrorMessage())) {
            $db->free();
            throw new Exception($db->getErrorMessage());
        }
            
        $returnValue = $db->fetchAll($result);
        
        $db->free();

        return $returnValue;
	}

    public function getUser($user) {
        $sql = "SELECT u.username, u.email, u.lastname, u.prename FROM jrusers AS u WHERE u.username = '$user'";
		return $this->query($sql);
    }

    public function getUsersByRole($role) {
        $sql = "
            SELECT u.username, u.email, u.lastname, u.prename 
            FROM jrusers AS u JOIN jruserjob as j ON u.username = j.username 
            WHERE j.jobfunction = '$role'
        ";

        return $this->query($sql);
    }

    public function getRolesByUser($user) {
        $sql = "SELECT j.jobfunction FROM jrusers AS u JOIN jruserjob as j ON u.username = j.username WHERE u.username = '$user'";
        return $this->query($sql);
    }
    
    public function wakeUpIncidentByProcessId($processId) {
        $sql = "UPDATE jrincidents SET step_status = 99 WHERE processid = '$processId' AND step_status = 0 AND module_name = 'wait'";
        return $this->query($sql);
	}
	
	public function wakeUpIncidentByProcessName($processName) {
        $sql = "UPDATE jrincidents SET step_status = 99 WHERE processname = '$processName' AND step_status = 0 AND module_name = 'wait'";
        return $this->query($sql);
	}

    public function copyAttachmentToPath($target) { global $u, $src;
        $srcPath = $u->getValue($src);
        $srcPathNormalized = $u->toPath($srcPath);
        copy($srcPath , $target);
	}

    /* JRMail*/
    public function buildMail() { return new JRMail($this); }

    /* JRIncident */
    public function buildIncident() { return new JRIncident($this); }
}

class JRLogger {
    private $u, $src, $logFolder, $logFolderDbEntry, $logFile, $logFilePath;
    private $processId, $processName, $processVersion, $fullUploadPath;

    private $logCfg = [
        'channel' => 'PT', // [PT|FILE]
        'levels' => array('VERBOSE' => 0, 'DEBUG' => 1, 'INFO'=> 2, 'WARN' => 3, 'ERROR' => 4, 'SUCCESS' => 5),
        'level' => 1, // (DEBUG => 1, INFO => 2, WARN => 3, ERROR => 4)
        'style' => 'HTML', // [HTML|TXT]
        'field' => 'a_log'
    ];

    public function __construct($utils, $source, $logChannel = 'PT', $logLevel = 1) { global $u, $src;
        $u = $utils;
        $src = $source;
        $this->logCfg['level'] = $logLevel;
        $this->logCfg['channel'] = $logChannel;
        $this->initConfig();
        $this->attachLogFileToJRDatabase();
		$src->setTableValue($this->logCfg['field'], "{$this->logFolderDbEntry}/{$logFile}");
    }
    
    private function initConfig() { global $src;
        $this->processId = $src->getProcessId();
        $this->processName = $src->getProcessName();
        $this->processVersion = $src->getVersion();
        $this->fullUploadPath = $src->getFullUploadPath();
        $processIdTrimmed = substr($this->processId, -10);
        $this->logFolder = "log\\$processIdTrimmed\\";
        $this->logFolderDbEntry = "log/$processIdTrimmed";
		$this->logFile = $this->processId . ".log.html";
        $this->logFilePath = "{$this->fullUploadPath}{$this->logFolder}{$this->logFile}";
    }
    
    public function log($logLevelName, $msg, $type) { global $logCfg;
        $caller = $this->getCaller();
        $htmlEncodedMsg = $this->getHtmlEncodedMsg($msg, $type);

        if ($this->isLogAcceptedByLogLevelFilter($logLevelName)) {
            switch($logCfg['channel']) {
                case 'PT': $this->logInHtmlStyle($logLevelName, $htmlEncodedMsg, $caller); break;
                case 'FILE':  error_log("$logLevelName | $caller -> $htmlEncodedMsg"); break;
                default: error_log("unknown logChannel {$logCfg['channel']}"); break;
            }
        }
    }

    private function logInHtmlStyle($logLevelName, $msg, $caller) { global $u, $src;
        $logFieldContent = $src->getTableValue($this->logField);

        if (empty($logFieldContent) || $logFieldContent === '') $this->createNewLogFile();

        $current_date = $u->getDate('NOW', true);
        
        $data = "<p>$current_date, <span class=\"LOGLEVEL $logLevelName\">$logLevelName</span>, <span class=\"LOGFUNC\">$caller</span><b> -> </b>$msg</p>\n";
        $result = file_put_contents($this->logFilePath, $data, FILE_APPEND | LOCK_EX);
        if (!$result) { throw new Exception("writing to logfile {$this->logFilePath} failed."); }
    }
    
    private function getHtmlEncodedMsg($msg, $type) {
        switch($type) {
            case 'str': return $msg;
            case 'obj': return print_r($msg, true);
            case 'xml': return str_replace(array('<', '>'), array('&#60;', '&#62;'), $this->getFormattedXML($msg));
            default: throw new Exception("wrong type '$type'.");
        }
    }
    
    public function getFormattedXML($msg) {
        $dom = new DOMDocument;
        $dom->preserveWhiteSpace = FALSE;
        $dom->loadXML($msg);
        $dom->formatOutput = TRUE;
        return $dom->saveXML();
    }

    private function getCaller() {
        $traceLine = ((new Exception)->getTrace())[3];
        $search = "{$this->processName}_{$this->processVersion}_";
        $callerClass = str_replace($search, '', $traceLine['class']);
        $callerFunction = $traceLine['function'];
        $result = "$callerClass::$callerFunction()";
        return $result;
    }

    private function attachLogFileToJRDatabase() { global $u, $src;
        $sql = "SELECT * FROM JRATTACHMENTS WHERE processid = '{$this->processId}' AND filename = '{$this->logFile}'";
        $result = $u->query($sql);

        if (sizeof($result) === 0) {
            $sql = "
                INSERT INTO JRATTACHMENTS (processid, folder, filename, original_filename)
                VALUES ('{$this->processId}', '$this->logFolderDbEntry', '{$this->logFile}', '{$this->logFile}');
            ";

            $u->query($sql);
        }
	}
	
	public function createNewLogFile() {
        $header = 
            '<meta http-equiv="refresh" content="15"/>'
            . "<style> "
                . "p { font-size:16px;font-family:helvetica;white-space: pre-wrap; } "
                . ".LOGLEVEL { font-size:13px; font-weight: bold; } "
                . ".SUCCESS { color: darkgreen; } "
                . ".ERROR { color: red; } "
                . ".WARN { color: goldenrod; } "
                . ".INFO { color: grey; } "
                . ".DEBUG { color: blue; } "
                . ".VERBOSE { color: blue; } "
                . ".LOGFUNC { color: deepskyblue; } "
            . "</style>\n";

        $path = "{$this->fullUploadPath}{$this->logFolder}";
        if (!file_exists($path)) { mkdir($path, 0777, true); }
        
        $result = file_put_contents($this->logFilePath, $header, LOCK_EX);
        if (!$result) { throw new Exception("Creating logfile {$this->logFilePath} failed."); }
	}

    private function isLogAcceptedByLogLevelFilter($logLevelName) {
        $myLogLevel = $this->logLevels[$logLevelName];
        return ($myLogLevel >= $this->logLevel) ? true : false;
	}
}

class JRIncident {
	private $utils;
        
    private $data = [
        "processname" => null,
        "initiator" => 'admin',
        "simulation" => 0,
        "version" => 0,
        "step" => null,
        "insstring" => null,
        "subtable_insstring" => null,
        "jobfunction" => null,
        "username" => null,
        "step_escalation_date" => null,
        "incident_escalation_date" => null,
        "indate" => null
    ];

    public function __construct($utils) { $this->utils = $utils; }
    
    public function processname($processname) { $this->data["processname"] = $processname;  }
    public function version($version) { $this->data["version"] = $version; }
    public function initiator($initiator) { $this->data["initiator"] = $initiator; }
    public function step($step) { $this->data["step"] = $step; }
    public function simulation($simulation) { $this->data["simulation"] = $simulation; }
    public function pt($params) { $this->data["insstring"] = $this->mappedImplode($params); }
    public function st($params) { $this->data["subtable_insstring"] = $params; }
    public function role($jobfunction) { $this->data["jobfunction"] = $jobfunction; }
    public function user($username) { $this->data["username"] = $username; }
    public function indate($indate) { $this->data["indate"] = $indate; }
    public function stepEscalationDate($stepEscalationDate) { $this->data["step_escalation_date"] = $stepEscalationDate; }
    public function incidentEscalationDate($incEscalationDate) { $this->data["incident_escalation_date"] = $incEscalationDate; }
    
    private function mappedImplode($array, $glue = ";", $symbol = '=') {
        $func = function($k, $v) use($symbol) { return $k . $symbol . $v; };
        return implode($glue, array_map($func, array_keys($array), array_values($array)));
    }
    
    public function send() { global $u;
        $sql = "
            INSERT INTO jrjobimport (
                processname, initiator, simulation, version, step, insstring, subtable_insstring, 
                jobfunction, username, step_escalation_date, incident_escalation_date, indate
            )
            VALUES (
                :processname, :initiator, :simulation, :version, :step, :insstring, :subtable_insstring, :jobfunction, 
                :username, :step_escalation_date, :incident_escalation_date, :indate
            )
        ";
        
        return $u->sQuery($sql, $this->data);
    }
}

class JRMail {
    private $u, $params = [
        'subject' => null,
        'msg' => null,
        'attachment' => null,
        'cc' => null,
        'bcc' => null,
        'indate' => null
    ];

    public function __construct($utils) { global $u;
        $u = $utils; 
    }

    public function subject($subject) { $this->params['subject'] = $subject; }
    public function msg($msg) { $this->params['msg'] = $msg; }
    public function attachment($attachment) { $this->params['attachment'] = $attachment; }
    public function cc($cc) { $this->params['cc'] = $cc; }
    public function bcc($bcc) { $this->params['bcc'] = $bcc; }
    public function indate($indate) { $this->params['indate'] = $indate; }

    public function target($toEmail, $toName = null) { 
        $this->params['toEmail'] = $toEmail; 
        $this->params['toName'] = $toName; 
    }

    public function source($fromEmail, $fromName = null) { 
        $this->params['fromEmail'] = $fromEmail; 
        $this->params['fromName'] = $fromName; 
    }

    public function send() { global $u;
        //$p = $u->toSql($this->mail);

        if (empty($params['indate'])) {
            $this->params['indate'] = $u->getDate();
        }

        $sql = "
            INSERT INTO JRMAIL (
                from_email, from_name, to_email, to_name, cc_email, bcc_email, 
                subject, emailtext, attachment, indate, mailtype
            )
            VALUES (:fromEmail, :fromName, :toEmail, :toName, :cc, :bcc, :subject, :msg, :attachment, :indate, 1)
        ";
           
        return $u->sQuery($sql, $this->params);
    }
}

class MISC {
    public static function getObjectDetails($obj) {
        $reflection = new \ReflectionClass($obj);
            
        $ref = "";
        $ref .= "class " . $reflection->getName() . " {\n\n";
        
        $properties = $reflection->getProperties();
        foreach($properties as $property) {
            $ref .= "\t" . $property->getType() . " " . $property->getName() . "\n";
        }
        
        $methods = $reflection->getMethods();

        foreach($methods as $method) {
            if (!empty($method->getDocComment()))
                $ref .= "\n\t" . print_r($method->getDocComment(), true) . "\n";
            
            $params = $method->getParameters();
            $paramsAry = [];
            
            foreach($params as $param) { 
                $paramStr = $param->getType() . " " . $param->getName();
                
                if ($param->isOptional()) $paramStr = "[" . $paramStr . "]";
            
                array_push($paramsAry, $paramStr); 
            }
            
            $paramsStr = implode(', ', $paramsAry);
            
            $ref .= "\tfunction " . $method->getName() . "(" . $paramsStr . ") { }\n";

        }
        
        $ref .= "}";
    }
}

?>