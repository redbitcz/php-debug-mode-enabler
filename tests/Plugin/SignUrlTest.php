<?php

declare(strict_types=1);

namespace Redbitcz\DebugModeTests\Plugin;

use Firebase\JWT\JWT;
use Redbitcz\DebugMode\Plugin\SignedUrl;
use Tester\Assert;

require __DIR__ . '/../bootstrap.php';

/** @testCase */
class SignUrlTest extends \Tester\TestCase
{
    private const KEY_HS256 = "zhYiojmp7O3VYQNuW0C5rS0VgFNgoAvuxW4IdS/0tn8";

    public function testSign(): void
    {
        $audience = 'test.' . __FUNCTION__;

        $plugin = new SignedUrl(self::KEY_HS256, 'HS256', $audience);
        $plugin->setTimestamp(1600000000);
        $token = $plugin->signUrl('https://host.tld/path?query=value', 1600000600);
        $expected = 'https://host.tld/path?query=value&_debug=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJjei5yZWRiaXQuZGVidWcudXJsIiwiYXVkIjoidGVzdC50ZXN0U2lnbiIsImlhdCI6MTYwMDAwMDAwMCwiZXhwIjoxNjAwMDAwNjAwLCJzdWIiOiJodHRwczpcL1wvaG9zdC50bGRcL3BhdGg_cXVlcnk9dmFsdWUiLCJtZXRoIjoiZ2V0IiwibW9kIjowLCJ2YWwiOjF9.61Z0pPW3lJN2WDoUhOfsZ4m16Q3hjtVFJep_t_qoQ5c';
        Assert::equal($expected, $token);
    }

    public function testGetToken(): void
    {
        $audience = 'test.' . __FUNCTION__;

        $plugin = new SignedUrl(self::KEY_HS256, 'HS256', $audience);
        $plugin->setTimestamp(1600000000);
        $token = $plugin->getToken('https://host.tld/path?query=value', 1600000600);
        $expected = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJjei5yZWRiaXQuZGVidWcudXJsIiwiYXVkIjoidGVzdC50ZXN0R2V0VG9rZW4iLCJpYXQiOjE2MDAwMDAwMDAsImV4cCI6MTYwMDAwMDYwMCwic3ViIjoiaHR0cHM6XC9cL2hvc3QudGxkXC9wYXRoP3F1ZXJ5PXZhbHVlIiwibWV0aCI6ImdldCIsIm1vZCI6MCwidmFsIjoxfQ.KO0DRN8hsn_MYZI3iRMpw5uRJ9hKh1taex-6k02BwMk';
        Assert::equal($expected, $token);
    }

    public function testVerifyToken(): void
    {
        $audience = 'test.' . __FUNCTION__;
        $timestamp = 1600000000;

        $plugin = new SignedUrl(self::KEY_HS256, 'HS256', $audience);
        $plugin->setTimestamp($timestamp);
        $token = $plugin->getToken('https://host.tld/path?query=value', 1600000600);

        $plugin = new SignedUrl(self::KEY_HS256, 'HS256', $audience);
        $plugin->setTimestamp($timestamp);
        JWT::$timestamp = $timestamp;
        $parsed = $plugin->verifyToken($token);
        $expected = ['https://host.tld/path?query=value', 'get', 0, 1, 1600000600];
        Assert::equal($expected, $parsed);
    }

    public function testVerifyUrl(): void
    {
        $audience = 'test.' . __FUNCTION__;
        $timestamp = 1600000000;
        $url = 'https://host.tld/path?query=value';

        $plugin = new SignedUrl(self::KEY_HS256, 'HS256', $audience);
        $plugin->setTimestamp($timestamp);
        $tokenUrl = $plugin->signUrl($url, 1600000600);

        $plugin = new SignedUrl(self::KEY_HS256, 'HS256', $audience);
        $plugin->setTimestamp($timestamp);
        JWT::$timestamp = $timestamp;
        $parsed = $plugin->verifyUrl($tokenUrl);
        $expected = ['get', 0, 1, 1600000600];
        Assert::equal($expected, $parsed);
    }

    public function testVerifyRequest(): void
    {
        $audience = 'test.' . __FUNCTION__;
        $timestamp = 1600000000;
        $url = 'https://host.tld/path?query=value';

        $plugin = new SignedUrl(self::KEY_HS256, 'HS256', $audience);
        $plugin->setTimestamp($timestamp);
        $tokenUrl = $plugin->signUrl($url, 1600000600);

        $plugin = new SignedUrl(self::KEY_HS256, 'HS256', $audience);
        $plugin->setTimestamp($timestamp);
        JWT::$timestamp = $timestamp;
        $parsed = $plugin->verifyRequest(false, $tokenUrl, 'GET');
        $expected = [0, 1, 1600000600];
        Assert::equal($expected, $parsed);
    }
}

(new SignUrlTest())->run();
