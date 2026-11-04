<?php

namespace BuckarooTest\Unit\RefundAdmin;

use BuckarooTest\TestCase;

require_once __DIR__ . '/StubsSupport.php';
require_once dirname(__DIR__, 3) . '/controllers/admin/AdminBuckarooController.php';
require_once dirname(__DIR__, 3) . '/controllers/admin/AdminBuckaroologController.php';
require_once dirname(__DIR__, 3) . '/api/corelogger.php';

/** The legacy (ModuleAdminController) back-office tabs. */
class LegacyAdminControllersTest extends TestCase
{
    /** @var string */
    private $logFile;

    /** @var string|null previous content of the shared test log file */
    private $logBackup;

    protected function setUp(): void
    {
        parent::setUp();
        $module = new \Module();
        $module->name = 'buckaroo3';
        $module->tab = 'payments_gateways';
        \Module::$instances['buckaroo3'] = $module;

        $this->logFile = \CoreLogger::logDirectory() . 'report_log.txt';
        $this->logBackup = is_file($this->logFile) ? (string) file_get_contents($this->logFile) : null;
    }

    protected function tearDown(): void
    {
        if ($this->logBackup === null) {
            @unlink($this->logFile);
        } else {
            file_put_contents($this->logFile, $this->logBackup);
        }
        parent::tearDown();
    }

    public function testBuckarooTabRedirectsToTheModuleConfiguration(): void
    {
        $controller = new \AdminBuckarooController();

        $url = $this->expectRedirect(function () use ($controller) {
            $controller->initContent();
        });

        self::assertSame(
            'https://shop.test/admin/AdminModules&configure=buckaroo3&tab_module=payments_gateways&module_name=buckaroo3&token=admintoken',
            $url
        );
    }

    public function testLogControllerUsesTheDefaultLanguageAndBootstrap(): void
    {
        \Configuration::updateValue('PS_LANG_DEFAULT', 4);

        $controller = new \AdminBuckaroologController();

        self::assertSame(4, $controller->lang);
        self::assertTrue($controller->bootstrap);
    }

    public function testLogIsListedNewestFirst(): void
    {
        @mkdir(dirname($this->logFile), 0777, true);
        file_put_contents($this->logFile, "2026-01-01 10:00:00|||first entry\nline without time\n2026-01-02 11:00:00|||latest entry\n");
        $controller = new \AdminBuckaroologController();

        $html = $controller->renderList();

        self::assertSame('rendered:buckaroolog.tpl', $html);
        self::assertSame([
            1 => ['id' => 1, 'time' => '2026-01-02 11:00:00', 'value' => "latest entry\n"],
            2 => ['id' => 2, 'time' => 'unknown', 'value' => "line without time\n"],
            3 => ['id' => 3, 'time' => '2026-01-01 10:00:00', 'value' => "first entry\n"],
        ], $this->context()->smarty->getTemplateVars('data'));
    }

    public function testEmptyOrMissingLogShowsNoRows(): void
    {
        @unlink($this->logFile);
        $controller = new \AdminBuckaroologController();

        $controller->renderList();
        self::assertSame([], $this->context()->smarty->getTemplateVars('data'));

        @mkdir(dirname($this->logFile), 0777, true);
        file_put_contents($this->logFile, '');
        $controller->renderList();
        self::assertSame([], $this->context()->smarty->getTemplateVars('data'));
    }
}
