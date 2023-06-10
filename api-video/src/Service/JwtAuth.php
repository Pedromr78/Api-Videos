<?php
namespace App\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

class JwtAuth{
   
    public EntityManagerInterface $doctrine;
    public $key;
   
    public function __construct(
        private EntityManagerInterface $doctrines,
     
    ) {
        $this->doctrine = $doctrines;
        $this->key= '49217941Venom._1234';
    }


    public function signup($email,$password, $gettoken = null){
        $user  = $this->doctrine->getRepository(Users::class)->findOneBy([
            'email' => $email,
            'password' => $password
        ]);

        $signup=false;

        if(is_object($user)){
            $signup=true;
        }
        if($signup){
            $token=[
                'sub' => $user->getId(),
                'name' => $user->getName(),
                'surname' => $user->getSurname(),
                'email' => $user->getEmail(),
                'iat' =>time(),
                'exp' => time()+(7*24*60*60)
            ];


            $jwt = JWT::encode($token,$this->key, 'HS256');
            if($gettoken){
              $data= $jwt;
            }else{
                // $decode = JWT::decode($jwt,$this->key, 'HS256');
                $data= $token;
            }
           

        }else{
            $data=[
                'status' => 'error',
                'message' => 'No se logueo correctamente',
          
            ];
        }
       return ($data);
    }
    public function checkToken($token, $identity=false){
        $auth= false;
        try{
            $decoded= JWT::decode($token, new Key($this->key, 'HS256'));
        }catch(\UnexpectedValueException $e){
            $auth=false;
        }catch(\DomainException $e){
            $auth=false;
        }
    
        if(isset($decoded) && !empty($decoded) && is_object($decoded) && isset($decoded->sub)){
            $auth=true;
        }else{
            $auth=false;
        }
        if($identity!=false){
         return $decoded;
        }else{
            return ($auth);
        }


      
    }

}
