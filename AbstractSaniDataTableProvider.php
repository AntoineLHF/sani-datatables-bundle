<?php

namespace App\DataTableProvider;

use Database\ActiveRecord;
use Database\Exception\ActiveRecordException;
use DH\BootstrapBundle\Helper\ArrayHelper;
use Doctrine\Bundle\DoctrineBundle\Twig\DoctrineExtension;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authorization\AuthorizationChecker;

/**
 * Class AbstractSaniDataTableProvider.
 *
 * Main DataTableProvider class
 * Child classes must implements:
 *   ->configure()
 *   ->generateRows()
 *   ->getFormattedOutput()
 */
abstract class AbstractSaniDataTableProvider
{
    const GLOBAL_FILTER_RESULT_ADDITIONAL_COLUMN_NAME = 'dt_provider_global_filter_result';
    const ROW_META__SQL_GLOBAL_FILTER_ALREADY_ACCEPTED_ROW = 'sqlGlobalFilterAlreadyAcceptedRow';

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var TranslatorInterface
     */
    protected $translator;

    /**
     * @var EntityManagerInterface
     */
    protected $entity_manager;

    /**
     * Input Configuration.
     *
     * @var InputConfiguration|null
     */
    protected $inputConfiguration;

    /**
     * UserDefined DataFields.
     *
     * @var DataField[]|null
     */
    protected $dataFields;

    /**
     * Rows to return to client.
     *
     * @var Row[]|null
     */
    protected $rows;

    /**
     * Total row count to return to client.
     * Note: it is possible not to specify this, for performance issues.
     *
     * @var int|null
     */
    protected $totalRowsCount;

    /**
     * Filtered row count to return to client.
     *
     * @var int|0
     */
    protected $filteredRowsCount;

    /**
     * @var array|null
     */
    protected $defaultRequiredDataSelectors;

    /**
     * Any data that user want to add in the final output
     *
     * @var array
     */
    protected $userOutputData;

    public function __construct(RouterInterface $router, TranslatorInterface $translator, EntityManagerInterface $entity_manager)
    {
        $this->router = $router;
        $this->translator = $translator;
        $this->em = $entity_manager;

        $this->init();
        $this->configure();
    }

    protected function init()
    {
        $this->rows = array();
        $this->dataFields = array();
        $this->inputConfiguration = null;
        $this->totalRowsCount = null;
        $this->filteredRowsCount = 0;
        $this->defaultRequiredDataSelectors = array( '**' );
        $this->userOutputData = array( );

        return $this;
    }


    /**
     * @return DataField[]
     */
    public function getDataFields()
    {
        return $this->dataFields;
    }

    protected function getDataFieldsHavingEnabledState($enabledState)
    {
        $statedFields = array();

        $fields = $this->getDataFields();
        foreach ($fields as $field) {
            if ($field->getEnabled() == $enabledState) {
                $statedFields[$field->getName()] = $field;
            }
        }

        return $statedFields;
    }

    /**
     * @return DataField[]
     */
    public function getEnabledDataFields()
    {
        return $this->getDataFieldsHavingEnabledState(true);
    }

    /**
     * @return DataField[]
     */
    public function getDisabledDataFields()
    {
        return $this->getDataFieldsHavingEnabledState(false);
    }

    /**
     * @return AbstractDataProvider
     */
    protected function setAllDataFieldsEnabledState($enabledState)
    {
        $statedFields = array();

        $fields = $this->getDataFields();
        foreach ($fields as $field) {
            $field->setEnabled($enabledState);
        }

        return $this;
    }

    /**
     * @return AbstractDataProvider
     */
    public function disableAllDataFields()
    {
        return $this->setAllDataFieldsEnabledState(false);
    }

    /**
     * @return AbstractDataProvider
     */
    public function enableAllDataFields()
    {
        return $this->setAllDataFieldsEnabledState(true);
    }

    /**
     * @return DataField
     */
    public function getDataField($dataFieldName)
    {
        return (array_key_exists($dataFieldName, $this->dataFields) ? $this->dataFields[$dataFieldName] : null);
    }

    /**
     * @param DataField $dataField
     *
     * @return DataField added DataField
     */
    public function addDataField(DataField $dataField)
    {
        $this->dataFields[$dataField->getName()] = $dataField;

        return $dataField;
    }

    /**
     * @return InputConfiguration
     */
    public function getInputConfiguration()
    {
        return $this->inputConfiguration;
    }

    /**
     * @param InputConfiguration $inputConfiguration
     *
     * @return AbstractDataProvider
     */
    public function setInputConfiguration(InputConfiguration $inputConfiguration)
    {
        $this->inputConfiguration = $inputConfiguration;

        return $this;
    }

    public function countRowsOfQb(QueryBuilder $qb){
        $qb_count = clone $qb;
        $qb_count->select($qb_count->expr()->count('car'));
        $qb_count->setFirstResult(null);
        $qb_count->setMaxResults(null);
        return $qb_count->getQuery()->getSingleScalarResult();
    }


    /**
     * @return int|null
     */
    public function getTotalRowsCount()
    {
        return $this->totalRowsCount;
    }

    /**
     * @return int
     */
    public function getFilteredRowCount()
    {
        return $this->filteredRowsCount;
    }

    /**
     * @return array
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * @return array
     *
     * TODO: maybe return this by reference to avoid copy of big data
     */
    public function getAllRowValuesForOutput() {
        $rowOutputs = array();
        foreach ($this->rows as $row) {
            $rowOutputs[] = $row->getValuesForOutput();
        }

        return $rowOutputs;
    }

    /**
     * @param QueryBuilder $qBuilder
     *
     * @return AbstractDataProvider
     */
    public function setQueryBuilder(QueryBuilder $qBuilder)
    {
        $this->qBuilder = $qBuilder;

        return $this;
    }

    public function setTotalRowCount($totalRowsCount)
    {
        $this->totalRowsCount = $totalRowsCount;
    }

    /**
     * @param $filteredRowsCount
     *
     * @return AbstractDataProvider
     */
    public function setFilteredRowsCount($filteredRowsCount)
    {
        $this->filteredRowsCount = $filteredRowsCount;

        return $this;
    }

    /**
     * @param array $rows
     *
     * @return AbstractDataProvider
     */
    public function setRows($rows)
    {
        $this->rows = $rows;

        return $this;
    }

    /**
     * @return int rows count
     */
    public function getRowsCount()
    {
        return count($this->rows);
    }

    private function sqlGlobalFilterAlreadyAcceptedRow($row)
    {
        $alreadyAccepted = false;

        $sourceObject = $row->getSourceObject();

        if (is_bool($sourceObject)) {
            $alreadyAccepted = $sourceObject;
        } else {
            if ($sourceObject instanceof ActiveRecord) {
                try {
                    $columnResult = $sourceObject->get(self::GLOBAL_FILTER_RESULT_ADDITIONAL_COLUMN_NAME);
                    if ($columnResult === '1' || $columnResult === 1) {
                        $alreadyAccepted = true;
                    }
                } catch (ActiveRecordException $E) {
                }
            }
        }

        return $alreadyAccepted;
    }

    /**
     * Add a row.
     *
     * @param Row  $row        the array of data
     * @param bool $autoFilter True if filters should be applied immediately on the row
     *
     * @return bool True if rows has been added, false if not (because of filters)
     */
    public function addRow(Row $row, $autoFilter = false)
    {
        $sqlGlobalFilterAlreadyAcceptedRow = $this->sqlGlobalFilterAlreadyAcceptedRow($row);
        $row->setMetadata(self::ROW_META__SQL_GLOBAL_FILTER_ALREADY_ACCEPTED_ROW, $sqlGlobalFilterAlreadyAcceptedRow);

        if ($autoFilter) {
            if (!$this->checkFiltering($row)) {
                return false;
            }
        }

        $newRowIndex = (count($this->rows));

        $this->rows[$newRowIndex] = $row;

        return true;
    }

    /**
     * Reset Rows
     * Resetting rows means to "get the DataTableProvider back to his state before ->generateRows() call)
     *
     * @return $this
     */
    public function resetRows() {
        $this->rows = array();
        $this->totalRowsCount = null;
        $this->filteredRowsCount = 0;

        return $this;
    }

    /**
     * Main function to configure this DataProvider.
     *
     * Use this to add your own DataFields
     */
    abstract public function configure();

    /**
     * Main function to generate the rows.
     *
     */
    abstract public function generateRows();


    /**
     * Create a InputConfiguration based on raw input from a client SaniDataTable.
     *
     * @param array $inputParameters
     */
    public function readRawInputParameters($inputParameters)
    {
        $this->setInputConfiguration(InputConfiguration::fromRawInput($inputParameters));
    }

    /**
     * Output result rows as expected by SaniDataTable.
     *
     * @return array
     */
    public function getFormattedOutput()
    {
        return array(
            'userData' => $this->getUserOutputDatas(),
            'recordsTotal' => $this->getTotalRowsCount(),
            'recordsFiltered' => $this->getFilteredRowCount(),
            'data' => $this->getAllRowValuesForOutput(),
        );
    }



    /**
     * Complete a SQL query SELECT clauses.
     *
     * @param QueryBuilder $qBuilder
     */
    public function completeQuerySelects(QueryBuilder $qBuilder)
    {
        $fields = $this->getEnabledDataFields();

        // Completing Selects (SELECT clauses)
        foreach ($fields as $field) {
            if ($field->getSqlSelectingCustomFunction() !== null) {
                $sqlSelectingCustomFunction = $field->getSqlSelectingCustomFunction();
                if (is_callable($sqlSelectingCustomFunction)) {
                    $sqlSelectingCustomFunction($field, $qBuilder);
                }
            }elseif($field->getSqlMainFieldName() !== null){
                $qBuilder->addSelect($field->getSqlMainFieldName());
            }
        }
    }

    /**
     * Complete a SQL query WHERE clauses.
     *
     * @param QueryBuilder $qBuilder
     */
    public function completeQueryFilters(QueryBuilder $qBuilder)
    {
        $fields = $this->getEnabledDataFields();
        $inputConfiguration = $this->getInputConfiguration();
        // Completing Filters (WHERE clauses)

        // 1- Applying datafield filters
        $applicableDataFieldFilters = $this->getApplicableDataFieldFilters();
        foreach ($applicableDataFieldFilters as $filter) {
            $field = $fields[$filter->getFieldName()];
            if ($field->getFilteringMethod() == DataField::FILTERING_METHOD_IN_SQL_REQUEST) {
                $sqlFilteringCustomFunction = $field->getSqlFilteringCustomFunction();
                if (is_callable($sqlFilteringCustomFunction)) {
                    $sqlFilteringCustomFunction($field, $filter, $qBuilder);
                } else {
                    $filter->applyToQuery($qBuilder, $field->getSqlMainFieldName(), $field);
                }
            }
        }

        /***
         * 2- Applying global filters
         *
         * If there is a PHP global filter, we cannot add filters directly in WHERE, because they are combined with OR, and a future PHP filter can accept the line !
         * Procedure: move all WHERE added clauses to a boolean inside SELECT
         *
         */
        $tmpQBuilder = new QueryBuilder($this->em);
        $conditionsAddedToQuery = array();

        $globalFilters = $inputConfiguration->getGlobalFilters();

        if (!empty($globalFilters)) {
            foreach ($globalFilters as $filter) {
                foreach ($fields as $field) {
                    if ($field->isFilterable()) {
                        if ($field->isFilterAuthorized($filter)) {
                            if ($filter->isDataFieldInScope($field)) {

                                if ($field->getFilteringMethod() == DataField::FILTERING_METHOD_IN_SQL_REQUEST) {
                                    $newConditionAdded = null;

                                    $sqlFilteringCustomFunction = $field->getSqlFilteringCustomFunction();
                                    if (is_callable($sqlFilteringCustomFunction)) {
                                        $newConditionAdded = $sqlFilteringCustomFunction($field, $filter, $tmpQBuilder);
                                    } else {
                                        $newConditionAdded = $filter->applyToQuery($tmpQBuilder, $field->getSqlMainFieldName(), $field);
                                    }
                                    if ($newConditionAdded !== null) {
                                        $conditionsAddedToQuery[] = $newConditionAdded;
                                    }
                                }
                            }
                        }
                    }
                }
            }


            /*
             * Move the where part to real query
             * - in WHERE if there is no PHP filtering
             * - in SELECT if there is a PHP filtering
             */
            if (!$this->hasSomethingToFilterViaPhpUsingAGlobalFilter()) {
                if (!empty($conditionsAddedToQuery)){
                    $or = $qBuilder->expr()->orX();
                    foreach ($tmpQBuilder->getDQLPart('where')->getParts() as $dqlpart) {
                       $or->add($dqlpart);
                    }
                    $qBuilder->andWhere($or);
                    foreach ($tmpQBuilder->getParameters() as $parameter) {
                        $qBuilder->setParameter($parameter->getName(),$parameter->getValue(),$parameter->getType());
                    }
                }
            } else {
                // TODO Alh : Vérifier cette partie quand on voudrea mettree en place des filtres php

                if (!empty($conditionsAddedToQuery)) {
                    $or = $tmpQBuilder->expr()->orX();

                    foreach ($conditionsAddedToQuery as $key => $conditionName){
                        $or->add($conditionName);
                    }
                    $qBuilder->where($or);
                }

                $doctrineExtension = new DoctrineExtension();

                $wherePartPreparedQuery = preg_replace('#^\s?WHERE\s?#', '', $tmpQBuilder->buildWhere());

                if ($wherePartPreparedQuery != '') {
                    $wherePart = $doctrineExtension->replaceQueryParameters($wherePartPreparedQuery, $tmpQBuilder->getData(), false);

                    $qBuilder->withColumn($wherePart, self::GLOBAL_FILTER_RESULT_ADDITIONAL_COLUMN_NAME);
                }
            }
        }
    }

    /**
     * Complete a SQL query ORDER clauses.
     *
     * @param QueryBuilder $qBuilder
     */
    public function completeQueryOrders(QueryBuilder $qBuilder)
    {
        $fields = $this->getEnabledDataFields();

        // Completing Orders (ORDER clauses)
        $applicableOrders = $this->getApplicableOrders();

        foreach ($applicableOrders as $order) {
            $field = $fields[$order->getFieldName()];
            if ($field->getOrderingMethod() == DataField::ORDERING_METHOD_IN_SQL_REQUEST) {
                $sqlOrderingCustomFunction = $field->getSqlOrderingCustomFunction();

                if (is_callable($sqlOrderingCustomFunction)) {
                    $sqlOrderingCustomFunction($field, $order, $qBuilder);
                } else {
                    $order->applyToQuery($qBuilder, $field->getSqlMainFieldName());
                }
            }
        }
    }

    /**
     * Complete a SQL query LIMIT clause.
     *
     * @param QueryBuilder $qBuilder
     */
    public function completeQueryPaging(QueryBuilder $qBuilder)
    {
        $inputConfiguration = $this->getInputConfiguration();

        if ($inputConfiguration->hasPagingLimit()) {
            $qBuilder->setFirstResult($inputConfiguration->getPagingOffset());
            $qBuilder->setMaxResults($inputConfiguration->getPagingLimit());
        }
    }

    /**
     * Complete a SQL query using existing Datafields.
     *
     * Using options, it's possible to prevent:
     * - altering SELECT ('complete-selects' => false): not recommanded unless you know what you do
     * - altering WHEREs ('complete-filters' => false): not recommanded unless you know what you do
     * - altering ORDERs ('complete-orders' => false): not recommanded unless you know what you do
     * - altering LIMIT ('complete-paging' => false): used often when paging must be done via PHP. This option is not linked to existing Datafields.
     *
     * @param QueryBuilder $qBuilder
     * @param array        $options
     */
    public function completeQuery(QueryBuilder $qBuilder, array $options = array())
    {
        $defaults = array(
            'complete-selects' => true,
            'complete-filters' => true,
            'complete-orders' => true,
            'complete-paging' => true,
        );

        $resolver = new OptionsResolver();
        $resolver->setDefaults($defaults);

        $settings = $resolver->resolve($options);

        if ($settings['complete-selects']) {
            $this->completeQuerySelects($qBuilder);
        }

        if ($settings['complete-filters']) {
            $this->completeQueryFilters($qBuilder);
        }

        if ($settings['complete-orders']) {
            $this->completeQueryOrders($qBuilder);

        }

        if ($settings['complete-paging']) {
            $this->completeQueryPaging($qBuilder);
        }
    }

    /**
     * Return all applicable orders = Orders that map an existing DataField which is orderable.
     *
     * @return Order[]
     */
    public function getApplicableOrders()
    {
        $fields = $this->getEnabledDataFields();

        $orders = $this->getInputConfiguration()->getOrders();

        $applicableOrders = array();

        foreach ($orders as $order) {
            if (isset($fields[$order->getFieldName()])) {
                $field = $fields[$order->getFieldName()];
                if ($field->isOrderable()) {
                    $applicableOrders[] = $order;
                }
            }
        }

        return $applicableOrders;
    }

    /**
     * Return all applicable datafield filters :
     *   IF filter type = TYPE_FOR_DATAFIELD
     *     IF filter maps an existing field
     *        IF filter field is filterable
     *           IF filter is authorized by field
     *              OK.
     *
     * @return Filter[]
     */
    public function getApplicableDataFieldFilters()
    {
        $fields = $this->getEnabledDataFields();
        $filters = $this->getInputConfiguration()->getFilters();
        $applicableFilters = array();

        foreach ($filters as $filter) {
            if ($filter->getType() == Filter::TYPE_FOR_DATAFIELD) {
                if (isset($fields[$filter->getFieldName()])) {
                    $field = $fields[$filter->getFieldName()];
                    if ($field->isFilterable()) {
                        if ($field->isFilterAuthorized($filter)) {
                            $applicableFilters[] = $filter;
                        }
                    }
                }
            }
        }

        return $applicableFilters;
    }

    /**
     * Return all applicable global filters :
     *   IF filter type = TYPE_GLOBAL
     *      IF any field is filterable, authorizes the filter, and is not excluded by Filter scope
     *         OK.
     *
     * @return Filter[]
     */
    public function getApplicableGlobalFilters()
    {
        $fields = $this->getEnabledDataFields();

        $globalFilters = $this->getInputConfiguration()->getGlobalFilters();

        $applicableFilters = array();

        foreach ($globalFilters as $filter) {
            foreach ($fields as $field) {
                if ($field->isFilterable()) {
                    if ($field->isFilterAuthorized($filter)) {
                        if ($filter->isDataFieldInScope($field)) {
                            $applicableFilters[] = $filter;
                            break;
                        }
                    }
                }
            }
        }

        return $applicableFilters;
    }

    /**
     * Returns all applicable filters.
     *
     * @return Filter[]
     */
    public function getAllApplicableFilters()
    {
        return array_merge(
            $this->getApplicableDataFieldFilters(),
            $this->getApplicableGlobalFilters()
        );
    }

    /**
     * Return if or not a DataField is concerned in filtering.
     *
     * @param string $fieldName
     *
     * @return bool
     */
    public function isDataFieldConcernedByFiltering($fieldName)
    {
        $allApplicableFilters = $this->getAllApplicableFilters();

        foreach ($allApplicableFilters as $filter) {
            if ($filter->getFieldName() == $fieldName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Return if or not a DataField is concerned in ordering.
     *
     * @param string $fieldName
     *
     * @return bool
     */
    public function isDataFieldConcernedByOrdering($fieldName)
    {
        $allOrders = $this->getApplicableOrders();

        foreach ($allOrders as $order) {
            if ($order->getFieldName() == $fieldName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a PHP sorting OR filtering will be done.
     *
     * @return bool true if a PHP sorting OR filtering will be done, false otherwise
     */
    public function hasSomethingToProcessViaPhp()
    {
        return ($this->hasSomethingToFilterViaPhp() || $this->hasSomethingToOrderViaPhp());
    }

    /**
     * Checks if a PHP sorting will be done.
     *
     * @return bool true if a PHP sorting will be done, false otherwise
     */
    public function hasSomethingToOrderViaPhp()
    {
        $fields = $this->getEnabledDataFields();

        $applicableOrders = $this->getApplicableOrders();

        /*
         * We have to sort rows only if there is at least one applicable ORDERING_METHOD_PHP_XXX
         */
        $hasPhpOrdering = false;
        foreach ($applicableOrders as $order) {
            $field = $fields[$order->getFieldName()];
            $method = $field->getOrderingMethod();

            if ($method == DataField::ORDERING_METHOD_PHP_AUTO || $method == DataField::ORDERING_METHOD_PHP_CUSTOM) {
                $hasPhpOrdering = true;
                break;
            }
        }

        return $hasPhpOrdering;
    }

    /**
     * Checks if a PHP filtering will be done using a Filter::TYPE_FOR_DATAFIELD Filter.
     *
     * @return bool true if a PHP filtering will be done using a Filter::TYPE_FOR_DATAFIELD Filter, false otherwise
     */
    public function hasSomethingToFilterViaPhpUsingADatafieldFilter()
    {
        $fields = $this->getEnabledDataFields();

        // 1- Checking datafield filters
        $applicableDataFieldFilters = $this->getApplicableDataFieldFilters();

        foreach ($applicableDataFieldFilters as $filter) {
            $field = $fields[$filter->getFieldName()];

            if (in_array($field->getFilteringMethod(), array(DataField::FILTERING_METHOD_PHP_AUTO, DataField::FILTERING_METHOD_PHP_CUSTOM))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a PHP filtering will be done using a Filter::TYPE_GLOBAL Filter.
     *
     * @return bool true if a PHP filtering will be done using a Filter::TYPE_GLOBAL Filter, false otherwise
     */
    public function hasSomethingToFilterViaPhpUsingAGlobalFilter()
    {
        $fields = $this->getEnabledDataFields();
        $inputConfiguration = $this->getInputConfiguration();

        // 1- Checking datafield filters
        $globalFilters = $inputConfiguration->getGlobalFilters();

        foreach ($globalFilters as $filter) {
            foreach ($fields as $field) {
                if ($field->isFilterable()) {
                    if ($field->isFilterAuthorized($filter)) {
                        if ($filter->isDataFieldInScope($field)) {
                            if (in_array($field->getFilteringMethod(), array(DataField::FILTERING_METHOD_PHP_AUTO, DataField::FILTERING_METHOD_PHP_CUSTOM))) {
                                return true;
                            }
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Checks if a PHP filtering will be done.
     *
     * @return bool true if a PHP filtering will be done, false otherwise
     */
    public function hasSomethingToFilterViaPhp()
    {
        return ($this->hasSomethingToFilterViaPhpUsingADatafieldFilter() || $this->hasSomethingToFilterViaPhpUsingAGlobalFilter());
    }

    /**
     * Checks if php-specific filters accepts or refuse the given row.
     *
     * @param Row $row
     *
     * @return bool true if row is accepted, false otherwise
     */
    public function checkFiltering(Row $row)
    {
        $fields = $this->getEnabledDataFields();
        $inputConfiguration = $this->getInputConfiguration();
        $rowValuesForProcessing = $row->getValuesForProcessing();

        // 1- Applying datafield filters
        $applicableDataFieldFilters = $this->getApplicableDataFieldFilters();

        foreach ($applicableDataFieldFilters as $filter) {
            $field = $fields[$filter->getFieldName()];

            $valueToFilter = $rowValuesForProcessing[$filter->getFieldName()];

            $phpFilteringTransformValueFunction = $field->getPhpFilteringTransformValueFunction();
            if ($phpFilteringTransformValueFunction != null && is_callable($phpFilteringTransformValueFunction)) {
                $valueToFilter = $phpFilteringTransformValueFunction($field, $valueToFilter, $row);
            }

            if ($field->getFilteringMethod() == DataField::FILTERING_METHOD_PHP_AUTO) {
                if (!$filter->checkFiltering($valueToFilter, $field)) {
                    return false;
                }
            } else {
                if ($field->getFilteringMethod() == DataField::FILTERING_METHOD_PHP_CUSTOM) {
                    $phpFilteringCustomFunction = $field->getPhpFilteringCustomFunction();
                    if (is_callable($phpFilteringCustomFunction)) {
                        if (!$phpFilteringCustomFunction($field, $filter, $valueToFilter, $row)) {
                            return false;
                        }
                    }
                }
            }
        }

        /*
         * 2- Applying global filters
         *
         * Important: if the row has already been accepted by a SQL global filter, row is therefore directly accepted !
         */
        if ($row->getMetadata(self::ROW_META__SQL_GLOBAL_FILTER_ALREADY_ACCEPTED_ROW) === true) {
            return true;
        }

        $globalFilters = $inputConfiguration->getGlobalFilters();

        if ($this->hasSomethingToFilterViaPhpUsingAGlobalFilter()) {
            $oneGlobalFilterPassed = false;

            foreach ($globalFilters as $filter) {
                foreach ($fields as $field) {
                    if ($field->isFilterable()) {
                        if ($field->isFilterAuthorized($filter)) {
                            if ($filter->isDataFieldInScope($field)) {
                                $valueToFilter = $rowValuesForProcessing[$field->getName()];

                                $phpFilteringTransformValueFunction = $field->getPhpFilteringTransformValueFunction();
                                if ($phpFilteringTransformValueFunction != null && is_callable($phpFilteringTransformValueFunction)) {
                                    $valueToFilter = $phpFilteringTransformValueFunction($field, $valueToFilter, $row);
                                }

                                if ($field->getFilteringMethod() == DataField::FILTERING_METHOD_PHP_AUTO) {
                                    if ($filter->checkFiltering($valueToFilter, $field)) {
                                        $oneGlobalFilterPassed = true;
                                        break 2;
                                    }
                                } else {
                                    if ($field->getFilteringMethod() == DataField::FILTERING_METHOD_PHP_CUSTOM) {
                                        $phpFilteringCustomFunction = $field->getPhpFilteringCustomFunction();
                                        if (is_callable($phpFilteringCustomFunction)) {
                                            if ($phpFilteringCustomFunction($field, $filter, $valueToFilter, $row)) {
                                                $oneGlobalFilterPassed = true;
                                                break 2;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if (!$oneGlobalFilterPassed) {
                return false;
            }
        }

        return true;
    }

    /**
     * Apply php-specific orders to all rows.
     *
     * NOTE: if there is at least one php-specific ordering method, ALL orders will me done via PHP,
     * including the DataField having ordering method using DataField::ORDERING_METHOD_IN_SQL_REQUEST.
     */
    public function applyOrders()
    {
        $fields = $this->getEnabledDataFields();

        $applicableOrders = $this->getApplicableOrders();

        if ($this->hasSomethingToOrderViaPhp()) {
            $baseSortFunctionsTpls = array(
                ORDER::DIRECTION_ASC => <<<CODE
function( \$rowA, \$rowB ) use(&\$orderFunctionChainStack) {
    \$rowAValue = \$rowA->getValueForProcessing('%field_name%');
    \$rowBValue = \$rowB->getValueForProcessing('%field_name%');

    if( \$rowAValue > \$rowBValue ) {
        return 1;
    }
    if( \$rowAValue < \$rowBValue ) {
        return -1;
    }

    %if_equals_return_code%
};
CODE
            ,
                ORDER::DIRECTION_DESC => <<<CODE
function( \$rowA, \$rowB ) use(&\$orderFunctionChainStack) {
    \$rowAValue = \$rowA->getValueForProcessing('%field_name%');
    \$rowBValue = \$rowB->getValueForProcessing('%field_name%');

    if( \$rowAValue < \$rowBValue ) {
        return 1;
    }
    if( \$rowAValue > \$rowBValue ) {
        return -1;
    }

    %if_equals_return_code%
};
CODE
            );

            $userSortFunctionContainerTpl = <<<CODE
function( \$rowA, \$rowB ) use( &\$userFieldStack, &\$orderFunctionChainStack, &\$userSortFunctionsStack, &\$userSortFunctionOrdersStack ) {
    \$rowAValues = \$rowA->getValuesForProcessing();
    \$rowBValues = \$rowB->getValuesForProcessing();

    \$userSortFunctionReturn = \$userSortFunctionsStack[%user_sort_functions_stack_index%] (
        \$userFieldStack[%user_fields_stack_index%],
        \$userSortFunctionOrdersStack[%user_orders_stack_index%],
        \$rowAValues['%field_name%'],
        \$rowBValues['%field_name%']
    );

    if( \$userSortFunctionReturn != 0 ) {
        return \$userSortFunctionReturn;
    } else {
        %if_equals_return_code%
    }
};
CODE;

            $orderFunctionChainStack = array();
            $userFieldStack = array();
            $userSortFunctionsStack = array();
            $userSortFunctionOrdersStack = array();

            for ($i = 0, $l = count($applicableOrders); $i < $l; ++$i) {
                $order = $applicableOrders[$i];
                $field = $fields[$order->getFieldName()];
                $method = $field->getOrderingMethod();

                /*
                 * If method is DataField::ORDERING_METHOD_IN_SQL_REQUEST, we do a PHP sorting anyway
                 */
                if ($method == DataField::ORDERING_METHOD_PHP_AUTO || $method == DataField::ORDERING_METHOD_IN_SQL_REQUEST) {
                    $orderSortFunctionTpl = $baseSortFunctionsTpls[$order->getDirection()];

                    $equalsCode = 'return 0;';
                    if (isset($applicableOrders[$i + 1])) {
                        $orderFunctionChainStackNextIndex = count($orderFunctionChainStack) + 1;
                        $equalsCode = <<<CODE
return \$orderFunctionChainStack[$orderFunctionChainStackNextIndex](\$rowA, \$rowB);
CODE;
                    }

                    $functionCode = str_replace(
                        array(
                            '%field_name%',
                            '%if_equals_return_code%',
                        ),
                        array(
                            str_replace("'", "\\'", $field->getName()),
                            $equalsCode,
                        ),
                        $orderSortFunctionTpl
                    );

                    $functionInsertCode = '$orderFunctionChainStack[] = '.$functionCode;

                    eval($functionInsertCode);
                }

                if ($method == DataField::ORDERING_METHOD_PHP_CUSTOM) {
                    $phpOrderingCustomFunction = $field->getPhpOrderingCustomFunction();

                    if (is_callable($phpOrderingCustomFunction)) {
                        $userFieldStack[] = &$field;
                        $userSortFunctionsStack[] = &$phpOrderingCustomFunction;
                        $userSortFunctionOrdersStack[] = &$order;

                        $equalsCode = 'return 0;';
                        if (isset($applicableOrders[$i + 1])) {
                            $orderFunctionChainStackNextIndex = count($orderFunctionChainStack) + 1;
                            $equalsCode = <<<CODE
return \$orderFunctionChainStack[$orderFunctionChainStackNextIndex](\$rowA, \$rowB);
CODE;
                        }

                        $functionCode = str_replace(
                            array(
                                '%field_name%',
                                '%user_fields_stack_index%',
                                '%user_sort_functions_stack_index%',
                                '%user_orders_stack_index%',
                                '%if_equals_return_code%',
                            ),
                            array(
                                str_replace("'", "\\'", $field->getName()),
                                count($userFieldStack) - 1,
                                count($userSortFunctionsStack) - 1,
                                count($userSortFunctionOrdersStack) - 1,
                                $equalsCode,
                            ),
                            $userSortFunctionContainerTpl
                        );

                        $functionInsertCode = '$orderFunctionChainStack[] = '.$functionCode;
                        eval($functionInsertCode);
                    }
                }
            }

            if (array_key_exists(0, $orderFunctionChainStack)) {
                $rowsToSort = array();
                foreach ($this->rows as $row) {
                    $rowsToSort[] = clone($row);
                }

                foreach ($rowsToSort as $index => $rowToSort) {
                    $rowToSortValues = $rowToSort->getValuesForProcessing();

                    for ($i = 0, $l = count($applicableOrders); $i < $l; ++$i) {
                        $order = $applicableOrders[$i];
                        $field = $fields[$order->getFieldName()];

                        $valueToOrder = $rowToSortValues[$field->getName()];

                        $phpOrderingTransformValueFunction = $field->getPhpOrderingTransformValueFunction();
                        if ($phpOrderingTransformValueFunction != null && is_callable($phpOrderingTransformValueFunction)) {
                            $valueToOrder = $phpOrderingTransformValueFunction($field, $valueToOrder, $this->rows[$index]);
                        }

                        $rowToSort->setRawValue($field->getName(), $valueToOrder);
                    }
                }

                uasort($rowsToSort, $orderFunctionChainStack[0]);

                foreach (array_keys($this->rows) as $rowIndex) {
                    $rowsToSort[$rowIndex] = $this->rows[$rowIndex];
                }

                $this->rows = $rowsToSort;
            }
        }
    }

    /**
     * Apply php filtering on each rows.
     *
     * Usually used when filtering was disabled when adding rows
     */
    public function applyFilters()
    {
        $newRows = array();

        foreach ($this->row as $rowIndex => $row) {
            if ($this->checkFiltering($row)) {
                $newRows[] = $row;
            }
        }

        $this->rows = &$newRows;
    }

    /**
     * Apply paging to all rows.
     *
     * Use only when paging cannot be done via SQL.
     * It's usually done at the really end of process.
     */
    public function applyPaging()
    {
        $inputConfiguration = $this->getInputConfiguration();

        if ($inputConfiguration->hasPagingLimit()) {
            $this->rows = array_slice($this->rows, $inputConfiguration->getPagingOffset(), $inputConfiguration->getPagingLimit());
        }
    }


    /**
     * @return string[]
     */
    public function getDefaultRequiredDataSelectors() {
        return $this->defaultRequiredDataSelectors;
    }

    /**
     * @param string[] $pagingOffset
     *
     * @return InputConfiguration
     */
    public function setDefaultRequiredDataSelectors($defaultRequiredDataSelectors) {
        $this->defaultRequiredDataSelectors = $defaultRequiredDataSelectors;

        return $this;
    }

    /**
     * @param string $order
     */
    public function addDefaultRequiredDataSelector($defaultRequiredDataSelector) {
        $this->defaultRequiredDataSelectors[] = $defaultRequiredDataSelector;
    }


    /**
     * @return string[]
     */
    public function getRequiredDataSelectors() {
        $InputConfiguration = $this->getInputConfiguration();

        if( $InputConfiguration !== null ) {
            if( $InputConfiguration->hasRequiredDataSelectors() ) {
                return $InputConfiguration->getRequiredDataSelectors();
            }
        }

        return $this->getDefaultRequiredDataSelectors();
    }


    /**
     * @return array|null
     */
    public function getUserOutputDatas( ) {
        return $this->userOutputData;
    }

    /**
     * @param array $userOutputData
     * @return $this
     */
    public function setUserOutputDatas( $userOutputData ) {
        $this->userOutputData = $userOutputData;

        return $this;
    }

    /**
     * @return mixed
     */
    public function getUserOutputData( $key, $default = null ) {
        if( array_key_exists($key, $this->userOutputData) ) {
            return $this->userOutputData[$key];
        }

        return $default;
    }

    /**
     * @param array $userOutputData
     * @return $this
     */
    public function setUserOutputData( $key, $data ) {
        $this->userOutputData[$key] = $data;

        return $this;
    }

    /**
     * Build data selector tree from a data selector string
     *
     * Example :
     * -> $dataSelector : 'adspace@country@**'
     * -> returned tree : array(
     *        'adspace' => array(
     *            'country' => array(
     *                '**'
     *            )
     *        )
     *    )
     *
     * @param $dataSelector
     * @return mixed
     */
    protected static function getDataSelectorTree( $dataSelector ) {
        $Tree = array();

        $dataSelectorComponents = explode('@', $dataSelector);
        $lastComponent = $dataSelectorComponents[count($dataSelectorComponents)-1];
        array_pop($dataSelectorComponents);

        ArrayHelper::arrayDeepSet( $Tree, $dataSelectorComponents, array($lastComponent) );

        return $Tree;
    }

    /**
     * Return list of data selector tree node data
     *
     * Example:
     * -> $dataSelectorTreeNode : array(
     *        'adspace' => array(
     *            '*',
     *            '**',
     *            'id',
     *            'status' => array(
     *                'id',
     *                'text'
     *            ),
     *            'name',
     *            'country' => array(
     *                '**'
     *            ),
     *        )
     *    )
     * -> Returned value : array( '*', '**', 'id', 'name' )
     *
     * Note: this function is the opposite of ->getDataSelectorTreeNodeScopes
     *
     * @param $dataSelectorTreeNode
     *
     * @return array the list of the data in the tree node
     *
     */
    protected static function getDataSelectorTreeNodeData( $dataSelectorTreeNode ) {
        return array_filter(
            $dataSelectorTreeNode,
            function( $k, $v ) {
                return !is_array($v);
            },
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Return list of data selector tree node data
     *
     * Example:
     * -> $dataSelectorTreeNode : array (
     *        'adspace' => array(
     *            '*',
     *            '**',
     *            'id',
     *            'status' => array(
     *                'id',
     *                'text'
     *            ),
     *            'name',
     *            'country' => array(
     *                '**'
     *            ),
     *        )
     *    )
     * -> Returned value : array( 'status', 'country' )
     *
     * Note: this function is the opposite of ->getDataSelectorTreeNodeData
     *
     * @param $dataSelectorTreeNode
     *
     * @return array the list of the data in the tree node
     *
     */
    protected static function getDataSelectorTreeNodeScopes( $dataSelectorTreeNode ) {
        return array_filter(
            $dataSelectorTreeNode,
            function( $k, $v ) {
                return is_array($v);
            },
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Specify if a specified data is required
     *
     * @param string $dataPath path to data, for example 'adspace@name', 'adspace@affiliate@id' ('name' and 'id' must be final field, not a scope)
     * @return bool
     */
    public function isDataRequired( $dataPath ) {
        $dataPathComponents = explode('@', $dataPath);

        foreach( $this->getRequiredDataSelectors() as $requiredDataSelector ) {
            $requiredDataSelectorTree = self::getDataSelectorTree( $requiredDataSelector );

            $notFoundLevels = 0;

            $currentDataPathComponents = $dataPathComponents;
            $currentDataPathComponent = $currentDataPathComponents[count($currentDataPathComponents)-1];

            while( true ) {
                $targetDataSelectorNode = ArrayHelper::arrayDeepGetValue($requiredDataSelectorTree, $currentDataPathComponents, null);

                if( $targetDataSelectorNode === null ) {
                    $notFoundLevels++;
                    $currentDataPathComponent = $currentDataPathComponents[count($currentDataPathComponents)-1];

                    if( !empty($currentDataPathComponents) ) {
                        array_pop($currentDataPathComponents);
                        continue;
                    } else {
                        break;
                    }
                } else {
                    // Path has been found
                    if( !is_array($targetDataSelectorNode) ) {
                        break; // Failure, let's test the next selector
                    }

                    if( in_array('**', $targetDataSelectorNode) ) {
                        return true;
                    }

                    if( in_array($currentDataPathComponent, $targetDataSelectorNode) ) {
                        return true;
                    }

                    if( in_array('*', $targetDataSelectorNode) ) {
                        if( $notFoundLevels < 2 ) {
                            return true;
                        } else {
                            break; // Failure, let's test the next selector
                        }
                    }

                    break; // Failure, let's test the next selector
                }
            }
        }

        return false;
    }

    /**
     * Specify if any data is required in specified scope
     *
     * @param string $dataScopePath path to data, for example 'adspace@affiliate' ('affiliate' must be a scope, not final field)
     * @return bool
     */
    public function isAnyDataRequiredInScope( $dataScopePath ) {
        $dataScopePathComponents = ( ($dataScopePath != '') ? explode('@', $dataScopePath) : array() ); // To make the function returning the truth if $dataScopePath is an empty string ''


        foreach( $this->getRequiredDataSelectors() as $requiredDataSelector ) {
            $requiredDataSelectorTree = self::getDataSelectorTree( $requiredDataSelector );

            $notFoundLevels = 0;

            $currentDataScopePathComponents = $dataScopePathComponents;

            while( true ) {
                $targetDataSelectorNode = ArrayHelper::arrayDeepGetValue($requiredDataSelectorTree, $currentDataScopePathComponents, null);
                if( $targetDataSelectorNode === null ) {
                    $notFoundLevels++;

                    if( !empty($currentDataScopePathComponents) ) {
                        array_pop($currentDataScopePathComponents);
                        continue;
                    } else {
                        break;
                    }
                } else {

                    // Path has been found
                    if( !is_array($targetDataSelectorNode) ) {
                        break; // Failure, let's test the next selector
                    }

                    if( $notFoundLevels == 0 ) {
                        if( !empty($targetDataSelectorNode) ) {
                            return true;
                        } else {
                            break;  // Failure, let's test the next selector
                        }
                    } else {
                        if( in_array('**', $targetDataSelectorNode) ) {
                            return true;
                        }

                        if( in_array('*', $targetDataSelectorNode) ) {
                            if( $notFoundLevels == 0 ) {
                                return true;
                            } else {
                                break; // Failure, let's test the next selector
                            }
                        }
                    }

                    break; // Failure, let's test the next selector
                }
            }
        }

        return false;

    }

    /**
     * Clean row by unsetting unrequired data
     *
     * @param Row $row
     *
     * @return Row
     */
    public function cleanRow( Row $row, $fieldNamesWhiteList = array('id') ) {
        $rawValues = $row->getRawValues();
        $formattedValues = $row->getFormattedValues();

        foreach( $rawValues as $fieldName => $fieldValue ) {
            if( in_array($fieldName, $fieldNamesWhiteList) ) {
                continue;
            }
            if( !$this->isDataRequired($fieldName) ) {
                unset( $rawValues[$fieldName] );
            }
        }

        foreach( $formattedValues as $fieldName => $fieldValue ) {
            if( in_array($fieldName, $fieldNamesWhiteList) ) {
                continue;
            }
            if( !$this->isDataRequired($fieldName) ) {
                unset( $formattedValues[$fieldName] );
            }
        }

        $row->setRawValues( $rawValues );
        $row->setFormattedValues( $formattedValues );

        return $row;
    }

}
