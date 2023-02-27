<?php
/**
 * Extend the HestiaCP Pluginable object with our Ghost object for
 * allocating Ghost instances.
 * 
 * @version 1.0.0
 * @license GPL-3.0
 * @link https://github.com/steveorevo/hestiacp-ghost
 * 
 */

if ( ! class_exists( 'Ghost') ) {
    class Ghost {
        /**
         * Constructor, listen for the invoke, POST, and render events
         */
        public function __construct() {
            global $hcpp;
            $hcpp->ghost = $this;
        }
    }
    new Ghost();
}