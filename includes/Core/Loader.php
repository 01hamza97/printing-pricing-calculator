<?php
namespace PPC\Core;

use PPC\Admin\AdminInit;
use PPC\Frontend\ShortcodeHandler;
use PPC\Parameters\ParameterEdit;
use PPC\Parameters\ParametersInit;
use PPC\Parameters\ParametersList;
use PPC\Products\ProductEdit;
use PPC\Products\ProductList;

class Loader {
    public function init() {
        new AdminInit();
        new ParametersInit();
        new ParametersList();
        new ParameterEdit();
        new ProductEdit();
        new ProductList();
        new ShortcodeHandler();
    }
}
