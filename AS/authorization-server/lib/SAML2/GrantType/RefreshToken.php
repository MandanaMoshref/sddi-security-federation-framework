<?php

/*
Copyright © 2019 Secure Dimensions GmbH

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

namespace SAML2\GrantType;

use SAML2\Storage\RefreshTokenInterface;
use OAuth2\GrantType\RefreshToken as BaseRefreshToken;
use OAuth2\GrantType\GrantTypeInterface;
use OAuth2\ResponseType\AccessTokenInterface;
use OAuth2\RequestInterface;
use OAuth2\ResponseInterface;

class RefreshToken extends BaseRefreshToken implements GrantTypeInterface
{
    /**
     * @var array
     */
    private $refreshToken;

    /**
     * @param RefreshTokenInterface $storage - REQUIRED Storage class for retrieving refresh token information
     * @param array                 $config  - OPTIONAL Configuration options for the server
     * @code
     *     $config = array(
     *         'always_issue_new_refresh_token' => true, // whether to issue a new refresh token upon successful token request
     *         'unset_refresh_token_after_use' => true // whether to unset the refresh token after after using
     *     );
     * @endcode
     */
    public function __construct(RefreshTokenInterface $storage, $config = array())
    {
        $this->config = array_merge(array(
            'always_issue_new_refresh_token' => false,
            'unset_refresh_token_after_use' => true
        ), $config);

        // to preserve B.C. with v1.6
        // @see https://github.com/bshaffer/oauth2-server-php/pull/580
        // @todo - remove in v2.0
        if (isset($config['always_issue_new_refresh_token']) && !isset($config['unset_refresh_token_after_use'])) {
            $this->config['unset_refresh_token_after_use'] = $config['always_issue_new_refresh_token'];
        }

        $this->storage = $storage;
    }

    /**
     * Validate the OAuth request
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @return bool|mixed|null
     */
    public function validateRequest(RequestInterface $request, ResponseInterface $response)
    {
        if (!$request->request("refresh_token")) {
            $response->setError(400, 'invalid_request', 'Missing parameter: "refresh_token" is required');

            return null;
        }

        if (!$refreshToken = $this->storage->getRefreshToken($request->request("refresh_token"))) {
            $response->setError(400, 'invalid_grant', 'Invalid refresh token');

            return null;
        }

        if ($refreshToken['expires'] > 0 && $refreshToken["expires"] < time()) {
            $response->setError(400, 'invalid_grant', 'Refresh token has expired');

            return null;
        }

        // store the refresh token locally so we can delete it when a new refresh token is generated
        $this->refreshToken = $refreshToken;

        return true;
    }

    /**
     * Create access token
     *
     * @param AccessTokenInterface $accessToken
     * @param mixed                $client_id   - client identifier related to the access token.
     * @param mixed                $user_id     - user id associated with the access token
     * @param string               $scope       - scopes to be stored in space-separated string.
     * @return array
     */
    public function createAccessToken(AccessTokenInterface $accessToken, $client_id, $user_id, $scope)
    {
        /*
         * It is optional to force a new refresh token when a refresh token is used.
         * However, if a new refresh token is issued, the old one MUST be expired
         * @see http://tools.ietf.org/html/rfc6749#section-6
         */
        $issueNewRefreshToken = $this->config['always_issue_new_refresh_token'];
        $unsetRefreshToken = $this->config['unset_refresh_token_after_use'];
        $token = $accessToken->createAccessToken($client_id, $user_id, $scope, $issueNewRefreshToken, $this->refreshToken['auth_id']);

        if ($unsetRefreshToken) {
            $this->storage->unsetRefreshToken($this->refreshToken['refresh_token']);
        }

        return $token;
    }
}
