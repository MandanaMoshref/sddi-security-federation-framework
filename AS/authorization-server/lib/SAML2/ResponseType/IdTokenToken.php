<?php

/*
Copyright © 2019 Secure Dimensions GmbH

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the “Software”), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
*/

namespace SAML2\ResponseType;

use SAML2\ResponseType\AccessTokenInterface;
use OAuth2\OpenID\ResponseType\IdTokenInterface;
use OAuth2\OpenID\ResponseType\IdTokenTokenInterface;
use OAuth2\Encryption\EncryptionInterface;
use OAuth2\Encryption\Jwt;
use OAuth2\Storage\PublicKeyInterface;
use SAML2\Storage\UserClaimsInterface;

class IdTokenToken implements IdTokenTokenInterface
{
    protected $accessToken;
    protected $idToken;

    public function __construct(AccessTokenInterface $accessToken, IdTokenInterface $idToken, UserClaimsInterface $userClaimsStorage, PublicKeyInterface $publicKeyStorage, EncryptionInterface $encryptionUtil = null, array $config = array())
    {
        $this->accessToken = $accessToken;
        $this->idToken = $idToken;

        $this->userClaimsStorage = $userClaimsStorage;
        $this->publicKeyStorage = $publicKeyStorage;
        if (is_null($encryptionUtil)) {
            $encryptionUtil = new Jwt();
        }
        $this->encryptionUtil = $encryptionUtil;

        if (!isset($config['issuer'])) {
            throw new \LogicException('config parameter "issuer" must be set');
        }
        $this->config = array_merge(array(
            'id_lifetime' => 3600,
        ), $config);

    }

    public function getAuthorizeResponse($params, $user_id = null)
    {
        $result = $this->accessToken->getAuthorizeResponse($params, $user_id);
        $access_token = $result[1]['fragment']['access_token'];
        //$result = array('query' => array());
        $params += array('scope' => null, 'state' => null, 'nonce' => null);

	if (isset($params['state'])) {
       		$result[1]["fragment"]["state"] = $params['state'];
       	}
	if (strstr($params['scope'], 'openid'))
	{
        	// create the id token.
        	$userClaims = $this->userClaimsStorage->getUserClaims($user_id, $params['scope']);

        	$id_token = $this->createIdToken($params['client_id'], $user_id, $params['nonce'], $userClaims, $access_token);
        	$result[1]['fragment']['id_token'] = $id_token;
	}

	return array($params['redirect_uri'], $result[1]);
    }

    public function createIdToken($client_id, $userInfo, $nonce = null, $userClaims = null, $access_token = null)
    {
        // pull auth_time from user info if supplied
        list($user_id, $auth_time) = $this->getUserIdAndAuthTime($userInfo);

        $token = array(
            'iss'        => $this->config['issuer'],
            'sub'        => $userClaims['sub'], //$user_id,
            'aud'        => $client_id,
            'iat'        => time(),
            'exp'        => time() + $this->config['id_lifetime'],
            'auth_time'  => $auth_time,
        );

        if ($nonce) {
            $token['nonce'] = $nonce;
        }

	$finalClaims = array();
        if ($userClaims) {
	  foreach($userClaims as $key => $claim)
	    if (!is_null($claim))
	      $finalClaims += array($key => $claim);
        }

	$token += $finalClaims;

        if ($access_token) {
            $token['at_hash'] = $this->createAtHash($access_token, $client_id);
        }

        return $this->encodeToken($token, $client_id);
    }

    protected function createAtHash($access_token, $client_id = null)
    {
        // maps HS256 and RS256 to sha256, etc.
        $algorithm = $this->publicKeyStorage->getEncryptionAlgorithm($client_id);
        $hash_algorithm = 'sha' . substr($algorithm, 2);
        $hash = hash($hash_algorithm, $access_token, true);
        $at_hash = substr($hash, 0, strlen($hash) / 2);

        return $this->encryptionUtil->urlSafeB64Encode($at_hash);
    }

    protected function encodeToken(array $token, $client_id = null)
    {
        $private_key = $this->publicKeyStorage->getPrivateKey($client_id);
        $algorithm = $this->publicKeyStorage->getEncryptionAlgorithm($client_id);

        return $this->encryptionUtil->encode($token, $private_key, $algorithm);
    }

    private function getUserIdAndAuthTime($userInfo)
    {
        $auth_time = null;

        // support an array for user_id / auth_time
        if (is_array($userInfo)) {
            if (!isset($userInfo['user_id'])) {
                throw new \LogicException('if $user_id argument is an array, user_id index must be set');
            }

            $auth_time = isset($userInfo['auth_time']) ? $userInfo['auth_time'] : null;
            $user_id = $userInfo['user_id'];
        } else {
            $user_id = $userInfo;
        }

        if (is_null($auth_time)) {
            $auth_time = time();
        }

        // userInfo is a scalar, and so this is the $user_id. Auth Time is null
        return array($user_id, $auth_time);
    }

}
