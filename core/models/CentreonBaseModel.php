<?php

/*
 * Copyright 2015 Centreon (http://www.centreon.com/)
 * 
 * Centreon is a full-fledged industry-strength solution that meets 
 * the needs in IT infrastructure and application monitoring for 
 * service performance.
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * 
 *    http://www.apache.org/licenses/LICENSE-2.0  
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 * For more information : contact@centreon.com
 * 
 */

namespace Centreon\Models;

use Centreon\Internal\Exception;
use Centreon\Internal\Di;

/**
 * Abstract Centreon Object class
 *
 * @author sylvestre
 */
abstract class CentreonBaseModel extends CentreonModel
{
    /**
     * Table name of the object
     * @var string 
     */
    protected static $table = null;

    /**
     * Primary key name
     * @var string 
     */
    protected static $primaryKey = null;

    /**
     * Unique label field
     * @var string 
     */
    protected static $uniqueLabelField = null;
    
     /**
     * Slug field
     * @var string 
     */
    protected static $slugField = null;

    /**
     * Database logical name
     *
     * @var string
     */
    protected static $databaseName = 'db_centreon';

    /**
     * Array of relation objects 
     *
     * @var array of strings
     */
    protected static $relations = array();

    /**
     * 
     */
    const OBJ_NOT_EXIST = 'Object not in database.';

    const NO_SLUG = 'Object got no slug.';
    
    /**
     * 
     * @param array $params
     * @param array $not_null_attributes attributes that cannot be set to null
     * @param array $is_int_attribute attributes that are int based
     */
    protected static function setAttributeProps($params, &$not_null_attributes, &$is_int_attribute)
    {
        $params = (array)$params;
        if (array_search("", $params)) {
            $sql_attr = "SHOW FIELDS FROM " . static::$table;
            $res = static::getResult($sql_attr, array(), "fetchAll");
            foreach ($res as $tab) {
                if (strtoupper($tab['Null']) == 'NO') {
                    $not_null_attributes[$tab['Field']] = true;
                }
                if (strstr($tab['Type'], 'int')) {
                    $is_int_attribute[$tab['Field']] = true;
                }
            }
        }
    }

    /**
     * Used for inserting object into database
     *
     * @param array $params
     * @return int
     */
    public static function insert($params = array(), $keepPrimaryKey = false)
    {
        $db = Di::getDefault()->get(static::$databaseName);
        $sql = "INSERT INTO " . static::$table;
        $sqlFields = "";
        $sqlValues = "";
        $sqlParams = array();
        $not_null_attributes = array();
        $is_int_attribute = array();
        static::setAttributeProps($params, $not_null_attributes, $is_int_attribute);

        foreach ($params as $key => $value) {
            if (($key == static::$primaryKey && !$keepPrimaryKey) || is_null($value)) {
                continue;
            }
            if ($sqlFields != "") {
                $sqlFields .= ",";
            }
            if ($sqlValues != "") {
                $sqlValues .= ",";
            }
            $sqlFields .= "`" . $key . "`";
            $sqlValues .= "?";
            if ($value === "" && !isset($not_null_attributes[$key])) {
                $value = null;
            } elseif (!is_numeric($value) && isset($is_int_attribute[$key])) {
                $value = null;
            }
            if (is_null($value)) {
                $type = \PDO::PARAM_NULL;
            } else if (isset($is_int_attribute[$key])) {
                $type = \PDO::PARAM_INT;
            } else {
                $type = \PDO::PARAM_STR;
            }
            $sqlParams[] = array('value' => trim($value), 'type' => $type);
        }
        if ($sqlFields && $sqlValues) {
            $sql .= "(".$sqlFields.") VALUES (".$sqlValues.")";
            $stmt = $db->prepare($sql);
            $i = 1;
            foreach ($sqlParams as $v) {
                $stmt->bindValue($i, $v['value'], $v['type']);
                $i++;
            }
            $stmt->execute();
            return $db->lastInsertId(static::$table, static::$primaryKey);
        }
        return null;
    }

    /**
     * Used for deleting object from database
     *
     * @param int $objectId
     */
    public static function delete($objectId, $notFoundError = true)
    {
        $db = Di::getDefault()->get(static::$databaseName);
        $sql = "DELETE FROM  " . static::$table . " WHERE ". static::$primaryKey . " = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute(array($objectId));
        if ((1 !== $stmt->rowCount()) && $notFoundError) {
            throw new Exception(static::OBJ_NOT_EXIST);
        }
    }

    /**
     * Used for deleting object from database
     *
     * @param int $uniqueLabelFieldId
     */
    public static function deleteByUniqueLabelField($uniqueLabelFieldId)
    {
        $db = Di::getDefault()->get(static::$databaseName);
        $sql = "DELETE FROM  " . static::$table . " WHERE ". static::$uniqueLabelField . " = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute(array($uniqueLabelFieldId));
        if (1 !== $stmt->rowCount()) {
            throw new Exception(static::OBJ_NOT_EXIST);
        }
    }

    /**
     * Used for updating object in database
     *
     * @param integer $objectId
     * @param array $params
     * @return void
     */
    public static function update($objectId, $params = array())
    {
        $sql = "UPDATE " . static::$table . " SET ";
        $sqlUpdate = "";
        $sqlParams = array();
        $not_null_attributes = array();
        $is_int_attribute = array();
        static::setAttributeProps($params, $not_null_attributes, $is_int_attribute);

        foreach ($params as $key => $value) {
            if ($key == static::$primaryKey) {
                continue;
            }
            if ($sqlUpdate != "") {
                $sqlUpdate .= ",";
            }
            $paramKey = ":{$key}";
            $sqlUpdate .= '`' . $key . "` = {$paramKey} ";
            
            if ($value === "" && !isset($not_null_attributes[$key])) {
                $value = null;
            } elseif (!is_numeric($value) && isset($is_int_attribute[$key])) {
                $value = null;
            }
            
            if (is_null($value)) {
                $type = \PDO::PARAM_NULL;
            } else if (isset($is_int_attribute[$key])) {
                $type = \PDO::PARAM_INT;
            } else {
                $type = \PDO::PARAM_STR;
            }
            $sqlParams[$paramKey] = array('value' => $value, 'type' => $type);
        }

        
        
        if ($sqlUpdate) {
            $db = Di::getDefault()->get(static::$databaseName);
            $sqlParams[':source_object_id'] = array('value' => $objectId, 'type' => \PDO::PARAM_INT);
            $sql .= $sqlUpdate . " WHERE " . static::$primaryKey . " =  :source_object_id";
            $stmt = $db->prepare($sql);
            foreach ($sqlParams as $k => $v) {
                $stmt->bindParam($k, $v['value'], $v['type']);
            }
            $stmt->execute();
            if (1 !== $stmt->rowCount()) {
                throw new Exception(static::OBJ_NOT_EXIST);
            }
        }
    }

    /**
     * Used for duplicating object
     *
     * @param int $sourceObjectId
     * @param int $duplicateEntries
     * @return array List of new object id
     */
    public static function duplicate($sourceObjectId, $duplicateEntries = 1)
    {
        $db = Di::getDefault()->get(static::$databaseName);
        $sourceParams = static::getParameters($sourceObjectId, "*");
        if (false === $sourceParams) {
            throw new Exception(static::OBJ_NOT_EXIST);
        }
        if (isset($sourceParams[static::$primaryKey])) {
            unset($sourceParams[static::$primaryKey]);
        }
        /* If multiple unique field */
        if (is_array(static::$uniqueLabelField)) {
            $originalName = array();
            foreach (static::$uniqueLabelField as $uniqueField) {
                $originalName[$uniqueField] = $sourceParams[$uniqueField];
                $explodeOriginalName = explode('_', $originalName[$uniqueField]);
                $count = count($explodeOriginalName);
                if (($count > 1) && (is_numeric($explodeOriginalName[$count - 1]))) {
                    $originalName[$uniqueField] = preg_replace('/(.*)_\d+$/', '$1', $originalName[$uniqueField]);
                }
            }
        } else {
            $originalName = $sourceParams[static::$uniqueLabelField];
            $explodeOriginalName = explode('_', $originalName);
            $count = count($explodeOriginalName);
            if (($count > 1) && (is_numeric($explodeOriginalName[$count - 1]))) {
                $originalName = preg_replace('/(.*)_\d+$/', '$1', $originalName);
            }
        }
        /* Get relations */
        $firstKeyCopy = array();
        $secondKeyCopy = array();
        foreach (static::$relations as $relation) {
            $relationObj = new $relation();
            if ($relation::$firstObject == "\\".get_called_class()) {
                $firstKeyCopy[$relation] = $relationObj->getTargetIdFromSourceId(
                    $relationObj->getSecondKey(),
                    $relationObj->getFirstKey(),
                    $sourceObjectId
                );
            } elseif ($relation::$secondObject == "\\".get_called_class()) {
                $secondKeyCopy[$relation] = $relationObj->getTargetIdFromSourceId(
                    $relationObj->getFirstKey(),
                    $relationObj->getSecondKey(),
                    $sourceObjectId
                );
            }
            unset($relationObj);
        }
        $i = 1;
        $j = 1;
        /* Add the number for new entries */
        $listDuplicateId = array();
        while ($i <= $duplicateEntries) {
            /* Test if unique fields are unique */
            if (is_array(static::$uniqueLabelField)) {
                $unique = true;
                foreach (static::$uniqueLabelField as $uniqueField) {
                    $sourceParams[$uniqueField] = $originalName[$uniqueField] . '_' . $j;
                    if (false === self::isUnique($originalName[$uniqueField] . '_' . $j, 0, $uniqueField)) {
                        $unique = false;
                    }
                }
            } else {
                $unique = false;
                $sourceParams[static::$uniqueLabelField] = $originalName . '_' . $j;
                if (self::isUnique($originalName . '_' . $j, 0)) {
                    $unique = true;
                }
            }
            if ($unique) {
                $lastId = static::insert($sourceParams);
                $listDuplicateId[] = $lastId;
                $db->beginTransaction();
                foreach ($firstKeyCopy as $relation => $idArray) {
                    foreach ($idArray as $relationId) {
                        $relation::insert($lastId, $relationId);
                    }
                }
                foreach ($secondKeyCopy as $relation => $idArray) {
                    foreach ($idArray as $relationId) {
                        $relation::insert($relationId, $lastId);
                    }
                }
                $db->commit();
                $i++;
            }
            $j++;
        }
        return $listDuplicateId;
    }

    /**
     * Get object parameters
     *
     * @param integer $objectId
     * @param mixed $parameterNames
     * @return array
     */
    public static function getParameters($objectId, $parameterNames)
    {
        if (is_array($parameterNames)) {
            $params = implode(",", $parameterNames);
        } else {
            $params = $parameterNames;
        }

        if (is_array($objectId)) {
            $sql = "SELECT " . static::$primaryKey . "," . $params . " FROM " . static::$table . " WHERE ";
            $whereCaluse = array();
            foreach ($objectId as $id) {
                $whereClause[] = static::$primaryKey . ' = ? ';
            }
            $sql .= implode(' OR ', $whereClause);
            $values = $objectId;
        } else {
            $sql = "SELECT " . $params . " FROM " . static::$table . " WHERE ". static::$primaryKey . " = ?";
            $values = array($objectId);
        }

        if (isset(static::$basicFilters)) {
            foreach (static::$basicFilters as $key => $value) {
                $sql .= " AND " . $key . " LIKE ? ";
                array_push($values, $value);
            }
        }

        $result = static::getResult($sql, $values, "fetchAll");

        /* Raise exception if object doesn't exist */
        if (false === $result) {
            throw new Exception(static::OBJ_NOT_EXIST);
        }
        if (count($result) < 1) {
            throw new Exception(static::OBJ_NOT_EXIST);
        }

        if (is_array($objectId)) {
            return $result;
        } else {
            return $result[0];
        }
    }

    /**
     * 
     * @param integer $id
     * @param mixed $parameterNames
     * @return array
     */
    public static function get($id, $parameterNames = "*")
    {
        if (is_array($parameterNames)) {
            $params = implode(",", $parameterNames);
        } else {
            $params = $parameterNames;
        }

        $sql = "SELECT $params FROM " . static::$table;
        $sql .= " WHERE " . static::$primaryKey . " LIKE ? ";

        $values = array($id);

        if (isset(static::$basicFilters)) {
            foreach (static::$basicFilters as $key => $value) {
                $sql .= " AND " . $key . " LIKE ? ";
                array_push($values, $value);
            }
        }

        $result = static::getResult($sql, $values, "fetchAll");
        if (1 !== count($result)) {
            throw new Exception(static::OBJ_NOT_EXIST);
        }
        return $result[0];
    }

    /**
     * 
     * @param type $value
     * @param type $extraParams
     * @return type
     * @throws Exception
     */
    public static function getSlugByUniqueField($value,$extraParams = array())
    {    
        $db = Di::getDefault()->get(static::$databaseName);
        $slugField = self::getSlugField();
        $uniqueField = self::getUniqueLabelField();
        if(empty($slugField)){
            throw new Exception(static::NO_SLUG); 
        }
        $sql = "Select ". $slugField . " FROM " . static::$table . " WHERE " . $uniqueField ." = ? ";
        
        foreach($extraParams as $key => $param){
            $sql .= " And " . $key . " = ? ";
        }
        $stmt = $db->prepare($sql);
        $stmt->bindValue(1, $value, \PDO::PARAM_STR);
        $i = 2;
        foreach($extraParams as $param){
            $stmt->bindValue($i, $param, \PDO::PARAM_STR);
            $i++;
        }
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result[$slugField];
    }
    
    /**
     * Generic method that allows to retrieve object ids
     * from another object parameter
     *
     * @param string $paramName
     * @param array $paramValues
     * @param array $extraConditions used for precising query with AND clauses
     * @return array
     */
    public static function getIdByParameter($paramName, $paramValues = array(), $extraConditions = array(), $conditionType = '=')
    {
        $sql = "SELECT " . static::$primaryKey . " FROM " . static::$table . " WHERE ";
        $condition = "";
        if (!is_array($paramValues)) {
            $paramValues = array($paramValues);
        }

        foreach ($paramValues as $val) {
            if ($condition != "") {
                $condition .= " OR ";
            } else {
                $condition .= "(";
            }
            $condition .= $paramName . " " . $conditionType . " ? ";
        }
        if ($condition) {
            $condition .= ")";
            $sql .= $condition;

            if (is_array($extraConditions)) {
                foreach ($extraConditions as $k => $v) {
                    $sql .= " AND $k = ? ";
                    $paramValues[] = $v;
                }
            }

            if (isset(static::$basicFilters)) {
                foreach (static::$basicFilters as $key => $value) {
                    $sql .= " AND " . $key . " LIKE ? ";
                    array_push($paramValues, $value);
                }
            }

            $rows = static::getResult($sql, $paramValues, "fetchAll");
            $tab = array();
            foreach ($rows as $val) {
                $tab[] = $val[static::$primaryKey];
            }
            return $tab;
        }
        return array();
    }

    /**
     * Generic method that allows to retrieve object ids
     * from another object parameter
     *
     * @param string $name
     * @param array $args
     * @return array
     * @throws Exception
     */
    public function __call($name, $args)
    {
        if (preg_match('/^getIdBy([a-zA-Z0-9_]+)/', $name, $matches)) {
            return static::getIdByParameter($matches[1], $args);
        } else {
            throw new Exception('Unknown method');
        }
    }

    /**
     * Primary Key Getter
     *
     * @return string
     */
    public static function getPrimaryKey()
    {
        return static::$primaryKey;
    }

    /**
     * Unique label field getter
     *
     * @return string
     */
    public static function getUniqueLabelField()
    {
        return static::$uniqueLabelField;
    }
    
    /**
     * Slug field Getter
     *
     * @return string
     */
    public static function getSlugField()
    {
        return static::$slugField;
    }

    /**
     * Get relations
     *
     * @return array
     */
    public static function getRelations()
    {
        return static::$relations;
    }
    
    /**
     * 
     * @param string $uniqueFieldvalue
     * @param integer $id
     * @return boolean
     */
    public static function isUnique($uniqueFieldvalue, $id = 0, $fieldName=null)
    {
        $dbconn = Di::getDefault()->get(static::$databaseName);
        /* Test if the field name is in unique field */
        if (false === is_null($fieldName)) {
            if (is_array(static::$uniqueLabelField) && false === in_array($fieldName, static::$uniqueLabelField)) {
                throw new Exception(); // @TODO Exception text
            } elseif (is_string(static::$uniqueLabelField) && $fieldName != static::$uniqueLabelField) {
                throw new Exception(); // @TODO Exception text
            }
        }
        $columns = array();
        $unicityRequest = 'SELECT count(' . static::$primaryKey . ') as nb
            FROM ' . static::$table . '
            WHERE ' . static::$primaryKey . ' != :id AND ';
        if (false === is_null($fieldName)) {
            $unicityRequest .= $fieldName . ' = :' . $fieldName;
            $columns[] = $fieldName;
        } elseif (is_array(static::$uniqueLabelField)) {
            $unicityRequest .= "(" . join(
                ' OR ',
                array_map(
                    function($value) { return $value . ' = :' . $value; }
                    , static::$uniqueLabelField
                )
            ) . ")";
            $columns = static::$uniqueLabelField;
        } else {
            $unicityRequest .= static::$uniqueLabelField . ' = :' . static::$uniqueLabelField;
            $columns[] = static::$uniqueLabelField;
        }
        $stmt = $dbconn->prepare($unicityRequest);
        $stmt->bindParam(':id', $id, \PDO::PARAM_INT);
        foreach ($columns as $column) {
            $stmt->bindParam(':' . $column, $uniqueFieldvalue);
        }
        $stmt->execute();
        $resultUnique = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if ($resultUnique['nb'] > 0) {
            return false;
        }
        return true;
    }

    /**
     * Get Table Name
     *
     * @return string
     */
    public static function getTableName()
    {
        return static::$table;
    }

    /**
     * Get columns
     *
     * @return array
     */
    public static function getColumns()
    {
        $db = Di::getDefault()->get(static::$databaseName);
        $stmt = $db->prepare("SHOW COLUMNS FROM " . static::$table);
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $result = array();
        foreach ($rows as $row) {
            $result[] = $row['Field'];
        }
        return $result;
    }
}
