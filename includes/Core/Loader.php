<?php
namespace PPC\Core;

use PPC\Admin\AdminInit;
use PPC\Calculator\Quotation;
use PPC\Checkout\CheckoutInit;
use PPC\Frontend\ShortcodeHandler;
use PPC\Parameters\ParameterEdit;
use PPC\Parameters\ParametersInit;
use PPC\Parameters\ParametersList;
use PPC\Products\ProductEdit;
use PPC\Products\ProductList;
use PPC\Products\ProductsInit;

class Loader {
    public function init() {
        new AdminInit();
        new ProductsInit();
        new ParametersInit();
        new ParametersList();
        new ParameterEdit();
        new ProductEdit();
        new ProductList();
        new ShortcodeHandler();
        new Quotation();
        HideWooPages::init();
        new CheckoutInit();
        
    }
}
