<?php

declare(strict_types=1);

namespace EasyWeChat\OpenPlatform;

use JetBrains\PhpStorm\ArrayShape;
use EasyWeChat\Kernel\Traits\AccessTokenAwareHttpClient;

class HttpClient implements \EasyWeChat\OpenPlatform\Contracts\HttpClient
{
    use AccessTokenAwareHttpClient;

    protected array $defaultOptions = [
        'base_uri' => 'https://api.weixin.qq.com/',
    ];

    /**
     * @return array
     */
    #[ArrayShape(['component_access_token' => "string"])]
    public function getAccessTokenQuery(): array
    {
        return ['component_access_token' => $this->accessToken->getToken()];
    }
}
