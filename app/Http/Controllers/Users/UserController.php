<?php

namespace App\Http\Controllers\Users;

use App\Http\Requests\registerRequest;
use App\Http\Requests\userstatus;
use App\Imports\DeanImport;
use App\Imports\UsersImport;
use App\Models\college;
use App\Models\dean;
use App\Models\user;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use Rap2hpoutre\FastExcel\FastExcel;

class UserController extends Controller
{
    /**
     * 注册
     * @param Request $registeredRequest
     * @return \Illuminate\Http\JsonResponse
     * @throws \Exception
     */
    public function registered(registerRequest $registeredRequest)
    {
        $count = Users::checknumber($registeredRequest);   //检测账号密码是否存在
        if($count == 0)
        {
            $student_id = Users::createUser(self::userHandle($registeredRequest));

            return  $student_id ?
                json_success('注册成功!',$student_id,200  ) :
                json_fail('注册失败!',null,100  ) ;
        }
        else{
            return
                json_success('注册失败!该工号已经注册过了！',null,100  ) ;
        }
    }


    /**
     * 登录
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(registerRequest $request)
    {

        $credentials = self::credentials($request);   //从前端获取账号密码
        //以手机号登录测试，具体根据自己的业务逻辑
        //    $user = DB::table('users')->first();
        /*   if(!$user){
              $user = new UsersModel();
              $user->phone = $phone;
              $user->save();
          }*/
        //方式一
        // $token = JWTAuth::fromUser($user);
        //方式二
        $token = auth('api')->attempt($credentials);   //获取token
//        if(!$token){
//            return response()->json(['error' => 'Unauthorized'],401);
//        }
//        return self::respondWithToken($token, '登录成功!');   //可选择返回方式
        return $token?
            json_success('登录成功!',$token,  200):
            json_fail('登录失败!账号或密码错误',null, 100 ) ;
        //       json_success('登录成功!',$this->respondWithToken($token,$user),  200);
    }
    /**
     * 封装token的返回方式
     */
    protected function respondWithToken($token, $msg)
    {
        // $data = Auth::user();
        return json_success( $msg, array(
            'token' => $token,
            //设置权限  'token_type' => 'bearer',
            'expires_in' => auth('api')->factory()->getTTL() * 60
        ),200);
    }
    /**
     * 获取账号密码返回
     */
    protected function credentials($request)   //从前端获取账号密码
    {
        return ['account' => $request['account'], 'password' => $request['password']];
    }
    /**
     * 对密码进行哈希256加密
     */
    protected function userHandle($request)   //对密码进行哈希256加密
    {
        $registeredInfo = $request->except('password_confirmation');
        $registeredInfo['password'] = bcrypt($registeredInfo['password']);
        $registeredInfo['account'] = $registeredInfo['account'];
        $registeredInfo['level'] = $request['level'];
        return $registeredInfo;
    }
    /**
     * 修改密码时从新加密
     */
    protected function userHandle111($password)   //对密码进行哈希256加密
    {
        $red = bcrypt($password);
        return $red;
    }
    /**
     * 修改密码
     */
    public function again(Request $registeredRequest)
    {
        $account     = $registeredRequest['account'];
        $newpassword = $registeredRequest['newpassword'];

        $password3 = self::userHandle111($newpassword);
        $red = DB::table('users')->where('account', '=', $account)->update([
            'password' => $password3
        ]);
        return $red ?
            json_success('修改成功!', $red, 200) :
            json_fail('修改失败!', null, 100);
    }



    /**
     * 导出excel
     */
    public function export()
    {
        $d=Users::select()->get();
        return (new FastExcel($d))->download('模板' . '.xlsx');
    }
    /**
     * 导入excel
     */
    public function import(Request $request)
    {
        $file = $request['file'];
         $res= Excel::import(new UsersImport, $file);
        return $res?
            json_success('导入成功!',null,  200):
            json_fail('导入失败!',null, 100 ) ;
    }

    public function deanimport(Request $request)
    {
        $file = $request['file'];
        $res= Excel::import(new DeanImport, $file);
        return $res?
            json_success('导入成功!',null,  200):
            json_fail('导入失败!',null, 100 ) ;
    }


    //院长导出
    public function deanExport()
    {
        $d=dean::select()->get();
        return (new FastExcel($d))->download('学生信息' . '.xlsx');
    }

    //学生导出
    public function studentExport(Request $request)
    {
        $SNumber=$request['SNumber'];
        $d=college::export($SNumber);

        return (new FastExcel($d))->download('模板' . '.xlsx');
    }


    //教师导出
    public function teacherExport(Request $request)
    {
        $ClassName=$request['ClassName'];

        $d=college::studentexport($ClassName);

        return (new FastExcel($d))->download('模板' . '.xlsx');
    }
    /**
     * 邮件发送
     */
    public function sendmail(userstatus $request){
        $email = $request->input('account');
        Mail::raw("忘记密码请点击这里 http://127.0.0.1:8000/api/users/userchange", function ($message) use ($email) {
            $message->subject("东软学院");
            // 发送到哪个邮箱账号
            $message->to($email);
        });
        // 判断邮件是否发送失败
        return $email ?
            json_success('操作成功!', $email, 200):
            json_fail('操作失败!', null, 100);
    }

    public function userChange(userstatus $request){
        $account=$request['account'];
        $res=user::change($account);
        return $res ?
            json_success('操作成功!', $res, 200):
            json_fail('操作失败!', null, 100);
    }
    //忘记密码-修改密码
    public function modify(Request $registeredRequest)
    {
        $account     = $registeredRequest['account'];
        $newpassword = $registeredRequest['password'];


        $password3 = self::userHandle111($newpassword);
        $red = DB::table('user')->where('account', '=', $account)->update([
            'password' => $password3,
            'change'=>0

        ]);
        return $red ?
            json_success('修改成功!', $red, 200) :
            json_fail('修改失败!', null, 100);
    }
}
