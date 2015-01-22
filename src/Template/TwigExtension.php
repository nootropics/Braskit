<?php
/*
 * Copyright (C) 2013, 2014 Frank Usrs
 *
 * See LICENSE for terms and conditions of use.
 */

namespace Braskit\Template;

class TwigExtension extends \Twig_Extension {
    protected $functions = [];
    protected $filters = [];
    protected $globals = [];

    public function setFilters(array $filters) {
        foreach ($filters as $key => $filter) {
            $this->filters[$key] = $filter;
        }
    }

    public function setFunctions(array $functions) {
        foreach ($functions as $key => $function) {
            $this->functions[$key] = $function;
        }
    }

    public function setGlobals(array $globals) {
        foreach ($globals as $key => $global) {
            $this->globals[$key] = $global;
        }
    }

    public function getFunctions() {
        $functions = [];

        foreach ($this->functions as $name => $function) {
            $functions[] = new \Twig_SimpleFunction($name, $function);     
        }

        return $functions;
    }

    public function getFilters() {
        $filters = [];

        foreach ($this->filters as $name => $filter) {
            $filters[] = new \Twig_SimpleFilter($name, $filter);     
        }

        return $filters;
    }

    public function getGlobals() {
        return $this->globals;
    }

    public function getName() {
        return 'braskit';
    }
}
