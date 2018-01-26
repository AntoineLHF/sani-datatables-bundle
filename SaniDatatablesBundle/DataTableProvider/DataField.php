<?php

namespace Sanilea\SaniDatatablesBundle\DataTableProvider;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class DataField.
 *
 * Represent an independent data field.
 * It's a set of rules and configuration defining:
 * - if the field is filterable, and how (SQL / PHP) if it does
 * - if the field is orderable and how (SQL / PHP) if it does
 */
class DataField
{
    const FILTERING_METHOD_IN_SQL_REQUEST = 'FILTERING_METHOD_IN_SQL_REQUEST';
    const FILTERING_METHOD_PHP_AUTO = 'FILTERING_METHOD_PHP_AUTO';
    const FILTERING_METHOD_PHP_CUSTOM = 'FILTERING_METHOD_PHP_CUSTOM';

    const ORDERING_METHOD_IN_SQL_REQUEST = 'ORDERING_METHOD_IN_SQL_REQUEST';
    const ORDERING_METHOD_PHP_AUTO = 'ORDERING_METHOD_PHP_AUTO';
    const ORDERING_METHOD_PHP_CUSTOM = 'ORDERING_METHOD_PHP_CUSTOM';

    /**
     * Name of the input field (as sent by client).
     *
     * @var string|''
     */
    protected $name;

    /**
     * Is this Datafield enabled.
     *
     * @var bool|true
     */
    protected $enabled;

    /**
     * Is the field filterable by client.
     *
     * @var bool|false
     */
    protected $filterable;

    /**
     * Is the field excluded from global filtering (overridden by $this->filterable).
     *
     * @var bool|false
     */
    protected $excludedFromGlobalFiltering;

    /**
     * Is the field orderable by client.
     *
     * @var bool|false
     */
    protected $orderable;

    /**
     * Default filtering operator.
     *
     * @var string|'LIKE'
     */
    protected $defaultFilteringOperator;

    /**
     * Filtering authorization restricting how the client can filter this DataField.
     *
     * @var FilteringAuthorization|FilteringAuthorization::createCommonStringAuthorization()
     */
    protected $filteringAuthorization;

    /**
     * Custom function for transforming input Filter value
     * Must return the transformed value.
     *
     * @var callable|function( DataField, Filter $filter )
     */
    protected $filterTransformValueFunction;

    /**
     * Method used to filter this DataField.
     * If it's possible, should be done directly in SQL for efficiency (self::FILTERING_METHOD_IN_SQL_REQUEST).
     * If it cannot, use self::FILTERING_METHOD_PHP_AUTO or self::FILTERING_METHOD_PHP_CUSTOM.
     * Note: field ignored if $this->filterable = false.
     *
     * @var string|self::FILTERING_METHOD_IN_SQL_REQUEST
     */
    protected $filteringMethod;

    /**
     * Method used to order this DataField.
     * If it's possible, should be done directly in SQL for efficiency (self::ORDERING_METHOD_IN_SQL_REQUEST).
     * If it cannot, use self::ORDERING_METHOD_PHP_AUTO or self::ORDERING_METHOD_PHP_CUSTOM.
     * Note: field ignored if $this->orderable = false.
     *
     * @var string|self::ORDERING_METHOD_CUSTOM
     */
    protected $orderingMethod;

    /**
     * Unique expression used for all reference of this DataField in the SQL request.
     * Use only if the same expression can be use the same way in all clause type (SELECT / WHERE / ORDER BY / HAVING)
     * Usefull for simple SQL queries.
     *
     * @var string|''
     */
    protected $sqlMainFieldName;

    /**
     * Custom function for modifying SELECT clause in SQL request for this DataField.
     *
     * @var callable|function( DataField, QueryBuilder $qBuilder )
     */
    protected $sqlSelectingCustomFunction;

    /**
     * Custom function for modifying WHERE clause in SQL request for this DataField.
     *
     * @var callable|function( DataField, Filter $filter, QueryBuilder $qBuilder )
     */
    protected $sqlFilteringCustomFunction;

    /**
     * Custom function for modifying ORDER BY clause in SQL request for this DataField.
     *
     * @var callable|function( DataField, Order $order, QueryBuilder $qBuilder )
     */
    protected $sqlOrderingCustomFunction;

    /**
     * Custom function for transforming value before filtering via PHP
     * Must return the transformed value.
     *
     * @var callable|function( DataField, $value, Row $row )
     */
    protected $phpFilteringTransformValueFunction;

    /**
     * Custom function for filtering a field via PHP.
     *
     * @var callable|function( DataField, Filter $filter, $value, Row $row )
     */
    protected $phpFilteringCustomFunction;

    /**
     * Custom function for ordering a field via PHP.
     *
     * @var callable|function( DataField, Order $order, $valueA, $valueB )
     */
    protected $phpOrderingCustomFunction;

    /**
     * Custom function for transforming value before sorting via PHP
     * Must return the transformed value.
     *
     * @var callable|function( DataField, $value )
     */
    protected $phpOrderingTransformValueFunction;

    /**
     * an array of html attributes to put on the <th> section
     *
     * @var array
     */
    protected $attributes;


    /**
     * Returns true if the attribute is defined.
     *
     * @param string $name The attribute name
     *
     * @return bool true if the attribute is defined, false otherwise
     */
    public function hasAttribute($name)
    {
        return array_key_exists($name, $this->attributes);
    }

    /**
     * @return array
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Sets multiple attributes.
     *
     * @param array $attributes The attributes
     */
    public function setAttributes($attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Sets an attribute by name.
     *
     * @param string $name  The attribute name
     * @param mixed  $value The attribute value
     */
    public function setAttribute($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Returns an attribute by name.
     *
     * @param string $name    The attribute name
     * @param mixed  $default Default value is the attribute doesn't exist
     *
     * @return mixed The attribute value
     */
    public function getAttribute($name, $default = null)
    {
        return $this->hasAttribute($name) ? $this->attributes[$name] : $default;
    }

    /**
     * Usage 1 (common usage) :
     * __construct( string $name, array $options )
     *   $name: name of the DataField
     *   $options: settings for all properties.
     *
     * Usage 2 (shortcut for simple SQL queries) :
     * __construct( string $name, string $sqlMainFieldName )
     *   $name: name of the DataField
     *   $sqlMainFieldName: the SQL expression mapping this DataField in SQL request
     *
     *
     * @param $name
     * @param array $options
     */
    public function __construct($name, $options = array())
    {
        $this->setName($name);

        $resolver = new OptionsResolver();

        $defaults = array(
            'enabled' => true,
            'filterable' => true,
            'excluded-from-global-filtering' => false,
            'filter-transform-value-function' => null,
            'orderable' => true,
            'default-filtering-operator' => 'LIKE',
            'filtering-authorization' => FilteringAuthorization::createCommonStringAuthorization(),
            'filtering-method' => self::FILTERING_METHOD_IN_SQL_REQUEST,
            'ordering-method' => self::ORDERING_METHOD_IN_SQL_REQUEST,
            'sql-main-field-name' => null,
            'sql-selecting-custom-function' => null,
            'sql-filtering-custom-function' => null,
            'sql-ordering-custom-function' => null,
            'php-filtering-transform-value-function' => null,
            'php-filtering-custom-function' => null,
            'php-ordering-transform-value-function' => null,
            'php-ordering-custom-function' => null,
        );

        if (is_string($options)) {
            $options = array('sql-main-field-name' => $options);
        }

        $resolver->setDefaults($defaults);
        $settings = $resolver->resolve($options);

        $this->init($settings);
    }

    public function init($options)
    {
        $this->setEnabled($options['enabled']);
        $this->setFilterable($options['filterable']);
        $this->setExcludedFromGlobalFiltering($options['excluded-from-global-filtering']);
        $this->setFilterTransformValueFunction($options['filter-transform-value-function']);
        $this->setOrderable($options['orderable']);
        $this->setDefaultFilteringOperator($options['default-filtering-operator']);
        $this->setFilteringAuthorization($options['filtering-authorization']);
        $this->setFilteringMethod($options['filtering-method']);
        $this->setOrderingMethod($options['ordering-method']);
        $this->setSqlMainFieldName($options['sql-main-field-name']);
        $this->setSqlSelectingCustomFunction($options['sql-selecting-custom-function']);
        $this->setSqlFilteringCustomFunction($options['sql-filtering-custom-function']);
        $this->setSqlOrderingCustomFunction($options['sql-ordering-custom-function']);
        $this->setPhpFilteringCustomFunction($options['php-filtering-custom-function']);
        $this->setPhpFilteringTransformValueFunction($options['php-ordering-transform-value-function']);
        $this->setPhpOrderingCustomFunction($options['php-ordering-custom-function']);
        $this->setPhpOrderingTransformValueFunction($options['php-ordering-transform-value-function']);
    }

    public function isFilterAuthorized(Filter $filter)
    {
        if ($this->isExcludedFromGlobalFiltering() && $filter->getType() == Filter::TYPE_GLOBAL) {
            return false;
        }

        if (!$this->getFilteringAuthorization()->isAuthorized($filter)) {
            return false;
        }

        return true;
    }

    /**
     * Returns if this Datafield is enabled or not.
     *
     * @return bool true if enabled, false otherwise
     */
    public function getEnabled()
    {
        return $this->enabled;
    }

    /**
     * Returns if this Datafield is enabled or not
     * Equivalent to getEnabled( ).
     *
     * @return bool true if enabled, false otherwise
     */
    public function isEnabled()
    {
        return $this->getEnabled();
    }

    /**
     * Set this DataField enabled or disabled.
     *
     * @param bool $enabled
     *
     * @return DataField
     */
    public function setEnabled($enabled)
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function isFilterable()
    {
        return $this->filterable === true;
    }

    public function setFilterable($filterable)
    {
        $this->filterable = $filterable;

        return $this;
    }

    public function isExcludedFromGlobalFiltering()
    {
        return $this->excludedFromGlobalFiltering === true;
    }

    public function setExcludedFromGlobalFiltering($excludedFromGlobalFiltering)
    {
        $this->excludedFromGlobalFiltering = $excludedFromGlobalFiltering;

        return $this;
    }

    public function getFilterTransformValueFunction()
    {
        return $this->filterTransformValueFunction;
    }

    public function setFilterTransformValueFunction($filterTransformValueFunction)
    {
        $this->filterTransformValueFunction = $filterTransformValueFunction;

        return $this;
    }

    public function excludeFromGlobalFiltering()
    {
        return $this->setExcludedFromGlobalFiltering(true);
    }

    public function isOrderable()
    {
        return $this->orderable === true;
    }

    public function setOrderable($orderable)
    {
        return $this->orderable = $orderable;
    }

    public function getDefaultFilteringOperator()
    {
        return $this->defaultFilteringOperator;
    }

    public function setDefaultFilteringOperator($defaultFilteringOperator)
    {
        $this->defaultFilteringOperator = $defaultFilteringOperator;

        return $this;
    }

    public function getFilteringAuthorization()
    {
        return $this->filteringAuthorization;
    }

    public function setFilteringAuthorization(FilteringAuthorization $filteringAuthorization)
    {
        $this->filteringAuthorization = $filteringAuthorization;

        return $this;
    }

    public function getFilteringMethod()
    {
        return $this->filteringMethod;
    }

    public function setFilteringMethod($filteringMethod)
    {
        $this->filteringMethod = $filteringMethod;

        return $this;
    }

    public function getOrderingMethod()
    {
        return $this->orderingMethod;
    }

    public function setOrderingMethod($orderingMethod)
    {
        $this->orderingMethod = $orderingMethod;

        return $this;
    }

    public function getSqlMainFieldName()
    {
        return $this->sqlMainFieldName;
    }

    public function setSqlMainFieldName($sqlGlobalFieldName)
    {
        $this->sqlMainFieldName = $sqlGlobalFieldName;

        return $this;
    }

    public function getSqlSelectingCustomFunction()
    {
        return $this->sqlSelectingCustomFunction;
    }

    public function setSqlSelectingCustomFunction($sqlSelectingCustomFunction)
    {
        $this->sqlSelectingCustomFunction = $sqlSelectingCustomFunction;

        return $this;
    }

    public function getSqlFilteringCustomFunction()
    {
        return $this->sqlFilteringCustomFunction;
    }

    public function setSqlFilteringCustomFunction($sqlFilteringCustomFunction)
    {
        $this->sqlFilteringCustomFunction = $sqlFilteringCustomFunction;

        return $this;
    }

    public function getSqlOrderingCustomFunction()
    {
        return $this->sqlOrderingCustomFunction;
    }

    public function setSqlOrderingCustomFunction($sqlOrderingCustomFunction)
    {
        $this->sqlOrderingCustomFunction = $sqlOrderingCustomFunction;

        return $this;
    }

    public function getPhpFilteringCustomFunction()
    {
        return $this->phpFilteringCustomFunction;
    }

    public function setPhpFilteringCustomFunction($phpFilteringCustomFunction)
    {
        $this->phpFilteringCustomFunction = $phpFilteringCustomFunction;

        return $this;
    }

    public function getPhpFilteringTransformValueFunction()
    {
        return $this->phpFilteringTransformValueFunction;
    }

    public function setPhpFilteringTransformValueFunction($phpFilteringTransformValueFunction)
    {
        $this->phpFilteringTransformValueFunction = $phpFilteringTransformValueFunction;

        return $this;
    }

    public function getPhpOrderingCustomFunction()
    {
        return $this->phpOrderingCustomFunction;
    }

    public function setPhpOrderingCustomFunction($phpOrderingCustomFunction)
    {
        $this->phpOrderingCustomFunction = $phpOrderingCustomFunction;

        return $this;
    }

    public function getPhpOrderingTransformValueFunction()
    {
        return $this->phpOrderingTransformValueFunction;
    }

    public function setPhpOrderingTransformValueFunction($phpOrderingCustomFunction)
    {
        $this->phpOrderingTransformValueFunction = $phpOrderingCustomFunction;

        return $this;
    }
}
