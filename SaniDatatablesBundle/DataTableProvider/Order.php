<?php

namespace Sanilea\SaniDatatablesBundle\DataTableProvider;
use Doctrine\ORM\QueryBuilder;


/**
 * Class Order.
 *
 * Represent an order request submitted by client (formalised)
 */
class Order
{
    const DIRECTION_ASC = 'DIRECTION_ASC';
    const DIRECTION_DESC = 'DIRECTION_DESC';

    /**
     * Name of the field this Order applies on.
     *
     * @var string|null
     */
    protected $fieldName;

    /**
     * Is this Order enabled.
     *
     * @var bool|true
     */
    protected $enabled;

    /**
     * Direction of the order (self::DIRECTION_ASC or self::DIRECTION_DESC).
     *
     * @var string|null
     */
    protected $direction;

    public function __construct()
    {
        $this->init();
    }

    protected function init()
    {
        $this->enabled = true;
        $this->fieldName = null;
        $this->direction = null;
    }

    /**
     * Creates a self object using raw input.
     *
     * @param $rawInput (as sent by client DataTable)
     *
     * @return Order|null
     */
    public static function fromRawInput($rawInput)
    {
        if (!self::isValidRawInput($rawInput)) {
            return;
        }

        $order = new self();

        $order->setFieldName($rawInput['field']);
        $order->setDirection(self::getDirectionConstantFromRawInput($rawInput['dir']));

        return $order;
    }

    public static function getDirectionConstantFromRawInput($rawInput)
    {
        switch (strtolower($rawInput)) {
            default:
            case 'asc':
                return self::DIRECTION_ASC;
            break;
            case 'desc':
                return self::DIRECTION_DESC;
            break;
        }
    }

    public static function getSqlDirectionFromDirectionConstant($directionConstant)
    {
        switch ($directionConstant) {
            default:
            case self::DIRECTION_ASC:
                return 'ASC';
            break;
            case self::DIRECTION_DESC:
                return 'DESC';
            break;
        }
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

        if (!array_key_exists('dir', $rawInput) || !array_key_exists('field', $rawInput)) {
            return false;
        }

        if (!is_string($rawInput['dir']) || !is_string($rawInput['field'])) {
            return false;
        }

        if (!in_array(strtolower($rawInput['dir']), array('asc', 'desc'))) {
            return false;
        }

        return true;
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
     * @return Order
     */
    public function setFieldName($fieldName)
    {
        $this->fieldName = $fieldName;

        return $this;
    }

    /**
     * @return string
     */
    public function getDirection()
    {
        return $this->direction;
    }

    /**
     * @return string
     */
    public function getSqlDirection()
    {
        return self::getSqlDirectionFromDirectionConstant($this->direction);
    }

    /**
     * @param string $direction
     *
     * @return Order
     */
    public function setDirection($direction)
    {
        $this->direction = $direction;

        return $this;
    }

    /**
     * Applies the order to the given QueryBuilder.
     *
     * @param QueryBuilder $qBuilder
     * @param string       $operandExpression
     */
    public function applyToQuery(QueryBuilder $qBuilder, $operandExpression)
    {
        $qBuilder->orderBy($operandExpression, self::getSqlDirectionFromDirectionConstant($this->getDirection()));
    }
}
