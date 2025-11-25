<?php
/**
 * ============================================================================
 * CLASE DE CONEXIÓN A BASE DE DATOS
 * ============================================================================
 * Implementa el patrón Singleton para gestionar conexiones PDO
 * ============================================================================
 */

class Database {
    private static $instance = null;
    private $connection;
    private $host;
    private $database;
    private $username;
    private $password;
    private $charset;

    /**
     * Constructor privado para implementar Singleton
     */
    private function __construct() {
        $this->host = DB_HOST;
        $this->database = DB_DATABASE;
        $this->username = DB_USERNAME;
        $this->password = DB_PASSWORD;
        $this->charset = DB_CHARSET;
        
        $this->connect();
    }

    /**
     * Obtiene la instancia única de la clase
     * 
     * @return Database
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establece la conexión con la base de datos
     * 
     * @throws Exception si falla la conexión
     */
    private function connect() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset={$this->charset}";
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
            ];

            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch (PDOException $e) {
            $this->logError('Database connection failed: ' . $e->getMessage());
            throw new Exception('Error de conexión a la base de datos');
        }
    }

    /**
     * Obtiene la conexión PDO
     * 
     * @return PDO
     */
    public function getConnection() {
        // Verificar si la conexión sigue activa
        if ($this->connection === null) {
            $this->connect();
        }
        return $this->connection;
    }

    /**
     * Ejecuta una consulta preparada
     * 
     * @param string $query Consulta SQL
     * @param array $params Parámetros para la consulta
     * @return PDOStatement
     */
    public function query($query, $params = []) {
        try {
            $stmt = $this->connection->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            $this->logError('Query failed: ' . $e->getMessage() . ' | Query: ' . $query);
            throw new Exception('Error al ejecutar la consulta');
        }
    }

    /**
     * Obtiene una fila de la consulta
     * 
     * @param string $query Consulta SQL
     * @param array $params Parámetros
     * @return array|false
     */
    public function fetchOne($query, $params = []) {
        $stmt = $this->query($query, $params);
        return $stmt->fetch();
    }

    /**
     * Obtiene todas las filas de la consulta
     * 
     * @param string $query Consulta SQL
     * @param array $params Parámetros
     * @return array
     */
    public function fetchAll($query, $params = []) {
        $stmt = $this->query($query, $params);
        return $stmt->fetchAll();
    }

    /**
     * Ejecuta un INSERT y retorna el último ID insertado
     * 
     * @param string $query Consulta SQL
     * @param array $params Parámetros
     * @return string ID del último registro insertado
     */
    public function insert($query, $params = []) {
        $this->query($query, $params);
        return $this->connection->lastInsertId();
    }

    /**
     * Ejecuta un UPDATE o DELETE y retorna el número de filas afectadas
     * 
     * @param string $query Consulta SQL
     * @param array $params Parámetros
     * @return int Número de filas afectadas
     */
    public function execute($query, $params = []) {
        $stmt = $this->query($query, $params);
        return $stmt->rowCount();
    }

    /**
     * Inicia una transacción
     * 
     * @return bool
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }

    /**
     * Confirma una transacción
     * 
     * @return bool
     */
    public function commit() {
        return $this->connection->commit();
    }

    /**
     * Revierte una transacción
     * 
     * @return bool
     */
    public function rollback() {
        return $this->connection->rollBack();
    }

    /**
     * Registra errores en el log
     * 
     * @param string $message Mensaje de error
     */
    private function logError($message) {
        $logFile = LOG_PATH . 'database_' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        error_log($logMessage, 3, $logFile);
    }

    /**
     * Prevenir clonación del objeto
     */
    private function __clone() {}

    /**
     * Prevenir deserialización del objeto
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
