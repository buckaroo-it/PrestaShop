<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * It is available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this file
 *
 *  @author    Buckaroo.nl <plugins@buckaroo.nl>
 *  @copyright Copyright (c) Buckaroo B.V.
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace BuckarooTest\Unit\Data;

use Buckaroo\PrestaShop\Src\ErrorReporting\CurlTransport;
use BuckarooTest\TestCase;

/**
 * Sends to a PHP built-in server on 127.0.0.1 that records the request.
 * Nothing leaves the machine.
 */
class CurlTransportTest extends TestCase
{
    /** @var resource|null */
    private static $server;

    /** @var string */
    private static $baseUrl = '';

    /** @var string */
    private static $dir = '';

    public static function setUpBeforeClass(): void
    {
        self::$dir = sys_get_temp_dir() . '/buckaroo3-curl-transport-' . getmypid() . '-' . mt_rand();
        @mkdir(self::$dir, 0777, true);
        file_put_contents(self::$dir . '/router.php', '<?php
            $headers = [];
            foreach ($_SERVER as $key => $value) {
                if (strpos($key, "HTTP_") === 0 || $key === "CONTENT_TYPE") {
                    $headers[$key] = $value;
                }
            }
            file_put_contents(__DIR__ . "/request.json", json_encode([
                "method" => $_SERVER["REQUEST_METHOD"],
                "uri" => $_SERVER["REQUEST_URI"],
                "headers" => $headers,
                "body" => file_get_contents("php://input"),
            ]));
            header("Content-Type: application/json");
            echo "{\"accepted\":true}";
        ');

        // Reserve a free port, then hand it to the server.
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        if ($probe === false) {
            return;
        }
        $port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);

        $null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        $process = proc_open(
            [PHP_BINARY, '-n', '-S', '127.0.0.1:' . $port, self::$dir . '/router.php'],
            [0 => ['file', $null, 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
            $pipes
        );
        if (!is_resource($process)) {
            return;
        }
        self::$server = $process;

        for ($i = 0; $i < 100; ++$i) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($socket) {
                fclose($socket);
                self::$baseUrl = 'http://127.0.0.1:' . $port;

                return;
            }
            usleep(20000);
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
        self::$server = null;
        @unlink(self::$dir . '/router.php');
        @unlink(self::$dir . '/request.json');
        @rmdir(self::$dir);
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (self::$baseUrl === '') {
            self::markTestSkipped('could not start the local PHP test server');
        }
        @unlink(self::$dir . '/request.json');
    }

    private static function receivedRequest(): array
    {
        $file = self::$dir . '/request.json';
        self::assertFileExists($file, 'the local server received no request');

        return json_decode((string) file_get_contents($file), true);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function senderProvider(): array
    {
        return [
            'curl' => ['send'],
            'stream fallback' => ['sendWithStream'],
        ];
    }

    /**
     * @dataProvider senderProvider
     */
    public function testPostsTheBodyWithTheHeaders(string $sender): void
    {
        if ($sender === 'send' && !function_exists('curl_init')) {
            self::markTestSkipped('ext-curl is not installed');
        }
        if ($sender === 'sendWithStream' && !filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            self::markTestSkipped('allow_url_fopen is disabled');
        }
        $transport = new CurlTransport();
        $url = self::$baseUrl . '/api/v1/reports';
        $headers = ['Content-Type' => 'application/json', 'X-Buckaroo-Ingest-Key' => 'key-1'];
        $body = '{"reports":[{"level":"error"}]}';

        if ($sender === 'send') {
            $transport->send($url, $headers, $body);
        } else {
            // Private fallback for shops without ext-curl, it receives the formatted header lines.
            self::invoke($transport, 'sendWithStream', [$url, ['Content-Type: application/json', 'X-Buckaroo-Ingest-Key: key-1'], $body]);
        }

        $request = self::receivedRequest();
        self::assertSame('POST', $request['method']);
        self::assertSame('/api/v1/reports', $request['uri']);
        self::assertSame('application/json', $request['headers']['CONTENT_TYPE']);
        self::assertSame('key-1', $request['headers']['HTTP_X_BUCKAROO_INGEST_KEY']);
        self::assertSame($body, $request['body']);
    }

    /**
     * @dataProvider senderProvider
     */
    public function testAnUnreachableCollectorIsIgnored(string $sender): void
    {
        $transport = new CurlTransport();
        $start = microtime(true);

        // Port 1 on loopback refuses the connection immediately.
        if ($sender === 'send') {
            $transport->send('http://127.0.0.1:1/', ['Accept' => 'application/json'], '{}');
        } else {
            self::invoke($transport, 'sendWithStream', ['http://127.0.0.1:1/', [], '{}']);
        }

        self::assertLessThan(CurlTransport::TIMEOUT_MS / 1000 + 1, microtime(true) - $start);
    }
}
