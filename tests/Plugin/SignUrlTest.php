<?php

/**
 * The MIT License (MIT)
 * Copyright (c) 2022 Redbit s.r.o., Jakub Bouček
 *
 * @noinspection PhpUnused
 * @testCase
 */

declare(strict_types=1);

namespace Redbitcz\DebugModeTests\Plugin;

use Firebase\JWT\JWT;
use LogicException;
use Redbitcz\DebugMode\Plugin\JWT\JWTFirebaseV6;
use Redbitcz\DebugMode\Plugin\SignedUrl;
use Redbitcz\DebugMode\Plugin\SignedUrlVerificationException;
use Tester\Assert;
use Tester\TestCase;

require __DIR__ . '/../bootstrap.php';

class SignUrlTest extends TestCase
{
    private const KEY_HS256 = "zhYiojmp7O3VYQNuW0C5rS0VgFNgoAvuxW4IdS/0tn8";


    public function testSign(): void
    {
        $audience = 'test.' . __FUNCTION__;

        $plugin = new SignedUrl(self::KEY_HS256, 'HS256', $audience);
        $plugin->setTimestamp(1600000000);
        $token = $plugin->signUrl('https://host.tld/path', 1600000600);

        if ($plugin->getJwt() instanceof JWTFirebaseV6) {
            $expected = 'https://host.tld/path?_debug=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJjei5yZWRiaXQuZGVi'
                . 'dWcudXJsIiwiYXVkIjoidGVzdC50ZXN0U2lnbiIsImlhdCI6MTYwMDAwMDAwMCwiZXhwIjoxNjAwMDAwNjAwLCJzdWIiOiJodHRw'
                . 'czovL2hvc3QudGxkL3BhdGgiLCJtZXRoIjpbImdldCJdLCJtb2QiOjAsInZhbCI6MX0.h2TAkamMzGVQkre-F9kaCSmg3irRt9qv'
                . '84oUcxj9gv0';
        } else {
            $expected = 'https://host.tld/path?_debug=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJjei5yZWRiaXQuZGVi'
                . 'dWcudXJsIiwiYXVkIjoidGVzdC50ZXN0U2lnbiIsImlhdCI6MTYwMDAwMDAwMCwiZXhwIjoxNjAwMDAwNjAwLCJzdWIiOiJodHRw'
                . 'czpcL1wvaG9zdC50bGRcL3BhdGgiLCJtZXRoIjpbImdldCJdLCJtb2QiOjAsInZhbCI6MX0.MTZOii4lQ2WCk1UltRx_e9T5vCT7'
                . 'nq8G3kh4D8EXy7s';
        }

        Assert::equal($expected, $token);
    }

    public function testSignQuery(): void
    {
        $audience = 'test.' . __FUNCTION__;

        $plugin = new SignedUrl(self::KEY_HS256, 'HS256', $audience);
        $plugin->setTimestamp(1600000000);
        $token = $plugin->signUrl('https://host.tld/path?query=value', 1600000600);

        if ($plugin->getJwt() instanceof JWTFirebaseV6) {
            $expected = 'https://host.tld/path?query=value&_debug=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJjei5y'
                . 'ZWRiaXQuZGVidWcudXJsIiwiYXVkIjoidGVzdC50ZXN0U2lnblF1ZXJ5IiwiaWF0IjoxNjAwMDAwMDAwLCJleHAiOjE2MDAwMDA2'
                . 'MDAsInN1YiI6Imh0dHBzOi8vaG9zdC50bGQvcGF0aD9xdWVyeT12YWx1ZSIsIm1ldGgiOlsiZ2V0Il0sIm1vZCI6MCwidmFsIjox'
                . 'fQ.UXB2AIKChgunDzoY7hcWNA7vg7j6sf3VvOWFw0OKz8k';
        } else {
            $expected = 'https://host.tld/path?query=value&_debug=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJjei5y'
                . 'ZWRiaXQuZGVidWcudXJsIiwiYXVkIjoidGVzdC50ZXN0U2lnblF1ZXJ5IiwiaWF0IjoxNjAwMDAwMDAwLCJleHAiOjE2MDAwMDA2'
                . 'MDAsInN1YiI6Imh0dHBzOlwvXC9ob3N0LnRsZFwvcGF0aD9xdWVyeT12YWx1ZSIsIm1ldGgiOlsiZ2V0Il0sIm1vZCI6MCwidmFs'
                . 'IjoxfQ.RrO7BCmdgldB7OlEIpudBWo8P33xDh-MsNjtZC34CNY';
        }

        Assert::equal($expected, $token);
    }

    public function testSignFragment(): void
    {
        $audience = 'test.' . __FUNCTION__;

        $plugin = new SignedUrl(self::KEY_HS256, 'HS256', $audience);
        $plugin->setTimestamp(1600000000);
        $token = $plugin->signUrl('https://host.tld/path?query=value#fragment', 1600000600);

        if ($plugin->getJwt() instanceof JWTFirebaseV6) {
            $expected = 'https://host.tld/path?query=value&_debug=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJjei5y'
                . 'ZWRiaXQuZGVidWcudXJsIiwiYXVkIjoidGVzdC50ZXN0U2lnbkZyYWdtZW50IiwiaWF0IjoxNjAwMDAwMDAwLCJleHAiOjE2MDAw'
                . 'MDA2MDAsInN1YiI6Imh0dHBzOi8vaG9zdC50bGQvcGF0aD9xdWVyeT12YWx1ZSIsIm1ldGgiOlsiZ2V0Il0sIm1vZCI6MCwidmFs'
                . 'IjoxfQ.-Aww363VPD0aSi5QK1JH2v_4yFU5DX5aRvbsxqtcJSg#fragment';
        } else {
            $expected = 'https://host.tld/path?query=value&_debug=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJjei5y'
                . 'ZWRiaXQuZGVidWcudXJsIiwiYXVkIjoidGVzdC50ZXN0U2lnbkZyYWdtZW50IiwiaWF0IjoxNjAwMDAwMDAwLCJleHAiOjE2MDAw'
                . 'MDA2MDAsInN1YiI6Imh0dHBzOlwvXC9ob3N0LnRsZFwvcGF0aD9xdWVyeT12YWx1ZSIsIm1ldGgiOlsiZ2V0Il0sIm1vZCI6MCwi'
                . 'dmFsIjoxfQ.9oIORBXW-hW8vTPdJglEdEMm19nwAvw2wLAxqWvFh3Y#fragment';
        }

        Assert::equal($expected, $token);
    }

    public function testGetToken(): void
    {
        $audience = 'test.' . __FUNCTION__;

        $plugin = new SignedUrl(self::KEY_HS256, 'HS256', $audience);
        $plugin->setTimestamp(1600000000);
        $token = $plugin->getToken(
            'https://host.tld/path?query=value',
            ['get'],
            1600000600,
            SignedUrl::MODE_REQUEST,
            SignedUrl::VALUE_ENABLE
        );

        if ($plugin->getJwt() instanceof JWTFirebaseV6) {
            $expected = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJjei5yZWRiaXQuZGVidWcudXJsIiwiYXVkIjoidGVzdC50Z'
                . 'XN0R2V0VG9rZW4iLCJpYXQiOjE2MDAwMDAwMDAsImV4cCI6MTYwMDAwMDYwMCwic3ViIjoiaHR0cHM6Ly9ob3N0LnRsZC9wYXRoP'
                . '3F1ZXJ5PXZhbHVlIiwibWV0aCI6WyJnZXQiXSwibW9kIjowLCJ2YWwiOjF9.LrE8DVuvXiP4u3cHXiSABIOXI4WlHFBxf2g-DRYW'
                . 'xNQ';
        } else {
            $expected = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJjei5yZWRiaXQuZGVidWcudXJsIiwiYXVkIjoidGVzdC50Z'
                . 'XN0R2V0VG9rZW4iLCJpYXQiOjE2MDAwMDAwMDAsImV4cCI6MTYwMDAwMDYwMCwic3ViIjoiaHR0cHM6XC9cL2hvc3QudGxkXC9wY'
                . 'XRoP3F1ZXJ5PXZhbHVlIiwibWV0aCI6WyJnZXQiXSwibW9kIjowLCJ2YWwiOjF9.I6tEfFneSxuY9qAjRf5esYFPonChbliZqGoi'
                . 'jtv2iHw';
        }

        Assert::equal($expected, $token);
    }

    public function testVerifyToken(): void
    {
        $audience = 'test.' . __FUNCTION__;
        $timestamp = 1600000000;

        $plugin = new SignedUrl(self::KEY_HS256, 'HS256', $audience);
        $plugin->setTimestamp($timestamp);
        $token = $plugin->getToken(
            'https://host.tld/path?query=value',
            ['get'],
            1600000600,
            SignedUrl::MODE_REQUEST,
            SignedUrl::VALUE_ENABLE
        );

        $plugin = new SignedUrl(self::KEY_HS256, 'HS256', $audience);
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

        $plugin = new SignedUrl(self::KEY_HS256, 'HS256', $audience);
        $plugin->setTimestamp($timestamp);
        $tokenUrl = $plugin->signUrl($url, 1600000600);

        $plugin = new SignedUrl(self::KEY_HS256, 'HS256', $audience);
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

        $plugin = new SignedUrl(self::KEY_HS256, 'HS256', $audience);
        $plugin->setTimestamp($timestamp);
        $tokenUrl = $plugin->signUrl($url, 1600000600);

        $plugin = new SignedUrl(self::KEY_HS256, 'HS256', $audience);
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

    public function testSignInvalidUrl(): void
    {
        Assert::exception(static function () {
            $url = (string)base64_decode('Ly8Eijrg+qawZw==');
            $plugin = new SignedUrl(self::KEY_HS256, 'HS256');
            $plugin->signUrl($url, 1600000600);
        }, LogicException::class);
    }

    public function testSignRelativeUrl(): void
    {
        Assert::exception(static function () {
            $url = '/login?email=foo@bar.cz';
            $plugin = new SignedUrl(self::KEY_HS256, 'HS256');
            $plugin->signUrl($url, 1600000600);
        }, LogicException::class);
    }

    public function testVerifyPostRequest(): void
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
        Assert::exception(static function () use ($plugin, $tokenUrl) {
            $plugin->verifyRequest(false, $tokenUrl, 'POST');
        }, SignedUrlVerificationException::class, 'HTTP method doesn\'t match signed HTTP method');
    }

    public function testVerifyInvalidRequest(): void
    {
        Assert::exception(static function () {
            $plugin = new SignedUrl(self::KEY_HS256, 'HS256');
            $url = (string)base64_decode('Ly8Eijrg+qawZw==');
            $plugin->verifyRequest(false, $url, 'GET');
        }, SignedUrlVerificationException::class, 'Url is invalid');
    }

    public function testVerifyInvalidUrl(): void
    {
        Assert::exception(static function () {
            $plugin = new SignedUrl(self::KEY_HS256, 'HS256');
            $plugin->verifyUrl('https://host.tld/path?query=value');
        }, SignedUrlVerificationException::class, 'No token in URL');
    }

    public function testVerifyUrlWithSuffix(): void
    {
        $timestamp = 1600000000;
        $url = 'https://host.tld/path?query=value';

        $plugin = new SignedUrl(self::KEY_HS256, 'HS256');
        $plugin->setTimestamp($timestamp);
        $tokenUrl = $plugin->signUrl($url, 1600000600);

        $tokenUrl .= '&fbclid=123456789';

        Assert::exception(
            static function () use ($timestamp, $tokenUrl) {
                $plugin = new SignedUrl(self::KEY_HS256, 'HS256');
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
        $plugin = new class(self::KEY_HS256, 'HS256', 'test.testSign') extends SignedUrl {
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
        $plugin = new class(self::KEY_HS256, 'HS256', 'test.testSign') extends SignedUrl {
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
