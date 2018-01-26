<?php

namespace Sanilea\SaniDatatablesBundle\DataTableProvider\SaniSelect;

use Sanilea\SaniDatatablesBundle\DataTableProvider\RowFrontEndConfiguration;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RowSaniSelect2FrontEndConfiguration extends RowFrontEndConfiguration {

    protected $disabled;

    public function __construct( $options = array() ) {

        $resolver = new OptionsResolver();

        $defaults = array(
            'disabled' => true
        );

        $resolver->setDefaults($defaults);
        $settings = $resolver->resolve($options);

        $this->init($settings);

    }

    public function init( $options ) {
        $this->setDisabled( $options['disabled'] );
    }


    public function getDisabled() {
        return $this->disabled;
    }

    public function isDisabled() {
        return $this->getDisabled();
    }

    public function setDisabled( $disabled ) {
        $this->disabled = $disabled;

        return $this;
    }


}