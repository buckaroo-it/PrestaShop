<?php

namespace BuckarooTest\Unit\Controller\Front\Request;

use BuckarooTest\TestCase;

require_once __DIR__ . '/RequestTestSupport.php';

/**
 * Buckaroo3ErrorModuleFrontController: the error page the request controller
 * redirects to when the module is not configured.
 */
class ErrorControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        loadControllers();
        \Module::$instances['buckaroo3'] = new RequestModuleDouble();
    }

    public function testErrorAndInvoiceFromTheRequestAreShown(): void
    {
        $this->request(['error' => 'Buckaroo Plug-in is not properly configured.', 'invoice' => 'INV-9']);
        $controller = new \Buckaroo3ErrorModuleFrontController();

        $controller->initContent();

        self::assertFalse($controller->display_column_left);
        self::assertFalse($controller->display_column_right);
        self::assertTrue($controller->ssl);
        self::assertSame('module:buckaroo3/views/templates/front/error.tpl', $controller->template);
        self::assertSame('INV-9', $this->context()->smarty->getTemplateVars('order_id'));
        self::assertSame('Buckaroo Plug-in is not properly configured.', $this->context()->smarty->getTemplateVars('error_message'));
    }

    public function testWithoutErrorTheDefaultMessageIsShown(): void
    {
        $controller = new \Buckaroo3ErrorModuleFrontController();

        $controller->initContent();

        self::assertNull($this->context()->smarty->getTemplateVars('order_id'));
        self::assertSame(
            'Your payment was unsuccessful. Please try again or choose another payment method.',
            $this->context()->smarty->getTemplateVars('error_message')
        );
    }
}
