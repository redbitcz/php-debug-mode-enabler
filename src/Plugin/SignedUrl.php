<?php

/**
 * The MIT License (MIT)
 * Copyright (c) 2022 Redbit s.r.o., Jakub Bouček
 *
 * @noinspection PhpComposerExtensionStubsInspection OpenSSL only optional dependency
 * @noinspection PhpElementIsNotAvailableInCurrentPhpVersionInspection PHP 7 compatibility
 */

declare(strict_types=1);

namespace Redbitcz\DebugMode\Plugin;

use DateTimeInterface;
use LogicException;
use Nette\Utils\DateTime;
use OpenSSLAsymmetricKey;
use OpenSSLCertificate;
use Redbitcz\DebugMode\Detector;
use Redbitcz\DebugMode\Plugin\JWT\JWTFirebaseV5;
use Redbitcz\DebugMode\Plugin\JWT\JWTFirebaseV6;
use Redbitcz\DebugMode\Plugin\JWT\JWTImpl;
use RuntimeException;

/**
 * @phpstan-type ParsedUrl array{'scheme'?: string, 'host'?: string, 'port'?: int, 'user'?: string, 'pass'?: string, 'path'?: string, 'query'?: string, 'fragment'?: string}
 * @phpstan-type ClaimsSet array{'iss': string, 'aud': string|null, 'iat': int, 'exp': int, 'sub': string, 'meth': array<int, string>, 'mod': int, 'val': int}
 */
class SignedUrl implements Plugin
{

    /** Set debug mode only for signed request */
    public const MODE_REQUEST = 0;
    /** Set debug mode permanent – requires Detector with prepared Enabler */
    public const MODE_ENABLER = 1;
    /** Deactivate Enabler – requires Detector with prepared Enabler */
    public const MODE_DEACTIVATE_ENABLER = 2;

    /** Force turn-off debug mode  */
    public const VALUE_DISABLE = 0;
    /** Force turn-on debug mode  */
    public const VALUE_ENABLE = 1;

    private const URL_QUERY_TOKEN_KEY = '_debug';
    private const ISSUER_ID = 'cz.redbit.debug.url';

    /** @var resource|string|OpenSSLAsymmetricKey|OpenSSLCertificate */
    private $key;
    private string $algorithm;
    private ?string $audience;
    private ?int $timestamp;

    private JWTImpl $jwt;

    /**
     * @param string|resource|OpenSSLAsymmetricKey|OpenSSLCertificate $key The key.
     * @param string $algorithm Supported algorithms are 'ES384','ES256', 'HS256', 'HS384', 'HS512', 'RS256', 'RS384', and 'RS512'
     * @param string|null $audience Recipient for which the JWT is intended
     * @noinspection PhpRedundantVariableDocTypeInspection
     */
    public function __construct($key, string $algorithm = 'HS256', ?string $audience = null)
    {
        /** @var class-string<JWTImpl> $impl */
        foreach ([JWTFirebaseV5::class, JWTFirebaseV6::class] as $impl) {
            if ($impl::isAvailable()) {
                $this->jwt = new $impl;
                break;
            }
        }

        if (isset($this->jwt) === false) {
            throw new LogicException(__CLASS__ . ' requires JWT library: firebase/php-jwt version ~5.0 or ~6.0');
        }

        $this->key = $key;
        $this->algorithm = $algorithm;
        $this->audience = $audience;
    }

    /**
     * @param string|int|DateTimeInterface $expire
     * @param array<int, string> $allowedHttpMethods
     */
    public function signUrl(
        string $url,
        $expire,
        int $mode = self::MODE_REQUEST,
        int $value = self::VALUE_ENABLE,
        array $allowedHttpMethods = ['get']
    ): string {
        /** @var ParsedUrl|false $parsedUrl */
        $parsedUrl = parse_url($url);

        if ($parsedUrl === false) {
            throw new LogicException('Url is invalid');
        }

        if (empty($parsedUrl['scheme'] ?? '') || empty($parsedUrl['host'] ?? '')) {
            throw new LogicException('Only absolute URL is allowed to sign');
        }

        $signUrl = $this->normalizeUrl($parsedUrl);

        $token = $this->getToken($this->buildUrl($signUrl), $allowedHttpMethods, $expire, $mode, $value);

        $parsedUrl['query'] = ltrim(($parsedUrl['query'] ?? '') . '&', '&')
            . self::URL_QUERY_TOKEN_KEY . '=' . urlencode($token);

        return $this->buildUrl($parsedUrl);
    }

    /**
     * @param array<int, string> $allowedMethods
     * @param string|int|DateTimeInterface $expire
     */
    public function getToken(
        string $url,
        array $allowedMethods,
        $expire,
        int $mode,
        int $value
    ): string {
        $expire = (int)DateTime::from($expire)->format('U');

        $payload = [
            'iss' => self::ISSUER_ID,
            'aud' => $this->audience,
            'iat' => $this->timestamp ?? time(),
            'exp' => $expire,
            'sub' => $url,
            'meth' => $allowedMethods,
            'mod' => $mode,
            'val' => $value,
        ];

        return $this->jwt->encode($payload, $this->key, $this->algorithm);
    }

    public function __invoke(Detector $detector): ?bool
    {
        try {
            [$mode, $value, $expires] = $this->verifyRequest();
        } catch (SignedUrlVerificationException $e) {
            return null;
        }

        if ($mode === self::MODE_DEACTIVATE_ENABLER) {
            if ($detector->hasEnabler()) {
                $detector->getEnabler()->deactivate();
            }
            return null;
        }

        if ($mode === self::MODE_ENABLER && $detector->hasEnabler()) {
            $detector->getEnabler()->activate((bool)$value, $expires);
        }

        return (bool)$value;
    }

    /**
     * @return array{int, int, int}
     */
    public function verifyRequest(bool $allowRedirect = false, ?string $url = null, ?string $method = null): array
    {
        // Fastest to check to prevent burn CPU to obvious missing token
        if ($url === null && isset($_GET[self::URL_QUERY_TOKEN_KEY]) === false) {
            throw new SignedUrlVerificationException('No token in URL');
        }

        $url = $url ?? $this->urlFromGlobal();
        $method = $method ?? $_SERVER['REQUEST_METHOD'];

        [$allowedMethods, $mode, $value, $expires] = $this->verifyUrl($url, $allowRedirect);

        if (in_array(strtolower($method), $allowedMethods, true) === false) {
            throw new SignedUrlVerificationException('HTTP method doesn\'t match signed HTTP method');
        }

        return [$mode, $value, $expires];
    }

    /**
     * @return array{array<int, string>, int, int, int}
     */
    public function verifyUrl(string $url, bool $allowRedirect = false): array
    {
        /** @var ParsedUrl|false $parsedUrl */
        $parsedUrl = parse_url($url);

        if ($parsedUrl === false) {
            throw new SignedUrlVerificationException('Url is invalid');
        }

        // Parse token from Query string
        // Note: Not parsed by parse_str() to prevent broke URL (repeated arguments like `?same_arg=1&same_arg=2`)
        $query = $parsedUrl['query'] ?? '';
        if (preg_match(
                '/(?<token_key>(?:^|&|^&)' . self::URL_QUERY_TOKEN_KEY . '=)(?<token>[^&]+)(?:$|(?<remaining>&.*$))/D',
                $query,
                $matches,
                PREG_OFFSET_CAPTURE
            ) !== 1) {
            throw new SignedUrlVerificationException('No token in URL');
        }

        $token = urldecode($matches['token'][0]);
        $tokenOffset = $matches['token_key'][1];
        $remainingOffset = $matches['remaining'][1] ?? null;

        // Parse token – when token invalid, no URL canonicalization proceed
        [$allowedUrl, $allowedMethods, $mode, $value, $expires] = $this->verifyToken($token);

        // Some apps modifing URL
        if ($remainingOffset !== null) {
            if ($allowRedirect === false) {
                throw new SignedUrlVerificationException('URL contains unallowed queries after Signing Token');
            }

            $canonicalUrl = $this->buildUrl(['query' => substr($query, 0, $remainingOffset)] + $parsedUrl);
            $this->sendRedirectResponse($canonicalUrl);
        }

        $parsedUrl = $this->normalizeUrl($parsedUrl);
        if ($tokenOffset > 0) {
            $parsedUrl['query'] = substr($query, 0, $tokenOffset);
        } else {
            unset($parsedUrl['query']);
            /** @var ParsedUrl $parsedUrl (bypass PhpStan bug) */
        }

        $signedUrl = $this->buildUrl($parsedUrl);

        if ($signedUrl !== $allowedUrl) {
            throw new SignedUrlVerificationException('URL doesn\'t match signed URL');
        }

        return [$allowedMethods, $mode, $value, $expires];
    }

    /**
     * @return array{string, array<int, string>, int, int, int}
     */
    public function verifyToken(string $token): array
    {
        try {
            /** @var ClaimsSet $payload */
            $payload = $this->jwt->decode($token, $this->key, $this->algorithm);
        } catch (RuntimeException $e) {
            throw new SignedUrlVerificationException('JWT Token invalid', 0, $e);
        }

        // Check mandatory claims presence
        if (isset(
                $payload->iss,
                $payload->iat,
                $payload->exp,
                $payload->sub,
                $payload->meth,
                $payload->mod,
                $payload->val
            ) === false) {
            throw new SignedUrlVerificationException('JWT Token has no all required Claims');
        }

        // Check mandatory claims
        if (
            $payload->iss !== self::ISSUER_ID
            || ($payload->aud ?? null) !== $this->audience
        ) {
            throw new SignedUrlVerificationException('JWT Token mandatory Claims doesn\'t match');
        }

        // Check valid mode & value claims
        if (in_array(
                $payload->mod,
                [self::MODE_REQUEST, self::MODE_ENABLER, self::MODE_DEACTIVATE_ENABLER],
                true
            ) === false
            || in_array($payload->val, [self::VALUE_DISABLE, self::VALUE_ENABLE], true) === false
        ) {
            throw new SignedUrlVerificationException('JWT Token values is out of range');
        }

        return [
            $payload->sub,
            $payload->meth,
            $payload->mod,
            $payload->val,
            $payload->exp
        ];
    }

    /**
     * @param ParsedUrl $parsedUrl
     */
    protected function buildUrl(array $parsedUrl): string
    {
        return (isset($parsedUrl['scheme']) ? "{$parsedUrl['scheme']}:" : '')
            . ((isset($parsedUrl['user']) || isset($parsedUrl['host'])) ? '//' : '')
            . ($parsedUrl['user'] ?? '')
            . (isset($parsedUrl['pass']) ? ":{$parsedUrl['pass']}" : '')
            . (isset($parsedUrl['user']) ? '@' : '')
            . ($parsedUrl['host'] ?? '')
            . (isset($parsedUrl['port']) ? ":{$parsedUrl['port']}" : '')
            . ($parsedUrl['path'] ?? '')
            . (isset($parsedUrl['query']) ? "?{$parsedUrl['query']}" : '')
            . (isset($parsedUrl['fragment']) ? "#{$parsedUrl['fragment']}" : '');
    }

    protected function urlFromGlobal(): string
    {
        $urlSegments = [];
        $urlSegments['scheme'] = !empty($_SERVER['HTTPS']) && strcasecmp($_SERVER['HTTPS'], 'off') ? 'https' : 'http';
        if (isset($_SERVER['HTTP_HOST'])) {
            $urlSegments['host'] = strtolower($_SERVER['HTTP_HOST']);
        }

        $requestUrl = $_SERVER['REQUEST_URI'] ?? '/';
        $requestUrl = preg_replace('#^\w++://[^/]++#', '', $requestUrl);
        $tmp = explode('?', $requestUrl, 2);
        $urlSegments['path'] = $tmp[0];
        if (isset($tmp[1])) {
            $urlSegments['query'] = $tmp[1];
        }

        if (isset($_SERVER['PHP_AUTH_USER'])) {
            $urlSegments['user'] = $_SERVER['PHP_AUTH_USER'];
        }
        if (isset($_SERVER['PHP_AUTH_PW'])) {
            $urlSegments['pass'] = $_SERVER['PHP_AUTH_PW'];
        }

        return $this->buildUrl($urlSegments);
    }

    /**
     * Set internal timestamp for compute sign. Especially for tests
     */
    public function setTimestamp(?int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }

    public function getJwt(): JWTImpl
    {
        return $this->jwt;
    }

    protected function sendRedirectResponse(string $canonicalUrl): void
    {
        header('Cache-Control: s-maxage=0, max-age=0, must-revalidate', true, 302);
        header('Expires: Mon, 23 Jan 1978 10:00:00 GMT', true);
        header('Location: ' . $canonicalUrl);
        $escapedUrl = htmlspecialchars($canonicalUrl, ENT_IGNORE | ENT_QUOTES, 'UTF-8');
        echo "<h1>Redirect</h1>\n\n<p><a href=\"{$escapedUrl}\">Please click here to continue</a>.</p>";
        die();
    }

    /**
     * @param ParsedUrl $url
     * @return ParsedUrl
     */
    protected function normalizeUrl(array $url): array
    {
        $url['path'] = ($url['path'] ?? '') === '' ? '/' : ($url['path'] ?? '');
        unset($url['fragment']);
        /** @var ParsedUrl $url (bypass PhpStan bug) */
        return $url;
    }
}
