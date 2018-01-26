<?php

namespace Sanilea\SanidatatablesBundle\DataTableProvider;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Validation;

/**
 * Class FilteringAuthorization.
 *
 * Represent a set of rules defining what a Datafield can or cannot filter.
 */
class FilteringAuthorization
{
    protected $filterCountLimit; // Not use for the moment
    protected $authorizations;

    public function __construct($options = array())
    {
        $this->init();
    }

    public function init()
    {
        $this->filterCountLimit = null;
        $this->authorizations = array();
    }

    static public function getValidator( $validatorName ) {
        switch($validatorName) {
            case 'is_scalar':
                return function ($value) {
                    return is_scalar($value);
                };
            break;
            case 'is_string':
                return new Type(array('type' => 'string'));
            break;
            case 'is_numeric':
                return function ($value) {
                    return is_numeric($value);
                };
            break;
            case 'is_array_of_string':
                return function( $value ) {
                    if( !is_array($value) ) {
                        return false;
                    }
                    foreach( $value as $elt ) {
                        if( !is_string($elt) ) {
                            return false;
                        }
                    }

                    return true;
                };
            break;
            case 'is_array_of_numeric':
                return function( $value ) {
                    if( !is_array($value) ) {
                        return false;
                    }
                    foreach( $value as $elt ) {
                        if( !is_numeric($elt) ) {
                            return false;
                        }
                    }

                    return true;
                };
            break;
            case 'is_array_of_scalar':
                return function( $value ) {
                    if( !is_array($value) ) {
                        return false;
                    }
                    foreach( $value as $elt ) {
                        if( !is_scalar($elt) ) {
                            return false;
                        }
                    }

                    return true;
                };
            break;
        }
    }

    public static function createCommonStringAuthorization()
    {
        $authorisation = new self();

        $isScalarValidator = self::getValidator('is_scalar');
        $isStringValidator = self::getValidator('is_string');
        $isArrayOfScalarValidator = self::getValidator('is_array_of_scalar');

        $authorisation->addAuthorization('=', $isScalarValidator);
        $authorisation->addAuthorization('!=', $isScalarValidator);
        $authorisation->addAuthorization('IS_NULL', $isScalarValidator);
        $authorisation->addAuthorization('!IS_NULL', $isScalarValidator);
        $authorisation->addAuthorization('LIKE', $isStringValidator);
        $authorisation->addAuthorization('!LIKE', $isStringValidator);
        $authorisation->addAuthorization('IN', $isArrayOfScalarValidator);
        $authorisation->addAuthorization('!IN', $isArrayOfScalarValidator);

        return $authorisation;
    }

    public static function createCommonNumericAuthorization()
    {
        $authorisation = new self();

        $isNumericValidator = self::getValidator('is_numeric');
        $isArrayOfNumericValidator = self::getValidator('is_array_of_numeric');

        $authorisation->addAuthorization('=', $isNumericValidator);
        $authorisation->addAuthorization('!=', $isNumericValidator);
        $authorisation->addAuthorization('>', $isNumericValidator);
        $authorisation->addAuthorization('>=', $isNumericValidator);
        $authorisation->addAuthorization('<', $isNumericValidator);
        $authorisation->addAuthorization('<=', $isNumericValidator);
        $authorisation->addAuthorization('IS_NULL', $isNumericValidator);
        $authorisation->addAuthorization('!IS_NULL', $isNumericValidator);
        $authorisation->addAuthorization('IN', $isArrayOfNumericValidator);
        $authorisation->addAuthorization('!IN', $isArrayOfNumericValidator);
        $authorisation->addAuthorization('BETWEEN', $isArrayOfNumericValidator);
        $authorisation->addAuthorization('!BETWEEN', $isArrayOfNumericValidator);

        return $authorisation;
    }

    public static function createCommonDateAuthorization()
    {
        $authorisation = new self();

        $isStringValidator = self::getValidator('is_string');
        $isArrayOfStringValidator = self::getValidator('is_array_of_string');

        $authorisation->addAuthorization('=', $isStringValidator);
        $authorisation->addAuthorization('!=', $isStringValidator);
        $authorisation->addAuthorization('>', $isStringValidator);
        $authorisation->addAuthorization('>=', $isStringValidator);
        $authorisation->addAuthorization('<', $isStringValidator);
        $authorisation->addAuthorization('<=', $isStringValidator);
        $authorisation->addAuthorization('IS_NULL', $isStringValidator);
        $authorisation->addAuthorization('!IS_NULL', $isStringValidator);
        $authorisation->addAuthorization('IN', $isArrayOfStringValidator);
        $authorisation->addAuthorization('!IN', $isArrayOfStringValidator);
        $authorisation->addAuthorization('BETWEEN', $isArrayOfStringValidator);
        $authorisation->addAuthorization('!BETWEEN', $isArrayOfStringValidator);

        return $authorisation;
    }

    public function resetAuthorizations()
    {
        $this->authorizations = array();

        return $this;
    }

    public function addAuthorization($operator = null, $validator = null)
    {
        $this->authorizations[] = array(
            'operator' => $operator,
            'validator' => $validator,
        );

        return $this;
    }

    public function isAuthorized(Filter $filter)
    {
        foreach ($this->authorizations as $authorization) {
            // Check operator
            if ($filter->getOperator() == 'AUTO' || $filter->getOperator() == $authorization['operator']) {
                // Check validator
                if ($authorization['validator'] === null) {
                    return true;
                }

                if ($authorization['validator'] instanceof Constraint) {
                    $validator = Validation::createValidatorBuilder()->getValidator();
                    $violationList = $validator->validate($filter->getValue(), $authorization['validator']);
                    if ($violationList->count() == 0) {
                        return true;
                    }
                } else {
                    if (is_callable($authorization['validator'])) {
                        if ($authorization['validator']($filter->getValue())) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }
}
