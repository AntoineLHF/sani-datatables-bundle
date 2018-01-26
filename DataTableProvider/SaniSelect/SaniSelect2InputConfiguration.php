<?php

namespace Sanilea\SanidatatablesBundle\DataTableProvider\SaniSelect;

use Sanilea\SanidatatablesBundle\DataTableProvider\InputConfiguration;

class SaniSelect2InputConfiguration extends InputConfiguration
{
    /**
     * Load the base input options.
     
     Raw Input Example :
     
     array:5 [
     "search_query" => "my search",
     "paging_offset" => "0"
     "paging_limit" => "10"
     "my_owm_parameter" => "1430124617236"
     ]
     
     * @param $inputOptions (as sent by client RSelect2)
     */
    public function loadRawInputs($inputOptions)
    {
        parent::loadRawInputs($inputOptions);
    }
}
