<?php

namespace Sanilea\SaniDatatablesBundle\DataTableProvider\SaniSelect;

use Sanilea\SaniDatatablesBundle\DataTableProvider\AbstractSaniDataTableProvider;

/**
 * Class AbstractSaniSelect2DataTableProvider.
 */
abstract class AbstractSaniSelect2DataTableProvider extends AbstractSaniDataTableProvider {
    /**
     * Create a InputConfiguration based on raw input from a client RDataTable.
     *
     * @param array $inputParameters
     */
    public function readRawInputParameters($inputParameters) {
        $this->setInputConfiguration(SaniSelect2InputConfiguration::fromRawInput($inputParameters));
    }

    public function getAllRowValuesForOutput() {
        $rowOutputs = array();

        foreach ($this->rows as $row) {
            $rowValuesForOutput = $row->getValuesForOutput();
            $rSelect2RowConfiguration = $row->getFrontEndConfiguration( RowRSelect2FrontEndConfiguration::class );
            if( $rSelect2RowConfiguration !== null ) {
                if( $rSelect2RowConfiguration->isDisabled() ) {
                    $rowValuesForOutput['disabled'] = $rSelect2RowConfiguration->getDisabled();
                }
            }

            $rowOutputs[] = $rowValuesForOutput;
        }

        return $rowOutputs;
    }

    /**
     * Output result rows as expected by RDataTable.
     *
     * @return array
     */
    public function getFormattedOutput() {
        return array(
            'userData'        => $this->getUserOutputDatas(),
            'recordsTotal'    => $this->getTotalRowsCount(),
            'recordsFiltered' => $this->getFilteredRowCount(),
            'results'           => $this->getAllRowValuesForOutput(),
        );
    }
}
