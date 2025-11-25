<?php
/**
 * ============================================================================
 * CLASE PARA VALIDACIÓN DE DATOS
 * ============================================================================
 * Valida datos de entrada según reglas especificadas
 * ============================================================================
 */

class Validator {
    
    private $data;
    private $rules;
    private $errors = [];
    private $customMessages = [];
    
    /**
     * Constructor
     * 
     * @param array $data Datos a validar
     * @param array $rules Reglas de validación
     * @param array $customMessages Mensajes personalizados opcionales
     */
    public function __construct($data, $rules, $customMessages = []) {
        $this->data = $data;
        $this->rules = $rules;
        $this->customMessages = $customMessages;
    }
    
    /**
     * Ejecuta la validación
     * 
     * @return bool
     */
    public function validate() {
        foreach ($this->rules as $field => $ruleSet) {
            $rules = explode('|', $ruleSet);
            $value = $this->data[$field] ?? null;
            
            foreach ($rules as $rule) {
                $this->applyRule($field, $value, $rule);
            }
        }
        
        return empty($this->errors);
    }
    
    /**
     * Aplica una regla de validación
     * 
     * @param string $field Campo a validar
     * @param mixed $value Valor del campo
     * @param string $rule Regla a aplicar
     */
    private function applyRule($field, $value, $rule) {
        // Parsear regla con parámetros (ej: min:5)
        $ruleParts = explode(':', $rule);
        $ruleName = $ruleParts[0];
        $ruleParam = $ruleParts[1] ?? null;
        
        switch ($ruleName) {
            case 'required':
                if (empty($value) && $value !== '0') {
                    $this->addError($field, 'required');
                }
                break;
                
            case 'email':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, 'email');
                }
                break;
                
            case 'min':
                if (!empty($value) && strlen($value) < $ruleParam) {
                    $this->addError($field, 'min', $ruleParam);
                }
                break;
                
            case 'max':
                if (!empty($value) && strlen($value) > $ruleParam) {
                    $this->addError($field, 'max', $ruleParam);
                }
                break;
                
            case 'numeric':
                if (!empty($value) && !is_numeric($value)) {
                    $this->addError($field, 'numeric');
                }
                break;
                
            case 'alpha':
                if (!empty($value) && !ctype_alpha($value)) {
                    $this->addError($field, 'alpha');
                }
                break;
                
            case 'alphanumeric':
                if (!empty($value) && !ctype_alnum($value)) {
                    $this->addError($field, 'alphanumeric');
                }
                break;
                
            case 'phone':
                if (!empty($value) && !preg_match('/^[0-9]{10,15}$/', $value)) {
                    $this->addError($field, 'phone');
                }
                break;
                
            case 'url':
                if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
                    $this->addError($field, 'url');
                }
                break;
                
            case 'in':
                $allowedValues = explode(',', $ruleParam);
                if (!empty($value) && !in_array($value, $allowedValues)) {
                    $this->addError($field, 'in', implode(', ', $allowedValues));
                }
                break;
                
            case 'unique':
                // Formato: unique:tabla,columna
                if (!empty($value) && !$this->checkUnique($value, $ruleParam)) {
                    $this->addError($field, 'unique');
                }
                break;
                
            case 'confirmed':
                // Verifica que field_confirmation coincida
                $confirmField = $field . '_confirmation';
                if (!empty($value) && (!isset($this->data[$confirmField]) || $value !== $this->data[$confirmField])) {
                    $this->addError($field, 'confirmed');
                }
                break;
        }
    }
    
    /**
     * Verifica unicidad en la base de datos
     * 
     * @param mixed $value Valor a verificar
     * @param string $params Parámetros (tabla,columna)
     * @return bool
     */
    private function checkUnique($value, $params) {
        list($table, $column) = explode(',', $params);
        
        try {
            $db = Database::getInstance();
            $query = "SELECT COUNT(*) as count FROM {$table} WHERE {$column} = ?";
            $result = $db->fetchOne($query, [$value]);
            
            return $result['count'] == 0;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Agrega un error de validación
     * 
     * @param string $field Campo con error
     * @param string $rule Regla que falló
     * @param mixed $param Parámetro adicional
     */
    private function addError($field, $rule, $param = null) {
        $key = "{$field}.{$rule}";
        
        if (isset($this->customMessages[$key])) {
            $message = $this->customMessages[$key];
        } else {
            $message = $this->getDefaultMessage($field, $rule, $param);
        }
        
        $this->errors[$field] = $message;
    }
    
    /**
     * Obtiene el mensaje de error por defecto
     * 
     * @param string $field Campo
     * @param string $rule Regla
     * @param mixed $param Parámetro
     * @return string
     */
    private function getDefaultMessage($field, $rule, $param = null) {
        $fieldName = ucfirst(str_replace('_', ' ', $field));
        
        $messages = [
            'required' => "{$fieldName} es requerido",
            'email' => "{$fieldName} debe ser un email válido",
            'min' => "{$fieldName} debe tener al menos {$param} caracteres",
            'max' => "{$fieldName} no debe exceder {$param} caracteres",
            'numeric' => "{$fieldName} debe ser numérico",
            'alpha' => "{$fieldName} solo debe contener letras",
            'alphanumeric' => "{$fieldName} solo debe contener letras y números",
            'phone' => "{$fieldName} debe ser un teléfono válido",
            'url' => "{$fieldName} debe ser una URL válida",
            'in' => "{$fieldName} debe ser uno de: {$param}",
            'unique' => "{$fieldName} ya está en uso",
            'confirmed' => "{$fieldName} no coincide con la confirmación"
        ];
        
        return $messages[$rule] ?? "Error de validación en {$fieldName}";
    }
    
    /**
     * Obtiene los errores de validación
     * 
     * @return array
     */
    public function getErrors() {
        return $this->errors;
    }
    
    /**
     * Sanitiza datos de entrada
     * 
     * @param array $data Datos a sanitizar
     * @return array
     */
    public static function sanitize($data) {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $sanitized[$key] = self::sanitize($value);
            } else {
                $sanitized[$key] = htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
            }
        }
        
        return $sanitized;
    }
}
