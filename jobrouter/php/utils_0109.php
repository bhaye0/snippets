<?php
// utils_php build 0109

declare(strict_types=1);
set_time_limit(20);
//error_reporting(-1);

class JRUtilities {   
    public $src, $db, $logger, $process;
    private $dateUtils;

    public function __construct($source) {
        $this->src = $source;
        $this->db = new JRDatabase($this);
        $this->process = $this->db->loadProcessMetaData();
    }

	/* JRLogger */
	public function log($level, $msg, $type = 'str') {
	    if (!isset($this->logger)) $this->initLogger("DEBUG", "FILE");
	    $this->logger->log($level, $msg, $type); 
	}
	public function resetlogFile() { $this->logger->resetLogFile(); }
    public function initLogger($logLevel, $logChannel) { $this->logger = new JRLogger($this, $logLevel, $logChannel); }
    
    /* JRData */
    public function getValue($target) { return JRData::getValue($this, $target); }
    public function setValue($target, $value) { JRData::setValue($this, $target, $value); }
    public function insertStRow($subtable, $rowData, $prepend = false) { JRData::insertStRow($this, $subtable, $rowData, $prepend); }
    public function filterArray($srcArray, $filter = null, $distinct = false) { return JRData::filterArray($srcArray, $filter, $distinct); }
	public function toAmount($num, $toStr = false, $separator = '.')  { return JRData::toAmount($num, $toStr, $separator); }
    public function getType($value, $returnClass = true) { return JRData::getType($value, $returnClass); }
    public function getObjectMethods($obj)  { return JRData::getObjectMethods($obj); } 
    public function removeLeadingZeroes($str) { return JRData::removeLeadingZeroes($str); }
    public function addLeadingZeroes($str, $strLength) { return JRData::addLeadingZeroes($str, $strLength); }
	public function quoteOrNull($value) { return JRData::quoteOrNull($value); }
    public function toInt($val) { JRData::toInt($val); }
    public function toFloat($val) { JRData::toFloat($val); }
    public function toString($val) { JRData::toString($val); }
    public function toDate($val) { JRData::toDate($val); }
    public function toPath($val) { JRData::toPath($val); }
    public function toSql($value, $required = false) { return JRData::toSql($this, $value, $required); }
    public function toSqlAry($data) { return JRData::toSqlAry($this, $data); }
    public function getDate($value = 'NOW', $toStr = false, $format = "Y-m-d H:i:s")  { return JRData::getDate($value, $toStr, $format);}
    public function strToDate($value, $format) { return JRData::strToDate($value, $format); }
    public function dateToStr($value, $format = 'Y-m-d H:i:s') { return JRData::dateToStr($value, $format); }

    /* JRDatabase */
    public function query($sql, $dbName = 'jr') { return $this->db->query($sql, $dbName); }
    public function sQuery($sql, $params, $dbName = 'jr') { return $this->db->sQuery($sql, $params, $dbName); }
    public function pQuery($sql, $params, $types, $dbName = 'jr') { return $this->db->pQuery($sql, $params, $types, $dbName); }
    public function getUser($user) { return $this->db->getUser($user); }
    public function getUsersByRole($role) { return $this->db->getUsersByRole($role); }
    public function getRolesByUser($user) { return $this->db->getRolesByUser($user); }
    public function wakeUpIncident($processId) { return $this->db->wakeUpIncident($processId); }

    /* JRMail*/
    public function buildMail() { return new JRMail($this); }

    /* JRIncident */
    public function buildIncident() { return new JRIncident($this); }
}

/*************************************** JRData ***************************************/
class JRData {
    public static function getValue($u, $target) {
        if (strpos($target, '.') !== false) {
            $targetAry = explode('.', $target);
            $type = self::getTargetType($u, $targetAry[0], $targetAry[1]);

            $result = ($targetAry[2] === "*")
                ? self::getIncidentArray($u, $target, $targetAry, $type)
                : self::getIncidentValue($u, $target, $targetAry, $type);
        }
        else {
            $type = self::getTargetType($u, 'pt', $target);
            $result = self::getIncidentValue($u, $target, null, $type);
        }
        
        return $result;
    }

    private static function getTargetType($u, $table, $field) {
        foreach ($u->process['map'] as $item) {
            if ($item['subtable'] === $table && $item['field'] === $field) return $item['type'];
        }

        throw new Exception("Target '$table.$field' does not exist.");
    }

    private static function getIncidentValue($u, $target, $targetAry, $type) { $s = $u->src;   
        $value = (isset($targetAry))
            ? $s->getSubtableValue($targetAry[0], intval($targetAry[2]), $targetAry[1], true)
            : $s->getTableValue($target, true);

        //if ($required && is_null($value)) throw new Exception("Required value at '$target' is not set");
        
        if (empty($value)) return null;

        switch($type) {
            case 'varchar': $typedValue = $value; break;
            case 'int': $typedValue = JRData::toInt($value); break;
            case 'decimal': $typedValue = JRData::toFloat($value); break;
            case 'datetime': $typedValue = JRData::toDate($value); break;
            case 'file': $typedValue = $value; break;
            default: throw new Exception("unknown type '$type' at '$target");
        }

        return $typedValue;
    }

    private static function getIncidentArray($u, $target, $targetAry, $type) { $s = $u->src;
        $subtableRowIds = $s->getSubtableRowIds($target[0]);
        $result = array();

        foreach ($subtableRowIds as $row) {
            $targetAry[2] = $row;
            $value = self::getIncidentValue($u, $target, $targetAry, $type);
            array_push($result, $value);
        }

        return $result;
    }
    
    public static function setValue($u, $target, $value) {
        if (strpos($target, '.') !== false) {
            $targetAry = explode('.', $target);

            if ($targetAry[2] === "*") self::setIncidentArray($u, $targetAry, $value);
            else self::setIncidentValue($u, $targetAry, $value);
        }
        else self::setIncidentValue($u, $target, $value);
    }
    
    private static function setIncidentValue($u, $target, $value) { $s = $u->src;
        if (is_array($target)) $s->setSubtableValue($target[0], intval($target[2]), $target[1], $value);
        else $s->setTableValue($target, $value);
    }

    private static function setIncidentArray($u, $target, $value) { $s = $u->src;
        $subtableRowIds = $s->getSubtableRowIds($target[0]);

        foreach ($subtableRowIds as $row) {
            $target[2] = $row;
            self::setIncidentValue($u, $target, $value);
            
        }
    }

    public static function insertStRow($u, $subtable, $rowData, $prepend) { $s = $u->src;
        $rowId = ($prepend) ? 1: intval($s->getSubtableCount($subtable)) + 1;
        $s->insertSubtableRow($subtable, $rowId, $rowData);
    }

    public static function filterArray($srcArray, $filters, $distinct) {
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
	
	public static function toAmount($numRaw, $toStr = false, $separator = '.') {
	    $num = strval($numRaw);
	    
        $dotPos = strrpos($num, '.');
        $commaPos = strrpos($num, ',');
        $sep = (($dotPos > $commaPos) && $dotPos) ? $dotPos :
            ((($commaPos > $dotPos) && $commaPos) ? $commaPos : false);
      
        if (!$sep) return floatval(preg_replace("/[^0-9]/", "", $num));
    
        $result = preg_replace("/[^0-9]/", "", substr($num, 0, $sep)) . $separator .preg_replace("/[^0-9]/", "", substr($num, $sep+1, strlen($num)));
        return ($toStr) ? $result : floatval($result);
    }
    
    public static function getType($value, $returnClass) { 
        if (isset($value)) {
            $baseType = gettype($value);
            $resultType = ($baseType === 'object' && $returnClass) ? get_class($value) : $baseType;
            $resultTypeNormalized = strtolower(trim($resultType));
        }
        //else throw new Exception("value is not set");
        else return 'string';
        
        return $resultTypeNormalized;
    }

    public static function getObjectMethods($obj) {
        $methods = get_class_methods($obj);
        $resultList = array();

        foreach ($methods as $method) {
            $r = new ReflectionMethod($obj, $method);
            $params = $r->getParameters();
        
            if (!empty($params)) {
                $param_names = array();
                foreach($params as $param) array_push($param_names, $param->getName());
                $result = $method . "($" . implode(', $', $param_names ) . ")";
            }
            else { $result = "$method()"; }
            
            array_push($resultList, $result);
        }
        
        return $resultList;
    }

    public static function removeLeadingZeroes($str) { return ltrim($str, '0'); }
    public static function addLeadingZeroes($str, $strLength) { return str_pad($str, $strLength, '0', STR_PAD_LEFT); }
	public static function quoteOrNull($value) { return (isset($value)) ? "'" . $value . "'" : "NULL"; }

    public static function toInt($val): int {
        if (!self::safeInt($val)) throw new Exception("Value could not be converted to int");
        else return (int)$val;
    }
    
    public static function toFloat($val): float {
        if (!self::safeFloat($val)) throw new Exception("Value could not be converted to float");
        else return (float)$val;
    }
    
    public static function toString($val): string {
        if (!self::safeString($val)) throw new Exception("Value could not be converted to string");
        else return (string)$val;
    }

    public static function toDate($val): DateTime { return new DateTime($val); }

    private static function safeInt($val): bool {
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

    public static function toPath($path) {
        $path = str_replace( '\\', '/', $path );
        $path = preg_replace('|(?<=.)/+|', '/', $path);
        return ( ':' === substr( $path, 1, 1 ) ) ? ucfirst( $path ) : $path;
    }
    
    public static function toSqlAry($u, $data) {
        if (!isset($data)) throw new Exception("data is not set");
        
        $sqlData = array();
        
        foreach($data as $key => $value) {
            $type = $u->getType($value);
            
            switch ($type) {
                case 'string': $result = $u->quoteOrNull($value); break;
                case 'datetime': $result = $u->quoteOrNull($value->format('Y-m-d H:i:s')); break;
                default: $result = $value;
            }
            
            $sqlData[$key] = $result;
        }
        
        return $sqlData;
    }

    public static function toSql($u, $value, $required) {
        //if (!isset($value)) throw new Exception("value is not set");
        if (!isset($value)) return 'NULL';

        $type = $u->getType($value);

        switch ($type) {
            case 'string': $result = $u->quoteOrNull($value); break;
            case 'datetime': $result = $u->quoteOrNull($value->format('Y-m-d H:i:s'));
            default: $result = $value;
        }
		
        if ($required && $result === "NULL") throw new Exception("value $value of type $type may not be NULL");
        return $result;
    }
    
    private static function safeFloat($val): bool {
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
    
    private static function safeString($val): bool {
        switch (gettype($val)) {
            case "string":
            case "integer":
            case "double": return true;
            case "object": return method_exists($val, "__toString");
            default: return false;
        }
    }
    
    public static function getDate($value, $toStr, $format)  {
        $date = new DateTime($value);
        return ($toStr) ? $date->format($format) : $date;
    }
    
    public static function strToDate($value, $format)  {
        return DateTime::createFromFormat($format, $value);
    }
    
    public static function dateToStr($value, $format)  {
        return $value->format($format);
    }
}

/*************************************** JRDatabase ***************************************/
class JRDatabase {
    private $u, $src;
    
    public function __construct($utils) {
        $this->u = $utils;
        $this->src = $utils->src;
    }
    
	public function query($sql, $dbName = 'jr') {
		$db = ($dbName === 'jr') ? $this->src->getJobDB() : $this->src->getDBConnection($dbName);

        $result = $db->query($sql);

        if (!$result) {
            $errorMsg = $db->getErrorMessage();
            $db->free();
            throw new Exception($errorMsg);
        }

        $returnValue = $db->fetchAll($result);
        $db->free();

        return $returnValue;
	}
	
	public function pQuery($sql, $params, $types, $dbName = 'jr') {
		$db = ($dbName === 'jr') ? $this->src->getJobDB() : $this->src->getDBConnection($dbName);
		
        $result = (strtolower(strtok(trim($sql), " ")) === 'select') ? 
            $db->preparedSelect($sql, $params, $types) :
            $db->preparedExecute($sql, $params, $types);

        if (!$result) {
            $errorMsg = $db->getErrorMessage();
            $db->free();
            throw new Exception($errorMsg);
        }

        $returnValue = $db->fetchAll($result);
        $db->free();

        return $returnValue;
	}
	
	public function sQuery($sql, $params, $dbName = 'jr') { 
		$db = ($dbName === 'jr') ? $this->src->getJobDB() : $this->src->getDBConnection($dbName);

        $result = (strtolower(strtok(trim($sql), " ")) === 'select') 
            ? $db->preparedSelect($sql, $params)
            : $db->preparedExecute($sql, $params);

        if (!empty($db->getErrorMessage())) {
            $db->free();
            throw new Exception($db->getErrorMessage());
        }
            
        return $db->fetchAll($result);
        $db->free();

        return $returnValue;
	}

    public function loadProcessMetaData() {
        $db = $this->src->getJobDB();
        
        $process = array(
            'id' => $this->src->getProcessId(),
            'name' => $this->src->getProcessName(),
            'version' => $this->src->getVersion(),
            'incident' => $this->src->getIncident(),
            'dbType' => $db->getDbType() // 1:mysql, 5:mssql
        );

        $db->free();

        $processName = $process['name'];

        $sql = "
            SELECT subtable, field_name as field, field_type as type FROM jrsubtablefields WHERE processname = '$processName'
            UNION
            SELECT 'pt' as subtable, field_name as field, field_type as type FROM jrtablefields WHERE processname = '$processName'
        ";

        $process['map'] = $this->query($sql);

        return $process;
    }

    public function getUser($user) {
        $sql = "
            SELECT u.username, u.email, u.lastname, u.prename 
            FROM jrusers AS u 
            WHERE u.username = '$user'
        ";

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
        $sql = "
            SELECT j.jobfunction 
            FROM jrusers AS u JOIN jruserjob as j ON u.username = j.username 
            WHERE u.username = '$user'
        ";

        return $this->query($sql);
    }
    
    public function wakeUpIncident($processId) {
        $sql = "
            UPDATE jrincidents SET step_status = 99 
            OUTPUT inserted.* 
            WHERE processid = '$processId' AND step_status = 0 AND module_name = 'wait'
        ";

        if ($u->process['dbType'] === 1) $sql = str_replace("OUTPUT inserted.*", "", $sql);
        return $this->query($sql);
	}
}

/*************************************** JRFile ***************************************/
class JRFile {
    public static function copyAttachmentToPath($u, $src, $target) {
        $srcPath = $u->getValue($src);
        $srcPathNormalized = $u->toPath($srcPath);
        copy($srcPath , $target);
	}

    public static function copyFileToAttachment($u, $src, $target) {
        $srcPath = $u->getValue($src);
        $srcPathNormalized = $u->toPath($srcPath);
        copy($srcPath , $target);
	}
}
/*************************************** JRLogger ***************************************/
class JRLogger {
    private $u, $src, $logFolder, $logFolderDbEntry, $logFile, $logFilePath, $process = array();
    private $processId, $processName, $processVersion, $fullUploadPath;
    
    private $logChannel; // [PT|FILE]
    private $logLevels = array('VERBOSE' => 0, 'DEBUG' => 1, 'INFO'=> 2, 'WARN' => 3, 'ERROR' => 4, 'SUCCESS' => 5);
    private $logLevel; // (DEBUG => 1, INFO => 2, WARN => 3, ERROR => 4)
    private $logStyle = 'HTML'; // [HTML|TXT]
    private $logField = 'a_log';

    public function __construct($utils, $logLevel = 'PT', $logChannel = 1) {
        $this->u = $utils;
        $this->src = $utils->src;
        $this->initConfig();
        $this->setLevel($logLevel);
        $this->setChannel($logChannel);
    }
    
    private function initConfig() {
        $this->processId = $this->src->getProcessId();
        $this->processName = $this->src->getProcessName();
        $this->processVersion = $this->src->getVersion();
        $this->fullUploadPath = $this->src->getFullUploadPath();
        $processIdTrimmed = substr($this->processId, -10);
        $this->logFolder = "log\\$processIdTrimmed\\";
        $this->logFolderDbEntry = "log/$processIdTrimmed";
		
		$this->logFile = $this->processId . ".log.html";
        $this->logFilePath = "{$this->fullUploadPath}{$this->logFolder}{$this->logFile}";
    }
    
    public function setLevel($level) { 
        switch($level) {
            case 'VERBOSE': $this->logLevel = 0; break;
            case 'DEBUG': $this->logLevel = 1; break;
            case 'INFO': $this->logLevel = 2; break;
            case 'WARN': $this->logLevel = 3; break;
            case 'ERROR': $this->logLevel = 4; break;
            case 'SUCCESS': $this->logLevel = 5; break;
        }
    }

    public function setChannel($logChannel) { $this->logChannel = $logChannel; }
    public function setStyle($logStyle) { $this->logStyle = $logStyle; return $this;}
	public function setField($logField) { $this->logField = $logField; return $this;}
	
	public function resetLogFile() { $src = $this->src;
	    $this->createNewLogFile();
        $this->attachLogFileToJRDatabase();
		$src->setTableValue($this->logField, "{$this->logFolderDbEntry}/{$this->logFile}");
	}
    
    public function log($logLevelName, $msg, $type) {
        $caller = $this->getCaller();
        $htmlEncodedMsg = $this->getHtmlEncodedMsg($msg, $type);

        if ($this->isLogAcceptedByLogLevelFilter($logLevelName)) {
            switch($this->logChannel) {
                case 'PT': $this->logInHtmlStyle($logLevelName, $htmlEncodedMsg, $caller); break;
                case 'FILE':  error_log("$logLevelName | $caller -> $htmlEncodedMsg"); break;
                default: error_log("unknown logChannel " . $this->logChannel); break;
            }
        }
    }

    private function logInHtmlStyle($logLevelName, $msg, $caller) { $u = $this->u; $src = $this->src;
        $logFieldContent = $src->getTableValue($this->logField);

        if (empty($logFieldContent) || $logFieldContent === '') $this->resetLogFile();

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

    private function attachLogFileToJRDatabase() { $u = $this->u; $src = $this->src;
        $sql = "
            INSERT INTO JRATTACHMENTS (processid, folder, filename, original_filename)
    		VALUES ('{$this->processId}', '$this->logFolderDbEntry', '{$this->logFile}', '{$this->logFile}')
    		ON DUPLICATE KEY UPDATE original_filename = '{$this->logFile}'
        ";

        $u->query($sql);
	}
	
	private function createNewLogFile() {
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

/*************************************** JRIncident ***************************************/
class JRIncident {
	private $utils;
        
    private $data = array(
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
    );

    public function __construct($utils) { $this->utils = $utils; }
    
    public function processname($processname) { $this->data["processname"] = $processname; return $this; }
    public function version($version) { $this->data["version"] = $version; return $this; }
    public function initiator($initiator) { $this->data["initiator"] = $initiator; return $this; }
    public function step($step) { $this->data["step"] = $step; return $this; }
    public function simulation($simulation) { $this->data["simulation"] = $simulation; return $this; }
    public function pt($params) { $this->data["insstring"] = $this->mappedImplode($params); return $this; }
    public function st($params) { $this->data["subtableInsstring"] = $this->mappedImplode($params); return $this; }
    public function role($jobfunction) { $this->data["jobfunction"] = $jobfunction; return $this; }
    public function user($username) { $this->data["username"] = $username; return $this; }
    public function indate($indate) { $this->data["indate"] = $indate; return $this; }
    public function stepEscalationDate($stepEscalationDate) { $this->data["stepEscalationDate"] = $stepEscalationDate; return $this; }
    public function incidentEscalationDate($incidentEscalationDate) { $this->data["incidentEscalationDate"] = $incidentEscalationDate; return $this; }
    
    private function mappedImplode($array, $glue = ";", $symbol = '=') {
        return implode($glue, array_map(
            function($k, $v) use($symbol) { return $k . $symbol . $v; },
            array_keys($array),
            array_values($array)
            )
        );
    }
    
    public function send() { $u = $this->utils;
        $sql = "
            INSERT INTO jrjobimport (
                processname, initiator, simulation, version, step, insstring, subtable_insstring, 
                jobfunction, username, step_escalation_date, incident_escalation_date, indate
            )
            VALUES (
                :processname,
                :initiator,
                :simulation,
                :version,
                :step,
                :insstring,
                :subtable_insstring,
                :jobfunction,
                :username,
                :step_escalation_date,
                :incident_escalation_date,
                :indate
            )
        ";
        
        return $u->sQuery($sql, $this->data);
    }
}

/*************************************** JRMail ***************************************/
class JRMail {
    private $utils, $subject, $msg, $attachment, $toEmail, $toName, $fromEmail, $fromName, $cc, $bcc, $indate;

    public function __construct($utils) { $this->utils = $utils; }

    public function subject($subject) { $this->subject = $subject; return $this; }
    public function msg($msg) { $this->msg = $msg; return $this; }
    public function attachment($attachment) { $this->attachment = $attachment; return $this; }
    public function cc($cc) { $this->cc = $cc; return $this; }
    public function bcc($bcc) { $this->bcc = $bcc; return $this; }
    public function indate($indate) { $this->indate = $indate; return $this; }
    public function target($toEmail, $toName = null) { $this->toEmail = $toEmail; $this->toName = $toName; return $this; }
    public function source($fromEmail, $fromName = null) { $this->fromEmail = $fromEmail; $this->fromName = $fromName; return $this; }

    public function send($dbName = 'jr') { $u = $this->utils;
        $sql = "
            INSERT INTO JRMAIL (
                from_email, from_name, to_email, to_name, cc_email, bcc_email, 
                subject, emailtext, attachment, indate, mailtype
            )
            OUTPUT INSERTED.*
            VALUES (
                {$u->toSql($this->fromEmail, 's')},
                {$u->toSql($this->fromName, 's', false)},
                {$u->toSql($this->toEmail, 's')},
                {$u->toSql($this->toName, 's', false)},
                {$u->toSql($this->cc, 's', false)},
                {$u->toSql($this->bcc, 's', false)},
                {$u->toSql($this->subject, 's')},
                {$u->toSql($this->msg, 's')},
    			{$u->toSql($this->attachment, 's', false)},
                {$u->toSql($this->msg, 'd')},
                1
            )
        ";

        if ($u->process['dbType'] === 1) $sql = str_replace("OUTPUT inserted.*", "", $sql);
            
        return $u->query($sql, $dbName);
    }
}

?>