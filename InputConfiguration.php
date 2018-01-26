<?php

namespace App\DataTableProvider;

/**
 * Class InputConfiguration.
 *
 * Represent a user request to a DataProvider (formalised)
 */
class InputConfiguration
{
    /**
     * @var array|array()
     */
    protected $parameters;

    /**
     * @var Filter[]|array()
     */
    protected $filters;

    /**
     * @var Order[]|array()
     */
    protected $orders;

    /**
     * @var int|null
     */
    protected $pagingLimit;

    /**
     * @var int|0
     */
    protected $pagingOffset;

    /**
     * @var array|null
     */
    protected $requiredDataSelectors;


    public function __construct()
    {
        $this->init();
    }

    protected function init()
    {
        $this->parameters = array();
        $this->filters = array();
        $this->orders = array();
        $this->pagingLimit = null;
        $this->pagingOffset = 0;
        $this->requiredDataSelectors = null;
    }

    /**
     * Creates a new object using raw input.
     *
     * @param $inputOptions options as sent by client
     *
     * @return InputConfiguration|null
     */
    public static function fromRawInput($inputOptions)
    {
        $calledClassName = get_called_class();

        $inputConfiguration = new $calledClassName();

        $inputConfiguration->loadRawInputs($inputOptions);

        return $inputConfiguration;
    }

    /**
     * Load the base input options.
     Raw Input Example :
     
     array:5 [
     "filters" => array:1 [
     "by_field" => array:1 [
     "data-collection@status_text" => array:1 [
     0 => array:2 [
     "operator" => "CONTAINS"
     "value" => "bla"
     ]
     ]
     ]
     ]
     "orders" => array:3 [
     0 => array:2 [
     "field" => "data-collection@id"
     "dir" => "asc"
     ]
     1 => array:2 [
     "field" => "test@nom"
     "dir" => "asc"
     ]
     2 => array:2 [
     "field" => "test@prenom"
     "dir" => "asc"
     ]
     ]
     "paging_offset" => "0"
     "paging_limit" => "10"
     "custom_parameters" : {
     "my_custom_parameter_1" => "1430124617236",
     "my_custom_parameter_2" => "hello"
     }
     ]
     * @param array $inputOptions
     */
    public function loadRawInputs($inputOptions)
    {
        if (is_array($inputOptions)) {
            // Read filters
            if (isset($inputOptions['filters']) && is_array($inputOptions['filters'])) {
                $filters = $inputOptions['filters'];

                if (isset($filters['by_field']) && is_array($filters['by_field'])) {
                    foreach ($filters['by_field'] as $fieldName => $thisFieldFilters) {
                        if (is_array($thisFieldFilters)) {
                            foreach ($thisFieldFilters as $filter) {
                                $oFilter = Filter::fromRawInput($filter);
                                if ($oFilter !== null) {
                                    $oFilter->setFieldName($fieldName);
                                    $oFilter->setType(Filter::TYPE_FOR_DATAFIELD);
                                    $this->addFilter($oFilter);
                                }
                            }
                        }
                    }
                }

                if (isset($filters['global']) && is_array($filters['global'])) {
                    foreach ($filters['global'] as $filter) {
                        $oFilter = Filter::fromRawInput($filter);

                        if ($oFilter !== null) {
                            $oFilter->setType(Filter::TYPE_GLOBAL);
                            $this->addFilter($oFilter);
                        }
                    }
                }
            }

            // Read orders
            if (isset($inputOptions['orders']) && is_array($inputOptions['orders'])) {
                foreach ($inputOptions['orders'] as $order) {
                    $oOrder = Order::fromRawInput($order);

                    if ($oOrder !== null) {
                        $this->addOrder($oOrder);
                    }
                }
            }

            // Read custom parameters
            if (array_key_exists('custom_parameters', $inputOptions)) {
                $this->setParameters($inputOptions['custom_parameters']);
            }

            // Read paging limit
            if (array_key_exists('paging_limit', $inputOptions) && is_numeric($inputOptions['paging_limit']) && $inputOptions['paging_limit'] >= 0) {
                $this->setPagingLimit($inputOptions['paging_limit']);
            }

            // Read paging offset
            if (array_key_exists('paging_offset', $inputOptions) && is_numeric($inputOptions['paging_offset']) && $inputOptions['paging_offset'] >= 0) {
                $this->setPagingOffset($inputOptions['paging_offset']);
            }

            // Required data
            if ( array_key_exists('required_data', $inputOptions) && is_array($inputOptions['required_data']) ) {
                $this->setRequiredDataSelectors($inputOptions['required_data']);
            }
        }
    }

    /**
     * @param String $parameterName
     *
     * @return mixed parameter value
     */
    public function getParameter($parameterName, $default = null)
    {
        if (!array_key_exists($parameterName, $this->parameters)) {
            return $default;
        }

        return $this->parameters[$parameterName];
    }

    /**
     * @return array all parameters
     */
    public function getParameters()
    {
        return $this->parameters;
    }

    /**
     * @param array $parameters
     *
     * @return InputConfiguration
     */
    public function setParameters($parameters)
    {
        $this->parameters = $parameters;

        return $this;
    }

    /**
     * @param array $parameters
     */
    public function setParameter($parameterName, $parameterValue)
    {
        $this->parameters[$parameterName] = $parameterValue;

        return $this;
    }

    /**
     * @return Filter[]
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @param string $fieldName
     *
     * @return Filter[]
     */
    public function getFiltersForDataField($fieldName)
    {
        $thisFieldNameFilters = array();

        foreach ($this->filters as $filter) {
            if ($filter->getType() == Filter::TYPE_FOR_DATAFIELD) {
                if ($filter->getFieldName() == $fieldName) {
                    $thisFieldNameFilters[] = $filter;
                }
            }
        }

        return $thisFieldNameFilters;
    }

    /**
     * @return Filter[]
     */
    public function getGlobalFilters()
    {
        $thisFieldNameFilters = array();

        foreach ($this->filters as $filter) {
            if ($filter->getType() == Filter::TYPE_GLOBAL) {
                $thisFieldNameFilters[] = $filter;
            }
        }

        return $thisFieldNameFilters;
    }

    /**
     * @param Filter $filter
     *
     * @return InputConfiguration
     */
    public function addFilter(Filter $filter)
    {
        $this->filters[] = $filter;

        return $this;
    }

    /**
     * @param Filter[] $filters
     *
     * @return InputConfiguration
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;

        return $this;
    }

    /**
     * @return Order[]
     */
    public function getOrders()
    {
        return $this->orders;
    }

    /**
     * @param Order $order
     */
    public function addOrder($order)
    {
        $this->orders[] = $order;
    }

    /**
     * @param Order[] $orders
     *
     * @return InputConfiguration
     */
    public function setOrders($orders)
    {
        $this->orders = $orders;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getPagingLimit()
    {
        return $this->pagingLimit;
    }

    /**
     * @return bool
     */
    public function hasPagingLimit()
    {
        return ($this->pagingLimit !== null);
    }

    /**
     * @param int|null $pagingLimit
     *
     * @return InputConfiguration
     */
    public function setPagingLimit($pagingLimit)
    {
        $this->pagingLimit = $pagingLimit;

        return $this;
    }

    /**
     * @return int
     */
    public function getPagingOffset()
    {
        return $this->pagingOffset;
    }

    /**
     * @param int $pagingOffset
     *
     * @return InputConfiguration
     */
    public function setPagingOffset($pagingOffset)
    {
        $this->pagingOffset = $pagingOffset;

        return $this;
    }

    /**
     * @return string[]
     */
    public function hasRequiredDataSelectors()
    {
        return ($this->getRequiredDataSelectors() !== null);
    }

    /**
     * @return string[]
     */
    public function getRequiredDataSelectors()
    {
        return $this->requiredDataSelectors;
    }

    /**
     * @param string[] $pagingOffset
     *
     * @return InputConfiguration
     */
    public function setRequiredDataSelectors($requiredDataSelectors)
    {
        $this->requiredDataSelectors = $requiredDataSelectors;

        return $this;
    }

    /**
     * @param string $order
     */
    public function addRequiredDataSelector($requiredDataSelector)
    {
        $this->requiredDataSelectors[] = $requiredDataSelector;
    }
}
