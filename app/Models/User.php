<?php

namespace App\Models;

use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Auth;
use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use Traits\ActiveUserHelper;
    use Traits\LastActivedAtHelper;
    use HasRoles;
    use Notifiable{
        notify as protected laravelNotify;
    }
    use HasApiTokens;

    public function notify($instance)
    {
        if($this->id == Auth::id()){
            return;
        }
        $this->increment('notification_count');
        $this->laravelNotify($instance);
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'introduction', 'avatar','phone','weixin_openid', 'weixin_unionid', 'registration_id'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    public function topics()
    {
        return $this->hasMany(Topic::class);
    }

    public function isAuthorOf($model)
    {
        return $this->id == $model->user_id;
    }

    public function replies()
    {
        return $this->hasMany(Reply::class);
    }

    public function markAsRead()
    {
        $this->notification_count = 0;
        $this->save();
        $this->unreadNotifications->markAsRead();
    }

    public function setPasswordAttribute($value)
    {
       // 如果长度等于60 ，既默认已经加过密
        if (strlen($value) != 60){
            // 不等于60，做加密
            $value = bcrypt($value);
        }

        $this->attributes['password'] = $value;
    }

    public function setAvatarAttribute($path)
    {
        // 如果不是 HTTP 开头的，那就是从后台上传的，需要添加 http
        if (!starts_with($path, 'http')){
            // 添加 http
            $path = config('app.url')."/uploads/images/avatars/$path";
        }

        $this->attributes['avatar'] = $path;
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    public function findForPassport($username)
    {
        filter_var($username, FILTER_VALIDATE_EMAIL)?
            $credentials['emial'] = $username :
            $credentials['phone'] = $username;

        return self::where($credentials)->first();
    }
}
