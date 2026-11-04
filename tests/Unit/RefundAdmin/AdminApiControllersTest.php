<?php

namespace BuckarooTest\Unit\RefundAdmin;

use Buckaroo\BuckarooClient;
use Buckaroo\PrestaShop\Controllers\admin\BaseApiController;
use Buckaroo\PrestaShop\Controllers\admin\Countries;
use Buckaroo\PrestaShop\Controllers\admin\Creditcards;
use Buckaroo\PrestaShop\Controllers\admin\Giftcards;
use Buckaroo\PrestaShop\Controllers\admin\Orderings;
use Buckaroo\PrestaShop\Controllers\admin\PaymentMethodConfig;
use Buckaroo\PrestaShop\Controllers\admin\PaymentMethodMode;
use Buckaroo\PrestaShop\Controllers\admin\PaymentMethods;
use Buckaroo\PrestaShop\Controllers\admin\Settings;
use Buckaroo\PrestaShop\Controllers\admin\TestCredentialsApi;
use Buckaroo\PrestaShop\Controllers\admin\VerificationMethods;
use Buckaroo\PrestaShop\Src\Entity\BkGiftcards;
use Buckaroo\PrestaShop\Src\Entity\BkOrdering;
use Buckaroo\PrestaShop\Src\Repository\CountryRepository;
use Buckaroo\PrestaShop\Src\Repository\GiftCardsRepository;
use Buckaroo\PrestaShop\Src\Repository\OrderingRepository;
use Buckaroo\PrestaShop\Src\Repository\RawCreditCardsRepository;
use Buckaroo\PrestaShop\Src\Service\BuckarooClientFactory;
use Buckaroo\PrestaShop\Src\Service\BuckarooConfigService;
use Buckaroo\PrestaShop\Src\Service\BuckarooSettingsService;
use BuckarooTest\Stubs;
use BuckarooTest\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

require_once __DIR__ . '/StubsSupport.php';

/**
 * JSON endpoints behind the Vue configuration dashboard.
 */
class AdminApiControllersTest extends TestCase
{
    /** @var string|null */
    private $method;

    protected function setUp(): void
    {
        parent::setUp();
        $this->method = $_SERVER['REQUEST_METHOD'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->method === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $this->method;
        }
        parent::tearDown();
    }

    /** Simulate the HTTP method and the raw JSON body (php://input). */
    private function http(string $method, $body = ''): void
    {
        $_SERVER['REQUEST_METHOD'] = $method;
        Stubs::returns('Tools::file_get_contents', function ($url) use ($body) {
            return $url === 'php://input' ? (is_array($body) ? json_encode($body) : $body) : false;
        });
    }

    private static function assertResponse(JsonResponse $response, int $status, array $data): void
    {
        self::assertSame($status, $response->getStatusCode());
        self::assertSame($data, $response->data);
        self::assertSame($data, json_decode($response->getContent(), true));
    }

    // ---------------------------------------------------------------- BaseApiController

    private static function base(): BaseApiController
    {
        return new BaseApiController();
    }

    public function testCsrfManagersAreOptionalSubscribedServices(): void
    {
        $services = BaseApiController::getSubscribedServices();

        self::assertSame('?Symfony\Component\Security\Csrf\CsrfTokenManagerInterface', $services['security.csrf.token_manager']);
        self::assertSame('?Symfony\Component\Security\Csrf\CsrfTokenManagerInterface', $services['buckaroo.csrf.token_manager']);
        self::assertArrayHasKey('router', $services);
    }

    public function csrfProvider(): array
    {
        $core = new TokenManager('core');
        $module = new TokenManager('module');

        return [
            'core manager first' => [['security.csrf.token_manager' => $core, 'buckaroo.csrf.token_manager' => $module], [], 'core'],
            'module manager as fallback' => [['buckaroo.csrf.token_manager' => $module], [], 'module'],
            'service of the wrong type is skipped' => [['security.csrf.token_manager' => new \stdClass(), 'buckaroo.csrf.token_manager' => $module], [], 'module'],
            'failing service is skipped' => [['buckaroo.csrf.token_manager' => $module], ['security.csrf.token_manager' => new \RuntimeException('boom')], 'module'],
            'no manager' => [[], [], null],
        ];
    }

    /**
     * @dataProvider csrfProvider
     */
    public function testCsrfManagerLookup(array $services, array $failing, ?string $expected): void
    {
        $container = new ServiceContainer($services);
        $container->failing = $failing;
        $controller = self::base();
        $controller->setContainer($container);

        $manager = self::invoke($controller, 'getCsrfTokenManager');

        self::assertSame($expected, $manager ? $manager->name : null);
    }

    public function testCsrfManagerWithoutContainerIsNull(): void
    {
        self::assertNull(self::invoke(self::base(), 'getCsrfTokenManager'));
    }

    public function jsonInputProvider(): array
    {
        return [
            'empty body' => ['', []],
            'object' => ['{"a":1,"b":[2]}', ['a' => 1, 'b' => [2]]],
            'json null' => ['null', []],
        ];
    }

    /**
     * @dataProvider jsonInputProvider
     */
    public function testJsonInput(string $body, array $expected): void
    {
        $this->http('POST', $body);

        self::assertSame($expected, self::invoke(self::base(), 'getJsonInput'));
    }

    public function testInvalidJsonIsRejected(): void
    {
        $this->http('POST', '{broken');
        $this->expectExceptionMessage('Invalid JSON input: Syntax error');

        self::invoke(self::base(), 'getJsonInput');
    }

    public function testErrorResponse(): void
    {
        self::assertResponse(self::invoke(self::base(), 'sendErrorResponse', ['Nope']), 400, ['error' => 'Nope']);
        self::assertResponse(self::invoke(self::base(), 'sendErrorResponse', ['Gone', 410]), 410, ['error' => 'Gone']);
    }

    // ---------------------------------------------------------------- simple read endpoints

    public function testCountries(): void
    {
        $repository = $this->createMock(CountryRepository::class);
        $repository->method('getCountries')->willReturn([['iso_code_2' => 'NL']]);

        self::assertResponse((new Countries($repository))->initContent(), 200, ['status' => true, 'countries' => [['iso_code_2' => 'NL']]]);
    }

    public function testCreditcards(): void
    {
        $repository = $this->createMock(RawCreditCardsRepository::class);
        $repository->method('getCreditCardsFromDB')->willReturn([['code' => 'visa']]);

        self::assertResponse((new Creditcards($repository))->initContent(), 200, ['status' => true, 'creditcards' => [['code' => 'visa']]]);
    }

    public function testPaymentMethodsAndVerificationMethods(): void
    {
        $config = $this->createMock(BuckarooConfigService::class);
        $config->method('getPaymentMethodsFromDBWithConfig')->willReturn([['name' => 'ideal']]);
        $config->method('getVerificationMethodsFromDBWithConfig')->willReturn([['name' => 'idin']]);

        self::assertResponse((new PaymentMethods($config))->initContent(), 200, ['status' => true, 'payments' => [['name' => 'ideal']]]);
        self::assertResponse((new VerificationMethods($config))->initContent(), 200, ['status' => true, 'payments' => [['name' => 'idin']]]);
    }

    // ---------------------------------------------------------------- PaymentMethodMode

    public function testPaymentMethodModeIsUpdated(): void
    {
        $config = $this->createMock(BuckarooConfigService::class);
        $config->expects(self::once())->method('updatePaymentMethodMode')->with('ideal', 'live')->willReturn(true);
        $this->http('POST', ['name' => 'ideal', 'mode' => 'live']);

        self::assertResponse((new PaymentMethodMode($config))->initContent(new Request()), 200, ['status' => true]);
    }

    public function paymentMethodModeErrorProvider(): array
    {
        return [
            'GET is not allowed' => ['GET', ['name' => 'ideal', 'mode' => 'live'], 405, 'Invalid request method'],
            'mode missing' => ['POST', ['name' => 'ideal'], 400, 'Required data not provided'],
        ];
    }

    /**
     * @dataProvider paymentMethodModeErrorProvider
     */
    public function testPaymentMethodModeErrors(string $method, array $body, int $status, string $error): void
    {
        $config = $this->createMock(BuckarooConfigService::class);
        $config->expects(self::never())->method('updatePaymentMethodMode');
        $this->http($method, $body);

        self::assertResponse((new PaymentMethodMode($config))->initContent(new Request()), $status, ['error' => $error]);
    }

    // ---------------------------------------------------------------- TestCredentialsApi

    public function credentialProvider(): array
    {
        return [[true], [false]];
    }

    /**
     * @dataProvider credentialProvider
     */
    public function testCredentialsAreCheckedWithTheGateway(bool $valid): void
    {
        $client = $this->createMock(BuckarooClient::class);
        $client->expects(self::once())->method('confirmCredential')->willReturn($valid);
        $factory = $this->createMock(BuckarooClientFactory::class);
        $factory->expects(self::once())->method('create')->with('WEBSITE', 'SECRET')->willReturn($client);
        $this->http('POST', ['website_key' => 'WEBSITE', 'secret_key' => 'SECRET']);

        self::assertResponse((new TestCredentialsApi($factory))->initContent(), 200, ['status' => $valid]);
    }

    public function missingCredentialProvider(): array
    {
        return [
            'no secret' => [['website_key' => 'WEBSITE']],
            'empty website key' => [['website_key' => '', 'secret_key' => 'SECRET']],
        ];
    }

    /**
     * @dataProvider missingCredentialProvider
     */
    public function testMissingCredentialsAreNotSent(array $body): void
    {
        $factory = $this->createMock(BuckarooClientFactory::class);
        $factory->expects(self::never())->method('create');
        $this->http('POST', $body);

        self::assertResponse((new TestCredentialsApi($factory))->initContent(), 200, ['status' => false, 'message' => 'Missing website_key or secret_key']);
    }

    // ---------------------------------------------------------------- Settings

    public function testSettingsAreRead(): void
    {
        $service = $this->createMock(BuckarooSettingsService::class);
        $service->method('getSettings')->willReturn(['mode' => 'test']);
        $this->http('GET');

        self::assertResponse((new Settings($service))->initContent(), 200, ['status' => true, 'settings' => ['mode' => 'test']]);
    }

    public function testValidSettingsAreSaved(): void
    {
        $service = $this->createMock(BuckarooSettingsService::class);
        $service->method('isValidData')->with(['mode' => 'live'])->willReturn(true);
        $service->expects(self::once())->method('updateSettings')->with(['mode' => 'live']);
        $service->method('getSettings')->willReturn(['mode' => 'live']);
        $this->http('POST', ['mode' => 'live']);

        self::assertResponse((new Settings($service))->initContent(), 200, ['status' => true, 'settings' => ['mode' => 'live']]);
    }

    public function testInvalidSettingsAreRejected(): void
    {
        $service = $this->createMock(BuckarooSettingsService::class);
        $service->method('isValidData')->willReturn(false);
        $service->expects(self::never())->method('updateSettings');
        $this->http('POST', ['mode' => 'nope']);

        self::assertResponse((new Settings($service))->initContent(), 400, ['error' => 'Invalid input data']);
    }

    // ---------------------------------------------------------------- PaymentMethodConfig

    public function testPaymentMethodConfigIsRead(): void
    {
        $config = $this->createMock(BuckarooConfigService::class);
        $config->method('getConfigArrayForMethod')->with('ideal')->willReturn(['mode' => 'live']);
        $this->http('GET');
        $this->request(['paymentName' => 'ideal']);

        self::assertResponse((new PaymentMethodConfig($config))->initContent(), 200, ['status' => true, 'config' => ['value' => ['mode' => 'live']]]);
    }

    public function testPaymentMethodConfigIsSaved(): void
    {
        $config = $this->createMock(BuckarooConfigService::class);
        $config->expects(self::once())->method('updatePaymentMethodConfig')->with('ideal', ['mode' => 'test'])->willReturn(true);
        $this->http('POST', ['mode' => 'test']);
        $this->request(['paymentName' => 'ideal']);

        self::assertResponse((new PaymentMethodConfig($config))->initContent(), 200, ['status' => true]);
    }

    public function paymentMethodConfigErrorProvider(): array
    {
        return [
            'GET without name' => ['GET', [], [], 'Payment name is missing.'],
            'POST without name' => ['POST', [], ['mode' => 'test'], 'Invalid data provided.'],
            'POST without data' => ['POST', ['paymentName' => 'ideal'], [], 'Invalid data provided.'],
        ];
    }

    /**
     * @dataProvider paymentMethodConfigErrorProvider
     */
    public function testPaymentMethodConfigErrors(string $method, array $query, array $body, string $error): void
    {
        $config = $this->createMock(BuckarooConfigService::class);
        $config->expects(self::never())->method('updatePaymentMethodConfig');
        $this->http($method, $body);
        $this->request($query);

        self::assertResponse((new PaymentMethodConfig($config))->initContent(), 400, ['error' => $error]);
    }

    public function otherMethodProvider(): array
    {
        return [['Settings'], ['PaymentMethodConfig'], ['Orderings'], ['Giftcards']];
    }

    /**
     * @dataProvider otherMethodProvider
     */
    public function testOtherHttpMethodsReturnNothing(string $controller): void
    {
        $this->http('DELETE');
        switch ($controller) {
            case 'Settings':
                $instance = new Settings($this->createMock(BuckarooSettingsService::class));
                break;
            case 'PaymentMethodConfig':
                $instance = new PaymentMethodConfig($this->createMock(BuckarooConfigService::class));
                break;
            case 'Orderings':
                $instance = new Orderings(new RecordingEntityManager($this->createMock(OrderingRepository::class)));
                break;
            default:
                $instance = new Giftcards(new RecordingEntityManager($this->createMock(GiftCardsRepository::class)));
        }

        self::assertNull($instance->initContent());
    }

    // ---------------------------------------------------------------- Orderings

    public function testOrderingIsReadForACountry(): void
    {
        $repository = $this->createMock(OrderingRepository::class);
        $repository->method('getOrdering')->willReturnCallback(function ($country) {
            return ['country' => $country, 'value' => [1, 2]];
        });
        $em = new RecordingEntityManager($repository);
        $controller = new Orderings($em);
        $this->http('GET');

        $this->request(['country' => 'NL']);
        self::assertResponse($controller->initContent(), 200, ['status' => true, 'orderings' => ['country' => 'NL', 'value' => [1, 2]]]);

        $this->request(['country' => '']);
        self::assertResponse($controller->initContent(), 200, ['status' => true, 'orderings' => ['country' => null, 'value' => [1, 2]]]);
        self::assertSame([BkOrdering::class], $em->repositoryRequests);
    }

    public function testOrderingIsSaved(): void
    {
        $repository = $this->createMock(OrderingRepository::class);
        $repository->expects(self::once())->method('updateOrdering')->with('[3,1,2]', 5)->willReturn(true);
        $this->http('POST', ['country_id' => 5, 'value' => [3, 1, 2]]);

        self::assertResponse((new Orderings(new RecordingEntityManager($repository)))->initContent(), 200, ['status' => true]);
    }

    public function testOrderingWithoutValueIsRejected(): void
    {
        $repository = $this->createMock(OrderingRepository::class);
        $repository->expects(self::never())->method('updateOrdering');
        $this->http('POST', ['country_id' => 5, 'value' => []]);

        self::assertResponse((new Orderings(new RecordingEntityManager($repository)))->initContent(), 200, ['status' => false, 'message' => 'Missing or invalid data']);
    }

    // ---------------------------------------------------------------- Giftcards

    /**
     * @return GiftCardsRepository&\PHPUnit\Framework\MockObject\MockObject
     */
    private function giftcards(): GiftCardsRepository
    {
        return $this->createMock(GiftCardsRepository::class);
    }

    public function testGiftcardsListStandardAndCustomCards(): void
    {
        $repository = $this->giftcards();
        $repository->method('getGiftCards')->willReturnCallback(function ($custom) {
            return $custom ? [['code' => 'mycard']] : [['code' => 'boekenbon']];
        });
        $em = new RecordingEntityManager($repository);
        $this->http('GET');

        self::assertResponse((new Giftcards($em))->initContent(), 200, [
            'status' => true,
            'giftcards' => [['code' => 'boekenbon']],
            'custom_giftcards' => [['code' => 'mycard']],
        ]);
        self::assertSame([BkGiftcards::class], $em->repositoryRequests);
    }

    public function testCustomGiftcardIsCreated(): void
    {
        $repository = $this->giftcards();
        $repository->expects(self::once())->method('createGiftCard')->with('My card', 'mycard', null)->willReturn(['id' => 9]);
        $this->http('POST', ['name' => 'My card', 'service_code' => 'mycard', 'logo_url' => '']);

        self::assertResponse((new Giftcards(new RecordingEntityManager($repository)))->initContent(), 200, ['status' => true, 'custom_giftcard' => ['id' => 9]]);
    }

    public function testGiftcardWithoutAnyDataIsRejected(): void
    {
        $repository = $this->giftcards();
        $repository->expects(self::never())->method('createGiftCard');
        $repository->expects(self::never())->method('editGiftCard');
        $this->http('POST', ['name' => '', 'service_code' => null]);
        $controller = new Giftcards(new RecordingEntityManager($repository));
        $expected = ['status' => false, 'message' => 'Missing or invalid data'];

        self::assertResponse($controller->initContent(), 200, $expected);
        self::assertResponse($controller->editGiftCard(), 200, $expected);
    }

    public function incompleteGiftcardProvider(): array
    {
        return [
            'logo only' => [['logo_url' => 'https://logo']],
            'name without service code' => [['name' => 'My card', 'logo_url' => 'https://logo']],
            'service code without name' => [['service_code' => 'mycard']],
        ];
    }

    /**
     * @dataProvider incompleteGiftcardProvider
     */
    public function testGiftcardWithoutNameOrServiceCodeIsRejected(array $post): void
    {
        $repository = $this->giftcards();
        $repository->expects(self::never())->method('createGiftCard');
        $repository->expects(self::never())->method('editGiftCard');
        $this->http('POST', ['id' => 9] + $post);
        $controller = new Giftcards(new RecordingEntityManager($repository));
        $expected = ['status' => false, 'message' => 'Missing or invalid data'];

        self::assertResponse($controller->initContent(), 200, $expected);
        self::assertResponse($controller->editGiftCard(), 200, $expected);
    }

    public function testCustomGiftcardIsEdited(): void
    {
        $repository = $this->giftcards();
        $repository->expects(self::once())->method('editGiftCard')->with(9, 'Renamed', 'mycard', 'https://logo')->willReturn(['id' => 9, 'name' => 'Renamed']);
        $this->http('POST', ['id' => 9, 'name' => 'Renamed', 'service_code' => 'mycard', 'logo_url' => 'https://logo']);

        self::assertResponse((new Giftcards(new RecordingEntityManager($repository)))->editGiftCard(), 200, ['status' => true, 'custom_giftcard' => ['id' => 9, 'name' => 'Renamed']]);
    }

    public function removeProvider(): array
    {
        return [
            'removed' => [true, ['status' => true, 'message' => 'Gift card with ID: 9 has been successfully deleted.']],
            'repository failure' => [false, ['status' => false, 'message' => 'Failed to delete gift card with ID: 9']],
        ];
    }

    /**
     * @dataProvider removeProvider
     */
    public function testCustomGiftcardIsRemoved(bool $result, array $expected): void
    {
        $repository = $this->giftcards();
        $repository->expects(self::once())->method('removeGiftCard')->with(9)->willReturn($result);
        $this->http('POST', ['id' => 9]);

        self::assertResponse((new Giftcards(new RecordingEntityManager($repository)))->removeGiftCard(), 200, $expected);
    }

    public function testRemoveWithoutIdIsRejected(): void
    {
        $repository = $this->giftcards();
        $repository->expects(self::never())->method('removeGiftCard');
        $this->http('POST', ['id' => 0]);

        self::assertResponse((new Giftcards(new RecordingEntityManager($repository)))->removeGiftCard(), 200, ['status' => false, 'message' => 'Missing or invalid ID']);
    }
}
