<?php

declare(strict_types=1);

namespace Redbitcz\DebugMode\Plugin;

use DateTimeInterface;
use Firebase\JWT\JWT;
use LogicException;
use Nette\Utils\DateTime;
use Redbitcz\DebugMode\Detector;
use RuntimeException;

/**
 * @phpstan-type ParsedUrl array{'scheme'?: string, 'host'?: string, 'port'?: int, 'user'?: string, 'pass'?: string, 'path'?: string, 'query'?: string, 'fragment'?: string}
 * @phpstan-type ClaimsSet array{'iss': string, 'aud': string|null, 'iat': int, 'exp': int, 'sub': string, 'meth': string, 'mod': int, 'val': int}
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
    private const HTTP_METHOD_GET = 'get';

    /** @var resource|string */
    private $key;
    private string $algorithm;
    private ?string $audience;
    private ?int $timestamp;

    /**
     * @param string|resource $key The key.
     * @param string $algorithm Supported algorithms are 'ES384','ES256', 'HS256', 'HS384', 'HS512', 'RS256', 'RS384', and 'RS512'
     * @param string|null $audience Recipient for which the JWT is intended
     */
    public function __construct($key, string $algorithm = 'HS256', ?string $audience = null)
    {
        if (class_exists(JWT::class) === false) {
            throw new LogicException(__CLASS__ . ' requires JWT library: firebase/php-jwt');
        }

        $this->key = $key;
        $this->algorithm = $algorithm;
        $this->audience = $audience;
    }

    /**
     * @param string|int|DateTimeInterface $expire
     */
    public function signUrl(
        string $url,
        $expire,
        int $mode = self::MODE_REQUEST,
        int $value = self::VALUE_ENABLE
    ): string {
        $parsedUrl = parse_url($url);

        if ($parsedUrl === false) {
            throw new LogicException('Url is invalid');
        }

        if (empty($parsedUrl['scheme'] ?? '') || empty($parsedUrl['host'] ?? '')) {
            throw new LogicException('Only absolute URL is allowed to sign');
        }

        $token = $this->getToken($url, $expire, $mode, $value);

        $parsedUrl['query'] = ($parsedUrl['query'] ?? '') . ((($parsedUrl['query'] ?? '') === '') ? '?' : '&')
            . self::URL_QUERY_TOKEN_KEY . '=' . urlencode($token);

        return $this->buildUrl($parsedUrl);
    }

    /**
     * @param string|int|DateTimeInterface $expire
     */
    public function getToken(
        string $url,
        $expire,
        int $mode = self::MODE_REQUEST,
        int $value = self::VALUE_ENABLE
    ): string {
        $expire = (int)DateTime::from($expire)->format('U');

        $payload = [
            'iss' => self::ISSUER_ID,
            'aud' => $this->audience,
            'iat' => $this->timestamp ?? time(),
            'exp' => $expire,
            'sub' => $url,
            'meth' => self::HTTP_METHOD_GET,
            'mod' => $mode,
            'val' => $value,
        ];

        return JWT::encode($payload, $this->key, $this->algorithm);
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

        [$allowedMethod, $mode, $value, $expires] = $this->verifyUrl($url);

        if (strcasecmp($method, $allowedMethod) !== 0) {
            throw new SignedUrlVerificationException('HTTP method doesn\'t match signed HTTP method');
        }

        return [$mode, $value, $expires];
    }

    /**
     * @return array{string, int, int, int}
     */
    public function verifyUrl(string $url, bool $allowRedirect = false): array
    {
        /** @noinspection CallableParameterUseCaseInTypeContextInspection */
        $url = parse_url($url);

        if ($url === false) {
            throw new SignedUrlVerificationException('Url is invalid');
        }

        // Parse token from Query string
        // Note: Not parsed by parse_str() to prevent broke URL (repeated arguments like `?same_arg=1&same_arg=2`)
        $query = $url['query'] ?? '';
        if (preg_match(
                '/(?<token_key>[?&]' . self::URL_QUERY_TOKEN_KEY . '=)(?<token>[^&]+)(?:$|(?<remaining>&.*$))/D',
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
        [$allowedUrl, $allowedMethod, $mode, $value, $expires] = $this->verifyToken($token);

        // Some apps modifing URL
        if ($remainingOffset !== null) {
            if ($allowRedirect === false) {
                throw new SignedUrlVerificationException('URL contains unallowed queries after Signing Token');
            }

            $canonicalUrl = $this->buildUrl(['query' => substr($query, 0, $remainingOffset)] + $url);
            header('Cache-Control: s-maxage=0, max-age=0, must-revalidate', true, 302);
            header('Expires: Mon, 23 Jan 1978 10:00:00 GMT', true);
            header('Location: ' . $canonicalUrl);
            $escapedUrl = htmlspecialchars($canonicalUrl, ENT_IGNORE | ENT_QUOTES, 'UTF-8');
            echo "<h1>Redirect</h1>\n\n<p><a href=\"{$escapedUrl}\">Please click here to continue</a>.</p>";
        }

        $signedUrl = $this->buildUrl(['query' => substr($query, 0, $tokenOffset)] + $url);

        if ($signedUrl !== $allowedUrl) {
            throw new SignedUrlVerificationException('URL doesn\'t match signed URL');
        }

        return [$allowedMethod, $mode, $value, $expires];
    }

    /**
     * @return array{string, string, int, int, int}
     */
    public function verifyToken(string $token): array
    {
        try {
            /** @var ClaimsSet */
            $payload = JWT::decode($token, $this->key, [$this->algorithm]);
        } catch (RuntimeException $e) {
            throw new SignedUrlVerificationException('JWT Token invalid', 0, $e);
        }

        // Check mandatory claims presence
        if (isset(
                $payload->iss,
                $payload->aud,
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
            || $payload->aud !== $this->audience
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
            (string)$payload->sub,
            (string)$payload->meth,
            $payload->mod,
            $payload->val,
            (int)$payload->exp
        ];
    }

    /**
     * @param ParsedUrl $parsedUrl
     */
    private function buildUrl(array $parsedUrl): string
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

    private function urlFromGlobal(): string
    {
        $urlSegments = [];
        $urlSegments['scheme'] = !empty($_SERVER['HTTPS']) && strcasecmp($_SERVER['HTTPS'], 'off') ? 'https' : 'http';
        $urlSegments['host'] = strtolower($_SERVER['HTTP_HOST'] ?? '');

        $requestUrl = $_SERVER['REQUEST_URI'] ?? '/';
        $requestUrl = preg_replace('#^\w++://[^/]++#', '', $requestUrl);
        $tmp = explode('?', $requestUrl, 2);
        $urlSegments['path'] = $tmp[0];
        $urlSegments['query'] = ($tmp[1] ?? '');

        $urlSegments['user'] = ($_SERVER['PHP_AUTH_USER'] ?? '');
        $urlSegments['pass'] = ($_SERVER['PHP_AUTH_PW'] ?? '');

        return $this->buildUrl($urlSegments);
    }

    /**
     * Set internal timestamp for compute sign. Especially for tests
     */
    public function setTimestamp(?int $timestamp): void
    {
        $this->timestamp = $timestamp;
    }
}
