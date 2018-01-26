<?php

namespace App\DataTableProvider;

use Doctrine\ORM\QueryBuilder;

/**
 * Class Filter.
 *
 * Represent a filter request submitted by client (formalised)
 */
class Filter
{
    const TYPE_FOR_DATAFIELD = 'TYPE_FOR_DATAFIELD'; // The filter is linked to a specific DataField
    const TYPE_GLOBAL = 'TYPE_GLOBAL'; // The filter is global (for all DataFields)

    protected static $availableOperators = array(
        '=',  '!=',
        '>', '<', '>=', '<=',
        'LIKE','!LIKE',
        'IS_NULL','!IS_NULL',
        'IN', '!IN',
        'BETWEEN', '!BETWEEN',
    );

    /**
     * The name of the field linked to this filter (if self::TYPE_FOR_DATAFIELD), or null if global (self::TYPE_GLOBAL).
     *
     * @var string|null
     */
    protected $fieldName;

    /**
     * Is this Filter enabled // TODO ! not used atm.
     *
     * @var bool|true
     */
    protected $enabled;

    /**
     * The operator ('>', '<', 'LIKE', etc...).
     *
     * @var string|null
     */
    protected $operator;

    /**
     * The value (can be any scalar or array).
     *
     * @var mixed|null
     */
    protected $value;

    /**
     * The scope (array of fieldnames or 'AUTO') for global filters
     * It specifies the fieldnames on which the global filter should apply.
     * This DOES NOT overrides restrictives Filters FilteringAuthorizations, but overrides extensives ones
     * (if FilteringAuthorization "forbids" global search, scope will have no effect, but if FilteringAuthorization "allows", scope can exclude a DataField from global filtering).
     *
     * @var array|'AUTO'
     */
    protected $scope;

    /**
     * The filter type (self::TYPE_FOR_DATAFIELD or self::TYPE_GLOBAL).
     *
     * @var string|null
     */
    protected $type;

    public function __construct()
    {
        $this->init();
    }

    protected function init()
    {
        $this->enabled = true;
        $this->operator = null;
        $this->value = null;
        $this->scope = 'AUTO';
    }

    /**
     * Creates a self object using raw input.
     *
     * @param $rawInput (as sent by client DataTable)
     *
     * @return Filter|null
     */
    public static function fromRawInput($rawInput)
    {
        if (!array_key_exists('operator', $rawInput) || !is_string($rawInput['operator']) || !in_array($rawInput['operator'], self::$availableOperators)) {
            $rawInput['operator'] = 'AUTO';
        }
        if (!array_key_exists('scope', $rawInput)) {
            $rawInput['scope'] = 'AUTO';
        }
        if (is_string($rawInput['scope'])) {
            if ($rawInput['scope'] != 'AUTO') {
                $rawInput['scope'] = array($rawInput['scope']);
            }
        }
        if (!is_array($rawInput['scope'])) {
            $rawInput['scope'] = 'AUTO';
        }
        if (!self::isValidRawInput($rawInput)) {
            return;
        }

        $filter = new self();

        $filter->setOperator($rawInput['operator']);
        $filter->setValue($rawInput['value']);
        $filter->setScope($rawInput['scope']);
        return $filter;
    }

    /**
     * @param $rawInput (as sent by client DataTable)
     *
     * @return bool true if $rawInput is correct, false otherwise
     */
    public static function isValidRawInput($rawInput)
    {
        if (!is_array($rawInput)) {
            return false;
        }

        if (array_key_exists('operator', $rawInput) && in_array($rawInput['operator'],array('IS_NULL','!IS_NULL'))) {
            return true;
        }

        if (!array_key_exists('value', $rawInput)) {
            return false;
        }

        if ($rawInput['value'] == '') {
            return false;
        }

        if (!array_key_exists('operator', $rawInput) || !is_string($rawInput['operator']) || !in_array($rawInput['operator'], self::$availableOperators)) {
            $rawInput['operator'] = 'AUTO';
        }

        if (!self::isCompatible($rawInput['operator'], $rawInput['value'])) {
            return false;
        }

        return true;
    }

    public static function isCompatible($operator, $value)
    {
        switch ($operator) {
            case 'AUTO':
                return true;
                break;
            case '=':
            case '!=':
                if (!is_scalar($value) && $value !== null ) {
                    return false;
                }
                break;

            case '>':
            case '<':
            case '>=':
            case '<=':
            case 'LIKE':
            case '!LIKE':
                if (!is_scalar($value)) {
                    return false;
                }
                if ($value == '') {
                    return false;
                }
                break;

            case 'IN':
            case '!IN':
                if (!is_array($value) || empty($value)) {
                    return false;
                }
                break;

            case 'BETWEEN':
            case '!BETWEEN':
                if (!is_array($value) || count($value) != 2) {
                    return false;
                }
                break;
            case 'IS_NULL':
            case '!IS_NULL':
                break;
        }

        return true;
    }

    /**
     * @param QueryBuilder $qBuilder
     * @param string       $operandExpression
     * @param DataField    $field
     *
     * @return string Name of the condition added to the query builder or null if nothing added
     */
    public function applyToQuery(QueryBuilder $qBuilder, $operandExpression, $field = null)
    {
        $conditionName = uniqid();
        $parameterName = uniqid("param");

        $operator = $this->getOperator();
        if ($operator == 'AUTO') {
            if ($field !== null && $field->getDefaultFilteringOperator() !== null) {
                $operator = $field->getDefaultFilteringOperator();
            } else {
                return; // We do not exclude any rows
            }
        }

        $value = $this->getValue();

        if ($field !== null ) {
            $filterTransformValueFunction = $field->getFilterTransformValueFunction();
            if ($filterTransformValueFunction != null && is_callable($filterTransformValueFunction)) {
                $value = $filterTransformValueFunction($field, $this);
            }
        }

        if (!self::isCompatible($operator, $value)) {
            return; // We do not exclude any rows
        }

        $sqlCS = function ($expression) {
            return $expression;
            //TODO ALH: Voir pourquoi cette conversion était nécessaire (probablement dans le cas d'utilisationde car spéciaux pour le nomage d'un champ? )
            return '( CONVERT('.$expression.' using utf8) COLLATE utf8_bin )';
        };
        //TODO ALH: Voir si les conditionName étaient utilisés pour des where combinés
        switch ($operator) {
            case '>':
            case '<':
            case '>=':
            case '<=':
                $qBuilder->andWhere($sqlCS($operandExpression). $operator. ' :'.$parameterName);
                $qBuilder->setParameter($parameterName, $value);
                break;
            case '=':
                $qBuilder->andWhere($sqlCS($operandExpression). $operator. ' :'.$parameterName);
                $qBuilder->setParameter($parameterName, $value);
                break;
            case '!=':
                $qBuilder->andWhere($sqlCS($operandExpression). $operator. ' :'.$parameterName);
                $qBuilder->setParameter($parameterName, $value);
                break;
            case 'IS_NULL':
                $qBuilder->andWhere($qBuilder->expr()->isNull($sqlCS($operandExpression)));
                break;
            case '!IS_NULL':
                $qBuilder->andWhere($qBuilder->expr()->isNotNull($sqlCS($operandExpression)));
                break;
            case 'LIKE':
                if(!strpos($value,'%')){
                    $value = '%'.$value.'%';
                }
                $qBuilder->andWhere($qBuilder->expr()->like($sqlCS($operandExpression), ' :'.$parameterName));
                $qBuilder->setParameter($parameterName,$value);
                break;
            case '!LIKE':
                if(!strpos($value,'%')){
                    $value = '%'.$value.'%';
                }
                $qBuilder->andWhere($qBuilder->expr()->notLike($sqlCS($operandExpression), ' :'.$parameterName));
                $qBuilder->setParameter($parameterName,$value);
                break;
            case 'IN':
                $qBuilder->andWhere($qBuilder->expr()->in($sqlCS($operandExpression), $value));
                break;
            case '!IN':
                $qBuilder->andWhere($qBuilder->expr()->notIn($sqlCS($operandExpression), $value));
                break;
            case 'BETWEEN':
                $qBuilder->andWhere($qBuilder->expr()->between($sqlCS($operandExpression), ':start'.$parameterName, ':end'.$parameterName));
                $qBuilder->setParameter('start'.$parameterName,$value[0]);
                $qBuilder->setParameter('end'.$parameterName,$value[1]);
                break;
        }
        return $conditionName;
    }

    /***
     * Normalize characters for filtering or sorting as SQL COLLATE utf8_bin would have done it
     *
     * @param string $str
     * @return string normalized string (lower case characters without accents, cedilla or ligature)
     */
    protected static function normalizeCharacters($str)
    {
        $acc = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
        $no_acc = 'aaaaaaaceeeeiiiidnoooooouuuuybsaaaaaaaceeeeiiiidnoooooouuuyybyRr';
        $str = utf8_decode($str);
        $str = strtr($str, utf8_decode($acc), $no_acc);

        return strtolower(utf8_encode($str));
    }

    /**
     * @param string    $fieldValue
     * @param DataField $field
     *
     * @return bool true if filter accepts the $fieldValue, false otherwise
     */
    public function checkFiltering($fieldValue, $field = null)
    {
        $operator = $this->getOperator();
        if ($operator == 'AUTO') {
            if ($field->getDefaultFilteringOperator() !== null) {
                $operator = $field->getDefaultFilteringOperator();
            } else {
                return false;
            }
        }

        $value = $this->getValue();

        $filterTransformValueFunction = $field->getFilterTransformValueFunction();
        if ($filterTransformValueFunction != null && is_callable($filterTransformValueFunction)) {
            $value = $filterTransformValueFunction($field, $this);
        }

        if (!self::isCompatible($operator, $value)) {
            return true; // We do not exclude any row
        }

        switch ($operator) {
            case '=':
                return ($fieldValue == $value);
                break;
            case '>':
                return ($fieldValue > $value);
                break;
            case '<':
                return ($fieldValue < $value);
                break;
            case '>=':
                return ($fieldValue >= $value);
                break;
            case '<=':
                return ($fieldValue <= $value);
                break;
            case '!=':
                return ($fieldValue != $value);
                break;
            case 'LIKE':
                return (strpos($fieldValue, $value) !== false);
                break;
            case 'IN':
                return in_array($fieldValue, $value);
                break;
            case '!IN':
                return !in_array($fieldValue, $value);
                break;
            case 'BETWEEN':
                return ($fieldValue >= $value[0] && $fieldValue <= $value[1]);
                break;
            case '!BETWEEN':
                return !($fieldValue >= $value[0] && $fieldValue <= $value[1]);
                break;
            case 'IS_NULL':
            case '!IS_NULL':
                break;
        }
    }

    public function isDataFieldInScope(DataField $field)
    {
        if ($this->getType() != self::TYPE_GLOBAL) {
            return true;
        }

        $scope = $this->getScope();

        if (is_string($scope)) {
            if ($this->getScope() == 'AUTO') {
                return true;
            }
        }

        return in_array($field->getName(), $scope);
    }

    public function getEnabled()
    {
        return $this->enabled;
    }

    public function isEnabled()
    {
        return $this->getEnabled();
    }

    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;

        return $this;
    }

    /**
     * @return string
     */
    public function getFieldName()
    {
        return $this->fieldName;
    }

    /**
     * @param string $fieldName
     *
     * @return Filter
     */
    public function setFieldName($fieldName)
    {
        $this->fieldName = $fieldName;

        return $this;
    }

    /**
     * @return string
     */
    public function getOperator()
    {
        return $this->operator;
    }

    /**
     * @param string $fieldName
     *
     * @return Filter
     */
    public function setOperator($operator)
    {
        $this->operator = $operator;

        return $this;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string $fieldName
     *
     * @return Filter
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getScope()
    {
        return $this->scope;
    }

    /**
     * @param mixed $scope
     *
     * @return Filter
     */
    public function setScope($scope)
    {
        $this->scope = $scope;

        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $fieldName
     *
     * @return Filter
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }
}
