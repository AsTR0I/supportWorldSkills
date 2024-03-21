<!-- Фасады -->

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;


<!-- validatorFails -->
public function validatorFails($validator){
            return response() -> json([
                "Status" => 422,
                "Content-Type" => "application/json",
                "Body" =>
                [
                "success" => false,
                "message" => [
                        $validator -> errors()
                    ]
                ]
            ]);
    }

  <!-- пример валидатора: -->
  $validator = Validator::make($request -> all(),[
            'email' => 'required|email|unique:users,email',
            'password' => 'required|regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])/|min:3',
            'first_name' => 'required',
            'last_name' => 'required'
        ]);

        if($validator -> fails()){
            return $this -> validatorFails($validator);

        } else {

        }
<!-- bin2hex -->
bin2hex(openssl_random_pseudo_bytes(16));

<!-- работа с файлами -->

// Чтение файла
$contents = Storage::get('example.txt');

// Запись файла
Storage::put('newfile.txt', $contents);

// Удаление файла
Storage::delete('example.txt');

// Проверка существования файла
if (Storage::exists('example.txt')) {
    // Файл существует
}

// Работа с публичными файлами
$url = Storage::url('public/file.jpg');

<!-- принять и загрзить файлы -->

public function filesPOST(Request $request){

$token = $request -> bearerToken();
$token_table = Token::where('token_value', $token) -> first();

if(!$token_table){
    return $this -> LoginFailed();
} else {

    $files_response = [];
    $files = $request -> file('files');

    foreach($files as $file){
        $validator = Validator::make(['file' => $file],[
            'file' => 'max:2048|mimes:doc,pdf,docx,zip,jpeg,jpg,png'
        ]);

        if ($validator->fails()) {
            $files_response[] = [
                "success"=> false,
                "message"=> "File not loaded",
                "name"=> $file -> getClientOriginalName()
            ];
        } else {
            $newFileName = pathinfo($file -> getClientOriginalName() ,PATHINFO_FILENAME);
            $fileExtension = $file -> getClientOriginalExtension();
            
            // Check if the file with the same name exists
            if (Storage::exists('files/' . $newFileName . '.' . $fileExtension)) {
                $count = 1;
                while (Storage::exists('files/' . $newFileName . '(' . $count . ').' . $fileExtension)) {
                    $count++;
                }
                $newFileName = $newFileName . '(' . $count . ')';
            }

            Storage::putFileAs('files', $file, $newFileName . '.' . $fileExtension);

            $file_id = bin2hex(openssl_random_pseudo_bytes(10));

            $newFile = new File();
            $newFile -> user_id = $token_table -> user_id;
            $newFile -> file_id = $file_id;
            $newFile -> name = $newFileName . '.'. $fileExtension;
            $newFile -> save();

            $file_access = new FileAccess();
            $file_access -> file_id = $file_id;
            $file_access -> user_id = $token_table -> user_id;
            $file_access -> access_type = "owner";
            $file_access -> save();

            $files_response[] = [
                "success" => true,
                "message"=> "Success",
                "name" => $file -> getClientOriginalName(),
                "url" => "{{host}}/".'files/'.$newFileName,
                "file_id" => $file_id                      
            ];
        }
    }

    return response() -> json([
        "Status"=> 200,
        "Content-Type"=> "application/json",
        "Body"=>[
            $files_response
        ]
    ]);
}
}

<!-- переименовать фаил -->

public function filesPATCH($file_id,Request $request){
        $token = $request -> bearerToken();
        $token_table = Token::where('token_value', $token) -> first();

        $file = File::where('file_id', $file_id) -> first();

        $file_name = $request -> input('name');

        if(!$token_table){
            return $this -> LoginFailed();
        } else {
            $validator = Validator::make($request -> all(),[
                "name" => 'required'
            ]);

            if($validator -> fails()){
                return $this -> validatorFails($validator);
            } else {
                if(!$file){
                    return $this -> NotFound();
                } else {
                    if($file -> user_id != $token_table -> user_id){
                        return $this -> forbiddenForYou();
                    } else {
                        // меняем в файлах
                        $oldName = 'files/'.$file -> name;
                        $newName = 'files/'.$file_name.'.'.pathinfo($file -> name,PATHINFO_EXTENSION);

                        // меняем в бд
                        $file -> name = $file_name.'.'.pathinfo($file -> name,PATHINFO_EXTENSION);
                        $file -> save();

                        Storage::move($oldName,$newName);

                        return response() -> json([
                            "Status" => 200,
                            "Content-Type" => "application/json",
                            "Body"=>
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

    <!-- удалить -->
    public function filesDELETE($file_id,Request $request){
        $token = $request -> bearerToken();
        $token_table = Token::where('token_value', $token) -> first();
        
        $file = File::where('file_id', $file_id) -> first();

        if(!$token_table){
            return $this -> LoginFailed();
        } else {
            $file = File::where('file_id', $file_id) -> first();
            if(!$file){
                return $this -> NotFound();
            } else {
                $file_access = FileAccess::where('file_id', $file -> file_id) -> where('user_id', $token_table -> user_id)-> first();
                if($file_access &&  ($file_access -> access_type !== 'owner' )){
                    return $this -> forbiddenForYou();
                } else {
                    Storage::delete('files/'.$file -> name);
                    $file_access -> delete();
                    $file -> delete();

                    return response() -> json([
                        "Status" => 200,
                        "Content-Type"=> "application/json",
                        "Body"=>
                        [
                        "success"=> true,
                        "message"=> "File already deleted"
                        ]

                    ]);
                }
            }
        }
    }
Route::prefix('api')->group(function () {
    Route::get('/users', 'UserController@index');
    Route::post('/users', 'UserController@store');
    // Другие маршруты API здесь
});
