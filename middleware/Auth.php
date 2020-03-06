<?php

define('sim-rest', TRUE);

class Auth{

    public $admins;
    public $token;

    public function __construct($token){
        $this->token = explode(" ",$token)[1];
    }
    
    public function check($token){

        $this->admins = $this->getAdmin();

        $filtered = array_filter($this->admins, function($admin) use($token){
            if($admin->token !== $token){
                return false;
            }
            return true;
        });
        
        if(empty($filtered)){
            return false;
        }

        return true;
    }

    public function login($payload){

        $this->admins = $this->getAdmin();

        $filtered = array_filter($this->admins, function($admin) use($payload){
            if($admin->username !== $payload["username"] || $admin->password !== $payload["password"]){
                return false;
            }
            return true;
        });

        if(empty($filtered)){
            return Helper::response(401,"Invalid username or password.",null);
        }
        
        return Helper::response(200,"Token generated.",["token"=>$this->generateToken($payload)]);
    }

    public function generateToken($payload){
        $token = base64_encode(bin2hex(openssl_random_pseudo_bytes(100)));
        $payload["token"] = $token;
        $updatedToken = array_map(function ($admin) use ($payload) {
            if($admin->username == $payload["username"]){
                $payload["_updated"] = time();
                return $payload;
            }
            return $admin;
        }, $this->admins);

        $this->appendInConfig($updatedToken);
        return $payload["token"];
    }

    public function getAdmin(){
        ob_start();
        require_once "database/config.php";
        $raw = ob_get_clean();
        return json_decode($raw)->admins;
    }

    /**
     * Append in database collection
     * @param array
     * @return void
     */
    public function appendInConfig($data){

        $newData->admins = $data;

        // Append array
        $head = "<?php 
        if(!defined('sim-rest')){ exit;}         
        header('Content-Type: application/json');
?>";

        $append = $head."\n".json_encode($newData,JSON_PRETTY_PRINT);

        file_put_contents("database/config.php",$append);
    }

     /**
     * Protect Route with Auth
     */
    public function routes(){

        if(!$this->check($this->token)){
            return false;
        }
        
        return true;
    }

}

$auth = new Auth($_SERVER['HTTP_AUTHORIZATION']);