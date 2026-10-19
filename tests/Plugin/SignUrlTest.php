<?php

/**
 * The MIT License (MIT)
 * Copyright (c) 2023 Redbit s.r.o., Jakub Bouček
 *
 * @noinspection PhpUnused
 * @testCase
 */

declare(strict_types=1);

namespace Redbitcz\DebugModeTests\Plugin;

use Composer\InstalledVersions;
use Firebase\JWT\JWT;
use LogicException;
use Redbitcz\DebugMode\Plugin\JWT\JWTFirebaseV7;
use Redbitcz\DebugMode\Plugin\SignedUrl;
use Redbitcz\DebugMode\Plugin\SignedUrlVerificationException;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../bootstrap.php';

class SignUrlTest extends TestCase
{
    private const string KeyHs256 = "zhYiojmp7O3VYQNuW0C5rS0VgFNgoAvuxW4IdS/0tn8";


    public function testAdapter(): void
    {
        Assert::true(JWTFirebaseV7::isAvailable());
        Assert::type(JWTFirebaseV7::class, SignedUrl::create(self::KeyHs256)->getJwt());
    }

    public function getUnsupportedVersions(): array
    {
        return [['5.5.1.0'], ['6.11.1.0'], ['8.0.0.0-beta1'], ['8.0.0.0'], ['dev-main'], [null]];
    }

    /** @dataProvider getUnsupportedVersions */
    public function testUnsupportedVersion(?string $version): void
    {
        $original = InstalledVersions::getAllRawData()[0];
        $data = $original;
        if ($version === null) {
            unset($data['versions']['firebase/php-jwt']);
        } else {
            $data['versions']['firebase/php-jwt']['version'] = $version;
        }
        // Keep autoloading, but isolate the metadata from registered vendor directories.
        $loaders = \Composer\Autoload\ClassLoader::getRegisteredLoaders();
        foreach ($loaders as $loader) {
            $loader->unregister();
            spl_autoload_register([$loader, 'loadClass']);
        }
        InstalledVersions::reload($data);
        try {
            Assert::false(JWTFirebaseV7::isAvailable());
            Assert::exception(static function () {
                SignedUrl::create(self::KeyHs256);
            }, LogicException::class, '%a%requires JWT library: firebase/php-jwt version ^7.0');
            Assert::false(\Redbitcz\DebugMode\Detector::detect(0));
        } finally {
            InstalledVersions::reload($original);
            foreach ($loaders as $loader) {
                spl_autoload_unregister([$loader, 'loadClass']);
                $loader->register();
            }
        }
    }

    public function testAdapterTimestamp(): void
    {
        $adapter = new JWTFirebaseV7(self::KeyHs256);
        $original = JWT::$timestamp;
        try {
            $adapter->setTimestamp(1600000000);
            $token = $adapter->encode(['exp' => 1600000060]);
            Assert::same(1600000060, $adapter->decode($token)->exp);
            $adapter->setTimestamp(1600000060);
            Assert::exception(static function () use ($adapter, $token) {
                $adapter->decode($token);
            }, \Firebase\JWT\ExpiredException::class);
            $adapter->setTimestamp(null);
            Assert::null(JWT::$timestamp);
        } finally {
            JWT::$timestamp = $original;
        }
    }

    public function testRsaKeyObjects(): void
    {
        if (!extension_loaded('openssl')) {
            $this->skip('OpenSSL is required for RSA key objects');
        }
        $privateKey = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        Assert::type(\OpenSSLAsymmetricKey::class, $privateKey);
        $details = openssl_pkey_get_details($privateKey);
        $publicKey = openssl_pkey_get_public($details['key']);
        Assert::type(\OpenSSLAsymmetricKey::class, $publicKey);

        $signer = SignedUrl::create($privateKey, 'RS256', 'test.keys');
        $verifier = SignedUrl::create($publicKey, 'RS256', 'test.keys');
        $expires = time() + 60;
        $url = $signer->signUrl('https://host.tld/path', $expires);
        Assert::same([['get'], SignedUrl::ModeRequest, SignedUrl::ValueEnable, $expires], $verifier->verifyUrl($url));
    }

    public function testResourceKeyRejected(): void
    {
        $resource = fopen('php://memory', 'r+');
        try {
            Assert::exception(static function () use ($resource) {
                SignedUrl::create($resource);
            }, \TypeError::class);
            Assert::exception(static function () use ($resource) {
                new JWTFirebaseV7($resource);
            }, \TypeError::class);
        } finally {
            fclose($resource);
        }
    }

    public function testShortKey(): void
    {
        $plugin = SignedUrl::create('too-short', 'HS256');
        Assert::exception(static function () use ($plugin) {
            $plugin->signUrl('https://host.tld/path', '+1 hour');
        }, \DomainException::class);
    }

    public function testSign(): void
    {
        $audience = 'test.' . __FUNCTION__;

        $plugin = SignedUrl::create(self::KeyHs256, 'HS256', $audience);
        $plugin->setTimestamp(1600000000);
        $token = $plugin->signUrl('https://host.tld/path', 1600000600);

        $expected = 'https://host.tld/path?_debug=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJjei5yZWRiaXQuZGVi'
            . 'dWcudXJsIiwiYXVkIjoidGVzdC50ZXN0U2lnbiIsImlhdCI6MTYwMDAwMDAwMCwiZXhwIjoxNjAwMDAwNjAwLCJzdWIiOiJodHRw'
            . 'czovL2hvc3QudGxkL3BhdGgiLCJtZXRoIjpbImdldCJdLCJtb2QiOjAsInZhbCI6MX0.h2TAkamMzGVQkre-F9kaCSmg3irRt9qv'
            . '84oUcxj9gv0';

        Assert::equal($expected, $token);
    }

    public function testSignQuery(): void
    {
        $audience = 'test.' . __FUNCTION__;

        $plugin = SignedUrl::create(self::KeyHs256, 'HS256', $audience);
        $plugin->setTimestamp(1600000000);
        $token = $plugin->signUrl('https://host.tld/path?query=value', 1600000600);

        $expected = 'https://host.tld/path?query=value&_debug=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJjei5y'
            . 'ZWRiaXQuZGVidWcudXJsIiwiYXVkIjoidGVzdC50ZXN0U2lnblF1ZXJ5IiwiaWF0IjoxNjAwMDAwMDAwLCJleHAiOjE2MDAwMDA2'
            . 'MDAsInN1YiI6Imh0dHBzOi8vaG9zdC50bGQvcGF0aD9xdWVyeT12YWx1ZSIsIm1ldGgiOlsiZ2V0Il0sIm1vZCI6MCwidmFsIjox'
            . 'fQ.UXB2AIKChgunDzoY7hcWNA7vg7j6sf3VvOWFw0OKz8k';

        Assert::equal($expected, $token);
    }

    public function testSignFragment(): void
    {
        $audience = 'test.' . __FUNCTION__;

        $plugin = SignedUrl::create(self::KeyHs256, 'HS256', $audience);
        $plugin->setTimestamp(1600000000);
        $token = $plugin->signUrl('https://host.tld/path?query=value#fragment', 1600000600);

        $expected = 'https://host.tld/path?query=value&_debug=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJjei5y'
            . 'ZWRiaXQuZGVidWcudXJsIiwiYXVkIjoidGVzdC50ZXN0U2lnbkZyYWdtZW50IiwiaWF0IjoxNjAwMDAwMDAwLCJleHAiOjE2MDAw'
            . 'MDA2MDAsInN1YiI6Imh0dHBzOi8vaG9zdC50bGQvcGF0aD9xdWVyeT12YWx1ZSIsIm1ldGgiOlsiZ2V0Il0sIm1vZCI6MCwidmFs'
            . 'IjoxfQ.-Aww363VPD0aSi5QK1JH2v_4yFU5DX5aRvbsxqtcJSg#fragment';

        Assert::equal($expected, $token);
    }

    public function testGetToken(): void
    {
        $audience = 'test.' . __FUNCTION__;

        $plugin = SignedUrl::create(self::KeyHs256, 'HS256', $audience);
        $plugin->setTimestamp(1600000000);
        $token = $plugin->getToken(
            'https://host.tld/path?query=value',
            ['get'],
            1600000600,
            SignedUrl::ModeRequest,
            SignedUrl::ValueEnable
        );

        $expected = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJjei5yZWRiaXQuZGVidWcudXJsIiwiYXVkIjoidGVzdC50Z'
            . 'XN0R2V0VG9rZW4iLCJpYXQiOjE2MDAwMDAwMDAsImV4cCI6MTYwMDAwMDYwMCwic3ViIjoiaHR0cHM6Ly9ob3N0LnRsZC9wYXRoP'
            . '3F1ZXJ5PXZhbHVlIiwibWV0aCI6WyJnZXQiXSwibW9kIjowLCJ2YWwiOjF9.LrE8DVuvXiP4u3cHXiSABIOXI4WlHFBxf2g-DRYW'
            . 'xNQ';

        Assert::equal($expected, $token);
    }

    public function testVerifyToken(): void
    {
        $audience = 'test.' . __FUNCTION__;
        $timestamp = 1600000000;

        $plugin = SignedUrl::create(self::KeyHs256, 'HS256', $audience);
        $plugin->setTimestamp($timestamp);
        $token = $plugin->getToken(
            'https://host.tld/path?query=value',
            ['get'],
            1600000600,
            SignedUrl::ModeRequest,
            SignedUrl::ValueEnable
        );

        $plugin = SignedUrl::create(self::KeyHs256, 'HS256', $audience);
        $plugin->setTimestamp($timestamp);
        JWT::$timestamp = $timestamp;
        $parsed = $plugin->verifyToken($token);
        $expected = ['https://host.tld/path?query=value', ['get'], 0, 1, 1600000600];
        Assert::equal($expected, $parsed);
    }

    public function testVerifyUrl(): void
    {
        $audience = 'test.' . __FUNCTION__;
        $timestamp = 1600000000;
        $url = 'https://host.tld/path';

        $plugin = SignedUrl::create(self::KeyHs256, 'HS256', $audience);
        $plugin->setTimestamp($timestamp);
        $tokenUrl = $plugin->signUrl($url, 1600000600);

        $plugin = SignedUrl::create(self::KeyHs256, 'HS256', $audience);
        $plugin->setTimestamp($timestamp);
        JWT::$timestamp = $timestamp;
        $parsed = $plugin->verifyUrl($tokenUrl);
        $expected = [['get'], 0, 1, 1600000600];
        Assert::equal($expected, $parsed);
    }

    public function testVerifyUrlQuery(): void
    {
        $audience = 'test.' . __FUNCTION__;
        $timestamp = 1600000000;
        $url = 'https://host.tld/path?query=value';

        $plugin = SignedUrl::create(self::KeyHs256, 'HS256', $audience);
        $plugin->setTimestamp($timestamp);
        $tokenUrl = $plugin->signUrl($url, 1600000600);

        $plugin = SignedUrl::create(self::KeyHs256, 'HS256', $audience);
        $plugin->setTimestamp($timestamp);
        JWT::$timestamp = $timestamp;
        $parsed = $plugin->verifyUrl($tokenUrl);
        $expected = [['get'], 0, 1, 1600000600];
        Assert::equal($expected, $parsed);
    }

    public function testVerifyRequest(): void
    {
        $audience = 'test.' . __FUNCTION__;
        $timestamp = 1600000000;
        $url = 'https://host.tld/path?query=value';

        $plugin = SignedUrl::create(self::KeyHs256, 'HS256', $audience);
        $plugin->setTimestamp($timestamp);
        $tokenUrl = $plugin->signUrl($url, 1600000600);

        $plugin = SignedUrl::create(self::KeyHs256, 'HS256', $audience);
        $plugin->setTimestamp($timestamp);
        JWT::$timestamp = $timestamp;
        $parsed = $plugin->verifyRequest(false, $tokenUrl, 'GET');
        $expected = [0, 1, 1600000600];
        Assert::equal($expected, $parsed);
    }

    public function testSignInvalidUrl(): void
    {
        Assert::exception(static function () {
            $url = (string)base64_decode('Ly8Eijrg+qawZw==');
            $plugin = SignedUrl::create(self::KeyHs256, 'HS256');
            $plugin->signUrl($url, 1600000600);
        }, LogicException::class);
    }

    public function testSignRelativeUrl(): void
    {
        Assert::exception(static function () {
            $url = '/login?email=foo@bar.cz';
            $plugin = SignedUrl::create(self::KeyHs256, 'HS256');
            $plugin->signUrl($url, 1600000600);
        }, LogicException::class);
    }

    public function testVerifyPostRequest(): void
    {
        $audience = 'test.' . __FUNCTION__;
        $timestamp = 1600000000;
        $url = 'https://host.tld/path?query=value';

        $plugin = SignedUrl::create(self::KeyHs256, 'HS256', $audience);
        $plugin->setTimestamp($timestamp);
        $tokenUrl = $plugin->signUrl($url, 1600000600);

        $plugin = SignedUrl::create(self::KeyHs256, 'HS256', $audience);
        $plugin->setTimestamp($timestamp);
        JWT::$timestamp = $timestamp;
        Assert::exception(static function () use ($plugin, $tokenUrl) {
            $plugin->verifyRequest(false, $tokenUrl, 'POST');
        }, SignedUrlVerificationException::class, 'HTTP method doesn\'t match signed HTTP method');
    }

    public function testVerifyInvalidRequest(): void
    {
        Assert::exception(static function () {
            $plugin = SignedUrl::create(self::KeyHs256, 'HS256');
            $url = (string)base64_decode('Ly8Eijrg+qawZw==');
            $plugin->verifyRequest(false, $url, 'GET');
        }, SignedUrlVerificationException::class, 'Url is invalid');
    }

    public function testVerifyInvalidUrl(): void
    {
        Assert::exception(static function () {
            $plugin = SignedUrl::create(self::KeyHs256, 'HS256');
            $plugin->verifyUrl('https://host.tld/path?query=value');
        }, SignedUrlVerificationException::class, 'No token in URL');
    }

    public function testVerifyUrlWithSuffix(): void
    {
        $timestamp = 1600000000;
        $url = 'https://host.tld/path?query=value';

        $plugin = SignedUrl::create(self::KeyHs256, 'HS256');
        $plugin->setTimestamp($timestamp);
        $tokenUrl = $plugin->signUrl($url, 1600000600);

        $tokenUrl .= '&fbclid=123456789';

        Assert::exception(
            static function () use ($timestamp, $tokenUrl) {
                $plugin = SignedUrl::create(self::KeyHs256, 'HS256');
                $plugin->setTimestamp($timestamp);
                JWT::$timestamp = $timestamp;
                $plugin->verifyUrl($tokenUrl);
            },
            SignedUrlVerificationException::class,
            'URL contains unallowed queries after Signing Token'
        );
    }

    public function testVerifyUrlWithSuffixRedirect(): void
    {
        $timestamp = 1600000000;
        $tokenUrl = 'https://host.tld/path?query=value&_debug=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJjei5yZWRi'
            . 'aXQuZGVidWcudXJsIiwiYXVkIjoidGVzdC50ZXN0U2lnbiIsImlhdCI6MTYwMDAwMDAwMCwiZXhwIjoxNjAwMDAwNjAwLCJzdWIiOiJo'
            . 'dHRwczpcL1wvaG9zdC50bGRcL3BhdGg_cXVlcnk9dmFsdWUiLCJtZXRoIjoiZ2V0IiwibW9kIjowLCJ2YWwiOjF9.61Z0pPW3lJN2WDo'
            . 'UhOfsZ4m16Q3hjtVFJep_t_qoQ5c'
            . '&fbclid=123456789';

        // Mock plugin without redirect
        $plugin = new class(SignedUrl::create(self::KeyHs256)->getJwt(), 'test.testSign') extends SignedUrl {
            protected function sendRedirectResponse(string $canonicalUrl): void
            {
                $expected = 'https://host.tld/path?query=value&_debug=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJj'
                    . 'ei5yZWRiaXQuZGVidWcudXJsIiwiYXVkIjoidGVzdC50ZXN0U2lnbiIsImlhdCI6MTYwMDAwMDAwMCwiZXhwIjoxNjAwMDAw'
                    . 'NjAwLCJzdWIiOiJodHRwczpcL1wvaG9zdC50bGRcL3BhdGg_cXVlcnk9dmFsdWUiLCJtZXRoIjoiZ2V0IiwibW9kIjowLCJ2'
                    . 'YWwiOjF9.61Z0pPW3lJN2WDoUhOfsZ4m16Q3hjtVFJep_t_qoQ5c';
                Assert::equal($expected, $canonicalUrl);
            }
        };

        $plugin->setTimestamp($timestamp);
        JWT::$timestamp = $timestamp;
        $plugin->verifyUrl($tokenUrl, true);
    }

    public function testVerifyUrlWithSuffixRedirectFragment(): void
    {
        $timestamp = 1600000000;
        $tokenUrl = 'https://host.tld/path?query=value'
            . '&_debug=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJjei5yZWRiaXQuZGVidWcudXJsIiwiYXVkIjoidGVzdC50ZXN'
            . '0U2lnbiIsImlhdCI6MTYwMDAwMDAwMCwiZXhwIjoxNjAwMDAwNjAwLCJzdWIiOiJodHRwczpcL1wvaG9zdC50bGRcL3BhdGg_cXVlcnk'
            . '9dmFsdWUiLCJtZXRoIjoiZ2V0IiwibW9kIjowLCJ2YWwiOjF9.61Z0pPW3lJN2WDoUhOfsZ4m16Q3hjtVFJep_t_qoQ5c'
            . '&fbclid=123456789'
            . '#hash';

        // Mock plugin without redirect
        $plugin = new class(SignedUrl::create(self::KeyHs256)->getJwt(), 'test.testSign') extends SignedUrl {
            protected function sendRedirectResponse(string $canonicalUrl): void
            {
                $expected = 'https://host.tld/path?query=value'
                    . '&_debug=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJjei5yZWRiaXQuZGVidWcudXJsIiwiYXVkIjoidGV'
                    . 'zdC50ZXN0U2lnbiIsImlhdCI6MTYwMDAwMDAwMCwiZXhwIjoxNjAwMDAwNjAwLCJzdWIiOiJodHRwczpcL1wvaG9zdC50bGR'
                    . 'cL3BhdGg_cXVlcnk9dmFsdWUiLCJtZXRoIjoiZ2V0IiwibW9kIjowLCJ2YWwiOjF9.61Z0pPW3lJN2WDoUhOfsZ4m16Q3hjt'
                    . 'VFJep_t_qoQ5c'
                    . '#hash';
                Assert::equal($expected, $canonicalUrl);
            }
        };

        $plugin->setTimestamp($timestamp);
        JWT::$timestamp = $timestamp;
        $plugin->verifyUrl($tokenUrl, true);
    }
}

(new SignUrlTest())->run();
