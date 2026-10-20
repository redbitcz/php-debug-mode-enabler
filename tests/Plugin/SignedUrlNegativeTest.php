<?php

/** @testCase */
declare(strict_types=1);

namespace Redbitcz\DebugModeTests\Plugin;

use Firebase\JWT\JWT;
use Redbitcz\DebugMode\Detector;
use Redbitcz\DebugMode\Plugin\SignedUrl;
use Redbitcz\DebugMode\Plugin\SignedUrlVerificationException;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../bootstrap.php';

class SignedUrlNegativeTest extends TestCase
{
    private const string Key = 'test-key-with-at-least-32-bytes-for-hs256';
    private const int Now = 1600000000;
    private array $originalGet;
    private array $originalServer;
    private ?int $originalTimestamp;

    protected function setUp(): void
    {
        $this->originalGet = $_GET;
        $this->originalServer = $_SERVER;
        $this->originalTimestamp = JWT::$timestamp;
        JWT::$timestamp = self::Now;
        $_SERVER['HTTP_HOST'] = 'host.tld';
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['REQUEST_METHOD'] = 'GET';
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
        $_SERVER = $this->originalServer;
        JWT::$timestamp = $this->originalTimestamp;
    }

    private function setRequestToken(string $token): void
    {
        $_GET['_debug'] = $token;
        $_SERVER['REQUEST_URI'] = '/?_debug=' . rawurlencode($token);
    }

    public function getInvalidTokens(): array
    {
        return array_map(static fn(string $case): array => [$case], [
            'segments', 'header-json', 'payload-json', 'base64', 'signature', 'tampering',
            'expired', 'future', 'audience', 'issuer', 'mode', 'value', 'missing-exp', 'method', 'url',
        ]);
    }

    /** @dataProvider getInvalidTokens */
    public function testRejectedThroughDetector(string $case): void
    {
        $plugin = SignedUrl::create(self::Key, audience: 'test');
        $plugin->setTimestamp(self::Now);
        $token = $plugin->getToken('https://host.tld/', ['get'], self::Now + 60,
            SignedUrl::ModeRequest, SignedUrl::ValueEnable);
        $detector = (new Detector(0))->appendPlugin($plugin);
        $this->setRequestToken($token);
        Assert::true($detector->resolve()); // Positive control for the same request and verifier.

        $payload = (array)$plugin->getJwt()->decode($token);
        switch ($case) {
            case 'segments': $token = 'one.two'; break;
            case 'header-json': $token = 'eA.e30.eA'; break;
            case 'payload-json': $token = explode('.', $token)[0] . '.eA.eA'; break;
            case 'base64': $token = '!.!.!'; break;
            case 'signature':
                $parts = explode('.', $token);
                $parts[2] = JWT::urlsafeB64Encode(str_repeat("\0", 32));
                $token = implode('.', $parts);
                break;
            case 'tampering':
                $parts = explode('.', $token);
                $payload['exp'] = self::Now + 3600;
                $parts[1] = JWT::urlsafeB64Encode(json_encode($payload, JSON_THROW_ON_ERROR));
                $token = implode('.', $parts);
                break;
            default:
                switch ($case) {
                    case 'expired': $payload['exp'] = self::Now; break;
                    case 'future': $payload['iat'] = self::Now + 60; break;
                    case 'audience': $payload['aud'] = 'another-app'; break;
                    case 'issuer': $payload['iss'] = 'another-issuer'; break;
                    case 'mode': $payload['mod'] = 999; break;
                    case 'value': $payload['val'] = 999; break;
                    case 'missing-exp': unset($payload['exp']); break;
                    case 'method': $payload['meth'] = ['post']; break;
                    case 'url': $payload['sub'] = 'https://host.tld/other'; break;
                }
                $token = JWT::encode($payload, self::Key, 'HS256');
        }
        $this->setRequestToken($token);
        Assert::exception(fn() => $plugin->verifyRequest(), SignedUrlVerificationException::class);
        Assert::null($detector->resolve());
        Assert::false($detector->isDebugMode());
        Assert::true($detector->isProductionMode());
    }

    public function testAudienceRemainsOptional(): void
    {
        $plugin = SignedUrl::create(self::Key);
        $plugin->setTimestamp(self::Now);
        $token = $plugin->getToken('https://host.tld/', ['get'], self::Now + 60,
            SignedUrl::ModeRequest, SignedUrl::ValueEnable);
        $payload = (array)$plugin->getJwt()->decode($token);
        unset($payload['aud']);
        $this->setRequestToken(JWT::encode($payload, self::Key, 'HS256'));
        Assert::true((new Detector(0))->appendPlugin($plugin)->resolve());
    }
}

(new SignedUrlNegativeTest())->run();
