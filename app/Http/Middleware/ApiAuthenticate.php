<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use GuzzleHttp\Client;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function handle($request, Closure $next)
    {
        $this->authenticate($request);

        return $next($request);
    }

    /**
     * Determine if the user is logged in to any of the given guards.
     * @return void
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function authenticate(Request $request)
    {
        try{
            $response = (new Client())->post(config('app.usercenter_host')."/api/checkAuth", [
                'headers' => [
                    'authorization' => $request->header('authorization')
                ]
            ])->getBody()->getContents();

            if(!$response){
                Log::error("认证服务器错误");
                throw new \Exception("认证服务器错误");
            }
            $result = json_decode($response, true);
            if($result['code'] == 300){
                throw new \Exception("认证失败");
            }

            $request->setUserResolver(function() use($result){
                return User::where("id",$result['data']['id'])->first();
            });
            return;
        }catch (\Exception $e){
        }

        throw new AuthenticationException('Unauthenticated.');
    }
}
