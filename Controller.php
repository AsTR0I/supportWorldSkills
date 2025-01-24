<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;


// Models
use App\Models\User;
use App\Models\File;
use App\Models\FileAccess;
use App\Models\Token;
use Illuminate\Contracts\Cache\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;


    public function Loginfailed(){
        return response() -> json([
            "Status"=> 403,
            "Content-Type"=> "application/json",
            "Body"=>
            [
               "message"=> "Login failed"
            ]
        ], 403);
    }

    public function Forbiddenforyou(){
        return response() -> json([
            "Status" => 403,
            "Content-Type" => "application/json",
            "Body" =>
            [
               "message" => "Forbidden for you"
            ]
        ],403);
    }

    public function Notfound(){
        return response() -> json([
            "Status" => 404,
            "Content-Type" => "application/json",
            "Body" =>
            [
                "message" => "Not found"
            ]
        ]);
    }

    public function validatorFails($validator){
        return response() -> json([
            "Status" => 422,
            "Content-Type" => "application/json",
            "Body" =>
            [
            "success" => false,
            "message" => $validator -> errors()
            ]
        ]);
    }

    // public function (Request $request){

    // }

       public function authorization(Request $request){

        $email = $request -> input('email');
        $password = $request -> input('password');

        $user = User::where('email', $email) -> first();

        if($user -> password !== $password){
            return $this -> Loginfailed();
        }
        $newToken = bin2hex(openssl_random_pseudo_bytes(16));
        
        $token = Token::where('user_id', $user -> id) -> first();
        $token -> token_value = $newToken;
        $token -> save();

        return response() -> json([
            "Status" => 200,
            "Content-Type" => "application/json",
            "Body" =>
            [
                "success" => true,
                "message" => "Success",
                "token" => $token -> token_value
            ]
        ]);
       }

       public function registration(Request $request){
            $email = $request -> input('email');
            $password = $request -> input('password');
            $first_name = $request -> input('first_name');
            $last_name = $request -> input('last_name');

            $validator = Validator::make($request -> all(),[
                "email" => 'required|email|unique:users,email',
                "password" => 'required|min:3|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])/',
                "first_name" => 'required|',
                "last_name" => 'required|'
            ]);

            if($validator -> fails()){
                return $this -> validatorFails($validator);
            }

            $newUser =  new User;
            $newUser -> email =$email;
            $newUser -> password =$password;
            $newUser -> first_name =$first_name;
            $newUser -> last_name =$last_name;
            $newUser -> save();

            $token = new Token;
            $token -> user_id = $newUser -> id;
            $token -> token_value = bin2hex(openssl_random_pseudo_bytes(16));
            $token -> save();

            return response() -> json([
                "Status" => 200,
                "Content-Type" => "application/json",
                "Body" =>
                [
                    "success" => true,
                    "message" => "Success",
                    "token" => $token -> token_value
                ]
            ]);
       }

       public function logout(Request $request){
            $bearerToken = $request -> bearerToken();
            $token = Token::where('token_value',$bearerToken ) -> first();
            if(!$token){
                return $this -> Loginfailed();
            }

            $token -> token_value = null;
            $token -> save();
            return response() -> json([
                "Status" => 200,
                "Content-Type" => "application/json",
                "Body" =>
                [
                    "success" => true,
                    "message" => "Logout"

                ]
            ]);
       }

    //    Загрузка файлов:
    public function files(Request $request){
        $bearerToken = $request -> bearerToken();

        $token = Token::where('token_value',$bearerToken ) -> first();

        if(!$token){
            return $this -> Loginfailed();
        } else {
            $files = $request -> file('files');

            $responsFilesArray = [];
            $user = User::where('id',$token -> user_id) -> first();

            foreach($files as $file){
                $validator = Validator::make(['file' => $file],[
                    "file" => 'max:2048|mimes:doc,pdf,docx,zip,jpeg,jpg,png,7z',
                ]);
    
                if($validator -> fails()){
                    $responsFilesArray[] = [
                        "success" => false,
                        "message" => "File not loaded",
                        "name" => $file -> getClientOriginalName()
                    ];
                } else {
                   $newFileName = pathinfo($file -> getClientOriginalName(), PATHINFO_FILENAME);
                   $fileExtension = $file -> getClientOriginalExtension();

                   if(Storage::exists('files/'.$newFileName.'.'.$fileExtension)){
                        $count = 1;
                        while(Storage::exists('files/'.$newFileName.'('.$count.')'. $fileExtension)){
                            $count++;
                        }
                        $newFileName = $newFileName . '(' . $count . ')';
                   }
                   Storage::putFileAs('files/', $file, $newFileName .'.'. $fileExtension);

                   $file_id = bin2hex(openssl_random_pseudo_bytes(10));

                   $newFile = new File();
                   $newFile -> user_id = $user -> id;
                   $newFile -> file_id = $file_id;
                   $newFile -> name = $newFileName .'.'. $fileExtension;
                   $newFile -> save();

                   $fileAccess = new FileAccess();
                   $fileAccess -> file_id = $file_id;
                   $fileAccess -> user_id = $user -> id;
                   $fileAccess -> access_type = "owner";
                   $fileAccess -> save();

                   $responsFilesArray[] = [
                    "success" => true,
                    "message"=> "Success",
                    "name" => $file -> getClientOriginalName(),
                    "url" => "{{host}}/".'files/'.$newFileName,
                    "file_id" => $file_id
                   ];
                }
            }
            return response() -> json([
                "Status" => 200,
                "Content-Type" => "application/json",
                "Body" => $responsFilesArray
            ]);
        }

    }

    // Редактирование файла:
    public function filesPATCH($id,Request $request){
        $bearerToken = $request -> bearerToken();
        
        $token = Token::where('token_value',$bearerToken ) -> first();
        $file = File::where('file_id', $id) -> first();
    
        if(!$token){
            return $this -> Loginfailed();
        } else {
            $file_access = FileAccess::where('file_id', $id) -> where('user_id',$token->user_id) -> first();
            if(!$file){
                return $this -> Notfound();
            } else {
                if(!$file_access){
                    return $this -> Forbiddenforyou();
               } else {
                    $file_new_name = $request -> input('name');

                    // required - обязательное поле.
                    // unique:files,name,NULL,id - проверка уникальности имени файла в таблице files, исключая текущее значение поля name.
                    $validator = Validator::make($request -> all(),[
                        'name' => 'required|unique:files,name,NULL,id,user_id,' . $token->user_id
                    ]);
                    if($validator -> fails()){
                        return $this -> validatorFails($validator);
                    } else {

                        $fileExtension = pathinfo($file->name, PATHINFO_EXTENSION);

                        $oldName = pathinfo($file->name, PATHINFO_FILENAME);
                        $newName = pathinfo($file_new_name, PATHINFO_FILENAME);

                        $fullName = $newName . '.' . $fileExtension;
                        $file -> name = $fullName;
                        $file -> save();
                        
                        Storage::move('files/'.$oldName.'.'.$fileExtension, 'files/'.$newName.'.'.$fileExtension);

                        return response() -> json([
                            "Status" => 200,
                            "Content-Type" => "application/json",
                            "Body" =>
                                [
                                    "success" => true,
                                    "message" => "Renamed"
                                ]

                        ]);
                    }
               }
            }
        }
    }

    // Удаление файла:
    public function filesDELETE($id,Request $request){
        $bearerToken = $request -> bearerToken();
        
        $token = Token::where('token_value',$bearerToken ) -> first();
        $file = File::where('file_id', $id) -> first();
        $file_access = FileAccess::where('file_id',$id) -> where('user_id', $token -> user_id) -> first();

        if(!$token){
            return $this -> Loginfailed();
        } else {
            if(!$file){
                return $this-> Notfound();
            } else {
                if(!$file_access || $file_access -> access_type != 'owner'){
                    return $this -> Forbiddenforyou();
                } else {
                    Storage::delete('files/'.$file -> name);
                    $file -> delete();
                    $file_accesses = File::where('id',$id) -> get();
                    foreach($file_accesses as $fa){
                        $fa -> delete();
                    }
                    return response() -> json([
                        "Status" => 200,
                        "Content-Type" => "application/json",
                        "Body" =>
                        [
                        "success" => true,
                        "message" => "File already deleted"
                        ]

                    ]);
                    
                }
            }
        }
    }

    // Скачивание файла
    public function filesDOWNLOAD($id,Request $request){
        $bearerToken = $request -> bearerToken();
        
        $token = Token::where('token_value',$bearerToken ) -> first();
        $file = File::where('file_id', $id) -> first();
        $file_access = FileAccess::where('file_id', $id)->where('user_id', $token->user_id)->first();
        
        if(!$token){
            return $this -> Loginfailed();
        } else {
            if(!$file){
                return $this-> Notfound();
            } else {
                if($file_access && ($file_access -> access_type == 'owner' || $file_access -> access_type == 'co_owner')){
                    $file = File::where('file_id',$id) -> first();
                    return Storage::download('files/'.$file->name);
                } else {
                    return $this -> Forbiddenforyou();
                }
            }
        }
    }

    // Добавление прав доступа:
    public function filesACCESSESADD($id,Request $request) {
        $bearerToken = $request -> bearerToken();
        
        $token = Token::where('token_value',$bearerToken ) -> first();
        $file = File::where('file_id', $id) -> first();

        $email = $request -> input('email');
        if(!$token){
            return $this -> Loginfailed();
        } else {
            if(!$file){
                return $this-> Notfound();
            } else {
                $file_access = FileAccess::where('file_id', $id)->where('user_id', $token->user_id)->first();
                if($file_access && $file_access -> access_type == 'owner'){
                    $newAccess = new FileAccess();
                    $newAccess -> file_id = $id;
                    $newAccess -> user_id = User::where('email', $email) -> first() -> id;
                    $newAccess -> access_type = 'co_owner';
                    $newAccess -> save();

                    $allAccess = [];
                    $FileAccess = FileAccess::where('file_id', $id)->get();
                    foreach($FileAccess as $access){
                        $user_access = User::where('id',$access -> user_id) -> first(); 
                        $allAccess[] = [
                            "fullname" => $user_access -> first_name . ' ' . $user_access -> last_name,
                            "email" => $user_access -> email,
                            "type" => $access -> access_type            
                        ];
                    }
                    return response() -> json([
                        "Status" => 200,
                        "Content-Type" => "application/json",
                        "Body" => $allAccess
                    ]);
                } else {
                    return $this -> Forbiddenforyou();
                }
            }
        }
    }

    // Удаление прав доступа
    public function filesACCESSESDELETE($id,Request $request) {
        $bearerToken = $request -> bearerToken();
        
        $token = Token::where('token_value',$bearerToken ) -> first();
        $file = File::where('file_id', $id) -> first();

        $email = $request -> input('email');
        if(!$token){
            return $this -> Loginfailed();
        } else {
            if(!$file){
                return $this-> Notfound();
            } else {
                $file_access = FileAccess::where('file_id', $id)->where('user_id', $token->user_id)->first();
                if($file_access && ($file_access -> access_type == 'owner')){
                    $userForDelete = User::where('email',$email) -> first();
                    $IsAccess = FileAccess::where('file_id', $id) -> where('user_id',$userForDelete -> id) -> first();
                    if(!$IsAccess){
                        return $this-> Notfound();
                    } else {

                        if($userForDelete -> id == $token -> user_id){
                            return $this -> Forbiddenforyou();
                        } else {
                            $IsAccess -> delete();
                            $allAccess = [];
                            $FileAccess = FileAccess::where('file_id', $id)->get();
                            foreach($FileAccess as $access){
                                $user_access = User::where('id',$access -> user_id) -> first(); 
                                $allAccess[] = [
                                    "fullname" => $user_access -> first_name . ' ' . $user_access -> last_name,
                                    "email" => $user_access -> email,
                                    "type" => $access -> access_type            
                                ];
                            }
                            return response() -> json([
                                "Status" => 200,
                                "Content-Type" => "application/json",
                                "Body" =>$allAccess
                            ]);
                        }
                    }
                } else {
                    return $this -> Forbiddenforyou();
                }
            }
        }            
    }

    // Просмотр файлов пользователя:

    public function filesDISKGET(Request $request){
        $bearerToken = $request -> bearerToken();
        
        $token = Token::where('token_value',$bearerToken ) -> first();
        if(!$token){
                return $this -> Loginfailed();
            } else {
                $files = File::where('user_id', $token -> user_id) -> get();
                $files_array = [];
                foreach($files as $file){
                    $file_access = FileAccess::where('file_id', $file -> file_id) -> get();
                    $file_access_array = [];
                    foreach($file_access as $fa){
                        $user = User::where('id', $fa -> user_id) -> first();
                        $file_access_array[] = [
                            "fullname" => $user -> first_name . ' ' . $user -> last_name,
                            "email" =>  $user -> email,
                            "type" => $fa -> access_type
                        ];
                    }

                    $files_array[] = [
                        "file_id" =>  $file -> file_id,
                        "name" =>  $file -> name,
                        "url" =>  '{{host}}/files/'.$file -> name,
                        "accesses" => $file_access_array
                    ];
                }

                return response() -> json([
                    "Status" => 200,
                    "Content-Type" => "application/json",
                    "Body" => $files_array
                ]);
            }
        }   
}
    
