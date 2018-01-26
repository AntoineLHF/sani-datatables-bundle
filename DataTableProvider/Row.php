<?php

namespace Sanilea\SanidatatablesBundle\DataTableProvider;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class Row.
 *
 * Represent a result row
 */
class Row {
    /**
     * Associative array columnName => $value.
     * Raw values are the values expected to be used by PHP filters and sorting.
     *
     * @var array|array()
     */
    protected $rawValues;

    /**
     * Associative array columnName => $value.
     * Formatted values are the values really sent back to the client Datatable.
     *
     * @var array|array()
     */
    protected $formattedValues;

    /**
     * Source Object (the source object that this row comes from, usually an instance of ActiveRecord).
     *
     * @var mixed|null
     */
    protected $sourceObject;

    /**
     * Metadata are free information the row.
     *
     * @var array|array()
     */
    protected $metadata;

    /**
     * @var RowFrontEndConfiguration[] $frontEndConfigurations
     */
    protected $frontEndConfigurations;


    public function __construct($options = array()) {
        $this->init();

        $resolver = new OptionsResolver();

        $defaults = array(
            'raw-values'               => array(),
            'formatted-values'         => array(),
            'source-object'            => null,
            'metadatas'                => array(),
            'front-end-configurations' => array(),
        );

        $resolver->setDefaults($defaults);
        $settings = $resolver->resolve($options);

        $this->setRawValues($settings['raw-values']);
        $this->setFormattedValues($settings['formatted-values']);
        $this->setSourceObject($settings['source-object']);
        $this->setMetadatas($settings['metadatas']);
        $this->setFrontEndConfigurations($settings['front-end-configurations']);
    }

    protected function init() {
        $this->rawValues = array();
        $this->formattedValues = array();
        $this->sourceObject = null;
        $this->metadata = array();
        $this->frontEndOptions = array();
    }

    public function getRawValues() {
        return $this->rawValues;
    }

    public function setRawValues($rawValues) {
        $this->rawValues = $rawValues;

        return $this;
    }

    public function getRawValue($columnName, $defaultValue = null) {
        if (!isset($this->rawValues[$columnName])) {
            return $defaultValue;
        }

        return $this->rawValues[$columnName];
    }

    public function setRawValue($columnName, $rawValue) {
        $this->rawValues[$columnName] = $rawValue;

        return $this;
    }

    public function getFormattedValues() {
        return $this->formattedValues;
    }

    public function setFormattedValues($formattedValues) {
        $this->formattedValues = $formattedValues;

        return $this;
    }

    public function getFormattedValue($columnName, $defaultValue = null) {
        if (!isset($this->formattedValues[$columnName])) {
            return $defaultValue;
        }

        return $this->formattedValues[$columnName];
    }

    public function setFormattedValue($columnName, $value) {
        $this->formattedValues[$columnName] = $value;

        return $this;
    }

    public function getMetadatas() {
        return $this->metadata;
    }

    public function setMetadatas($metadatas) {
        return $this->metadata = $metadatas;

        return $this;
    }

    public function getMetadata($metadataName, $defaultValue = null) {
        if (!isset($this->metadata[$metadataName])) {
            return $defaultValue;
        }

        return $this->metadata[$metadataName];
    }

    public function setMetadata($metadataName, $value) {
        $this->metadata[$metadataName] = $value;

        return $this;
    }

    public function getSourceObject() {
        return $this->sourceObject;
    }

    public function setSourceObject($sourceObject) {
        $this->sourceObject = $sourceObject;

        return $this;
    }


    /**
     * @return RowFrontEndConfiguration[]
     */
    public function getFrontEndConfigurations() {
        return $this->frontEndConfigurations;
    }

    /**
     * @param string $frontEndClass class name of expected FrontEnd
     *
     * @return RowFrontEndConfiguration|null
     */
    public function getFrontEndConfiguration($frontEndClass) {
        return (isset($this->frontEndConfigurations[$frontEndClass]) ? $this->frontEndConfigurations[$frontEndClass] : null);
    }

    /**
     * @param RowFrontEndConfiguration[] $frontEndConfigurations
     *
     * @return Row
     */
    public function setFrontEndConfigurations($frontEndConfigurations) {
        foreach ($frontEndConfigurations as $frontEndConfiguration) {
            $this->setFrontEndConfiguration($frontEndConfiguration);
        }

        return $this;
    }

    /**
     * @param RowFrontEndConfiguration $frontEndConfiguration
     *
     * @return Row
     */
    public function setFrontEndConfiguration($frontEndConfiguration) {
//        dump($frontEndConfiguration);
//        dump(get_class($frontEndConfiguration));
        $this->frontEndConfigurations[get_class($frontEndConfiguration)] = $frontEndConfiguration;

        return $this;
    }

    /**
     * @return bool
     */
    public function hasFrontEndConfiguration() {
        return !empty($this->frontEndOptions);
    }

    /**
     * @param FrontEndRowOption $frontEndOptions
     *
     * @return Row
     */
    public function addFrontEndOption($frontEndOption) {
        $this->frontEndOptions[] = $frontEndOption;

        return $this;
    }

    /**
     * Return values to be used in PHP processing (filtering and sorting)
     * For each column, it takes the raw value when exists, and formatted value otherwise.
     *
     * @return array
     */
    public function getValuesForProcessing() {
        $formattedValues = $this->getFormattedValues();
        $rawValues = $this->getRawValues();

        $valuesForProcessing = $rawValues;

        foreach ($formattedValues as $columnName => $value) {
            if (!isset($rawValues[$columnName]) || $rawValues[$columnName] === null) {
                $valuesForProcessing[$columnName] = $value;
            }
        }

        return $valuesForProcessing;
    }

    public function getValueForProcessing($columnName, $defaultValue = null) {
        $formattedValue = $this->getFormattedValue($columnName, null);
        $rawValue = $this->getRawValue($columnName, null);

        $valueForProcessing = ($rawValue !== null ? $rawValue : $formattedValue);

        if ($valueForProcessing === null) {
            return $defaultValue;
        }

        return $valueForProcessing;
    }

    /**
     * Return values to be output to client Datatable
     * For each column, it takes the formatted value when exists, and raw value otherwise.
     *
     * @return array
     */
    public function getValuesForOutput() {
        $formattedValues = $this->getFormattedValues();
        $rawValues = $this->getRawValues();

        $valuesForOutput = $rawValues;

        foreach ($formattedValues as $columnName => $value) {
            $valuesForOutput[$columnName] = $value;
        }

        return $valuesForOutput;
    }

    public function getValueForOutput($columnName, $defaultValue = null) {
        $formattedValue = $this->getFormattedValue($columnName, null);
        $rawValue = $this->getRawValue($columnName, null);

        $valueForOutput = ($formattedValue !== null ? $formattedValue : $rawValue);

        if ($valueForOutput === null) {
            return $defaultValue;
        }

        return $valueForOutput;
    }
}
