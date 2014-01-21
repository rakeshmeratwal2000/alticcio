<?php

class Mysql {
	
	private $db;
	private $types;
	
	public function __construct($params) {
		$server = isset($params['server']) ? $params['server'] : "localhost";
		$user = isset($params['user']) ? $params['user'] : "root";
		$password = isset($params['password']) ? $params['password'] : "";
		if (!($this->db = mysql_connect($server, $user, $password, true))) {
			throw new Exception(mysql_error($this->db));
		}
		mysql_set_charset("utf8", $this->db);
		if (!mysql_select_db ($params['database'], $this->db)) {
			throw new Exception(mysql_error($this->db));
		}
	}
	
	public function query($q, $args = array()) {
		if (!$result = mysql_query($q, $this->db)) {
			throw new Exception(mysql_error($this->db)." Query: $q");
		}
		
		return $result;
	}
	
	public function fetch($result) {
		if (!$result) {
			throw new Exception(mysql_error($this->db));
		}
		return mysql_fetch_assoc($result);
	}
	
	public function insert_id() {
		return mysql_insert_id($this->db);
	}
	
	public function found_rows() {
		$res = $this->query("SELECT FOUND_ROWS() AS total");
		$row = $this->fetch($res);
		
		return $row['total'];
	}

	public function limit($limit, $offset) {
		$q_limit = "";
		if ($limit) {
			if ($offset) {
				$q_limit = "LIMIT $offset, $limit";
			}
			else {
				$q_limit = "LIMIT $limit";
			}
		}
		return $q_limit;
	}

    function quote($value) {
        $type = gettype($value);
        switch ($type) {
            case 'boolean':
                $value = (int) $value;
                break;
            case 'NULL':
                $value = 'NULL';
                break;
            case 'string':
                $value = "'".mysql_real_escape_string($value)."'";
                break;
        }
        return $value;
    }

	function quote_string($table, $field, $value) {
		if (!isset($this->types[$table])) {
			$q = "SHOW COLUMNS FROM $table";
			$res = $this->query($q);
			while ($row = $this->fetch($res)) {
				$this->types[$table][$row['Field']] = strtoupper($row['Type']);
			}
		}

		if (isset($this->types[$table][$field])) {
			foreach (array('CHAR', 'VARCHAR', 'BINARY', 'VARBINARY', 'BLOB', 'TEXT', 'ENUM', 'SET') as $type) {
				if (strpos($this->types[$table][$field], $type) === 0) {
					return "'$value'";
				}
			}
		}

		return $value ? $value : 0;
	}

	function file($file) {
		$queries = file_get_contents($file);
		$queries = preg_replace("/^--(.*)$/m", "", $queries);
		$queries = preg_replace("/^\/\*(.*)\*\/;$/m", "", $queries);
		$queries = str_replace(";\n", "MYSQL_QUERY_END", $queries);
		$queries = str_replace("\n", " ", $queries);
		foreach(explode("MYSQL_QUERY_END", $queries) as $q) {
			if ($q = trim($q)) {
				$this->query($q);
			}
		}
	}
}

?>
