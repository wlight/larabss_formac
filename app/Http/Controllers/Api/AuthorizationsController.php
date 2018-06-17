<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\AuthorizationsRequest;
use App\Http\Requests\Api\SocialAuthorizationRequest;
use App\Traits\PassportToken;
use Illuminate\Http\Request;
use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Auth;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response as Psr7Response;

class AuthorizationsController extends Controller
{
    use PassportToken;
    public function socialStore($type, SocialAuthorizationRequest $request)
    {
        if (!in_array($type, ['weixin'])) {
            return $this->response->errorBadRequest();
        }
        $driver = Socialite::driver($type);
        try{
            if ($code = $request->code) {
                $response = $driver->getAccessTokenResponse($code);
                $token = array_get($response, 'access_token');
            } else {
                $token = $request->access_token;

                if ($type == 'weixin'){
                    $driver->setOpenId($request->openid);
                }
            }

            $oauthUser = $driver->userFromToken($token);
        } catch (\Exception $e) {
            return $this->response->errorUnauthorized('参数错误，未获取用户信息');
        }

        switch ($type){
            case 'weixin':
//                $user = User::where('weixin_unionid', $oauthUser->offsetGet('unionid'))->first();
                $unionid = $oauthUser->offsetExists('unionid') ? $oauthUser->offsetGet('unionid'):null;

                if ($unionid){
                    $user = User::where('weixin_unionid', $unionid)->first();
                } else {
                    $user = User::where('weixin_openid', $oauthUser->getId())->first();
                }

            // 没有用户，默认创建一个用户
            if (!$user){
                    $user = User::create([
                        'name' => $oauthUser->getNickname(),
                        'avatar' => $oauthUser->getAvatar(),
                        'weixin_openid' => $oauthUser->getId(),
                        'weixin_unionid' => $oauthUser->offsetGet('unionid'),
                    ]);
            }

            break;
        }
//        $token = Auth::guard('api')->fromUser($user);
//
//        return $this->responseWithToken($token)->setStatusCode(201);

        $result = $this->getBearerTokenByUser($user, '1', false);
        return $this->response->array($result)->setStatusCode(201);
    }

    public function store(AuthorizationsRequest $request, AuthorizationServer $server, ServerRequestInterface $serverRequest)
    {
        try {
            return $server->respondToAccessTokenRequest($serverRequest, new  Psr7Response)->withStatus(201);
        } catch (OAuthServerException $e){
            return $this->response->errorUnauthorized($e->getMessage());
        }

        /*$username = $request->username;

        filter_var($username, FILTER_VALIDATE_EMAIL) ?
            $credentials['email'] = $username :
            $credentials['phone'] = $username;

        $credentials['password'] = $request->password;

        if (!$token = \Auth::guard('api')->attempt($credentials)){
            return $this->response->errorUnauthorized(trans('auth.failed'));
        }

        return $this->responseWithToken($token)->setStatusCode(201);*/
    }

    protected function responseWithToken($token)
    {
        return $this->response->array([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => \Auth::guard('api')->factory()->getTTL() * 60
        ]);
    }

    public function update(AuthorizationServer $server, ServerRequestInterface $serverRequest)
    {
        /*$token = Auth::guard('api')->refresh();
        return $this->responseWithToken($token);*/

        try {
            return $server->respondToAccessTokenRequest($serverRequest, new Psr7Response);
        } catch (OAuthServerException $e) {
            return $this->response->errorUnauthorized($e->getMessage());
        }
    }

    public function destroy()
    {
        /*Auth::guard('api')->logout();
        return $this->response->noContent();*/

        $this->user()->token()->revoke();
        return $this->response->noContent();
    }
}
