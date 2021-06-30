<?php

declare(strict_types=1);

namespace EasyWeChat\OpenWork;

use EasyWeChat\Kernel\Exceptions\HttpException;
use EasyWeChat\Kernel\Traits\InteractWithAccessTokenClient;
use EasyWeChat\Kernel\Traits\InteractWithCache;
use EasyWeChat\Kernel\Traits\InteractWithConfig;
use EasyWeChat\Kernel\Traits\InteractWithServerRequest;
use EasyWeChat\Kernel\Encryptor;
use EasyWeChat\Kernel\Contracts\AccessToken as AccessTokenInterface;
use EasyWeChat\OpenPlatform\Authorization;
use EasyWeChat\OpenWork\Contracts\Account as AccountInterface;
use EasyWeChat\OpenWork\Contracts\Application as ApplicationInterface;
use EasyWeChat\Kernel\Contracts\AccessTokenAwareHttpClient as HttpClientInterface;
use EasyWeChat\Kernel\Contracts\Server as ServerInterface;

class Application implements ApplicationInterface
{
    use InteractWithConfig;
    use InteractWithCache;
    use InteractWithServerRequest;
    use InteractWithAccessTokenClient;

    protected ?ServerInterface $server = null;
    protected ?AccountInterface $account = null;
    protected ?Encryptor $encryptor = null;
    protected ?AccessTokenInterface $accessToken = null;
    protected ?HttpClientInterface $httpClient = null;

    /**
     * @var array
     */
    public const DEFAULT_HTTP_OPTIONS = [
        'timeout' => 5.0,
        'base_uri' => 'https://qyapi.weixin.qq.com/',
    ];

    public function getAccount(): AccountInterface
    {
        if (!$this->account) {
            $this->account = new Account(
                corpId: $this->config->get('corp_id'),
                providerSecret: $this->config->get('provider_secret'),
                token: $this->config->get('token'),
                aesKey: $this->config->get('aes_key'),
            );
        }

        return $this->account;
    }

    public function setAccount(AccountInterface $account): static
    {
        $this->account = $account;

        return $this;
    }

    public function getEncryptor(): Encryptor
    {
        if (!$this->encryptor) {
            $this->encryptor = new Encryptor(
                $this->getAccount()->getCorpId(),
                $this->getAccount()->getToken(),
                $this->getAccount()->getAesKey(),
            );
        }

        return $this->encryptor;
    }

    public function setEncryptor(Encryptor $encryptor): static
    {
        $this->encryptor = $encryptor;

        return $this;
    }

    /**
     * @throws \ReflectionException
     * @throws \EasyWeChat\Kernel\Exceptions\InvalidArgumentException
     * @throws \Throwable
     */
    public function getServer(): ServerInterface
    {
        if (!$this->server) {
            $this->server = new Server(
                account: $this->getAccount(),
                request: $this->getRequest(),
                encryptor: $this->getEncryptor()
            );
        }

        return $this->server;
    }

    public function setServer(ServerInterface $server): static
    {
        $this->server = $server;

        return $this;
    }

    public function getHttpClient(): HttpClientInterface
    {
        if (!$this->httpClient) {
            $this->httpClient = (new HttpClient())
                ->withOptions(\array_merge(self::DEFAULT_HTTP_OPTIONS, $this->config->get('http', [])));
        }

        return $this->httpClient;
    }

    public function setHttpClient(HttpClientInterface $httpClient): static
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    public function getProviderAccessToken(): AccessTokenInterface
    {
        if (!$this->accessToken) {
            $this->accessToken = new ProviderAccessToken(
                corpId: $this->getAccount()->getCorpId(),
                providerSecret: $this->getAccount()->getProviderSecret(),
                cache: $this->getCache(),
                httpClient: $this->getHttpClient(),
            );
        }

        return $this->accessToken;
    }

    public function setProviderAccessToken(AccessTokenInterface $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getAuthorization(string $corpId, string $permanentCode, AccessTokenInterface $suiteAccessToken): Authorization
    {
        $response = $this->getHttpClient()->request(
            'POST',
            'cgi-bin/service/get_auth_info',
            [
                'query' => [
                    'suite_access_token' => $suiteAccessToken->getToken(),
                ],
                'json' => [
                    'auth_corpid' => $corpId,
                    'permanent_code' => $permanentCode,
                ],
            ]
        )->toArray();

        if (empty($response['auth_corp_info'])) {
            throw new HttpException('Failed to get auth_corp_info.');
        }

        return new Authorization($response);
    }

    public function getAuthorizerAccessToken(string $corpId, string $permanentCode, AccessTokenInterface $suiteAccessToken): AuthorizerAccessToken
    {
        $response = $this->getHttpClient()->request(
            'POST',
            'cgi-bin/service/get_corp_token',
            [
                'query' => [
                    'suite_access_token' => $suiteAccessToken->getToken(),
                ],
                'json' => [
                    'auth_corpid' => $corpId,
                    'permanent_code' => $permanentCode,
                ],
            ]
        )->toArray();

        if (empty($response['access_token'])) {
            throw new HttpException('Failed to get access_token.');
        }

        return new AuthorizerAccessToken($corpId, accessToken: $response['access_token']);
    }
}
