<?php

namespace App\Controller;




use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Users;
use App\Entity\Videos;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Monolog\DateTimeImmutable;
use App\Service\JwtAuth;
use Symfony\Component\Serializer\SerializerInterface;


class UserController extends AbstractController
{
   
    public EntityManagerInterface $doctrine;
    public SerializerInterface $serialize;

    public function __construct(
        private EntityManagerInterface $doctrines,
         private SerializerInterface $serializes
     
    ) {
        $this->doctrine = $doctrines;
          $this->serialize = $serializes;
    }

    public function resjson($data){
        $json= $this->serialize->serialize($data, 'json');
 
         $response = new Response();
         $response->setContent($json);
         $response->headers->set('Content-Type', 'application/json');
         return $response;
     }

    public function register(Request $request)
    {
        //Recoger datos 
        $json = $request->get('json', null);
        //decodificar;
        $params = json_decode($json);
        if ($json != null) {
            $name = (!empty($params->name)) ? $params->name : null;
            $surname = (!empty($params->surname)) ? $params->surname : null;
            $email = (!empty($params->email)) ? $params->email : null;
            $password = (!empty($params->password)) ? $params->password : null;
            $validator = Validation::createValidator();
            $validate_email = $validator->validate($email, [
                new Email()
            ]);
            if ($name != null && $surname != null && $email != null && $password != null && count($validate_email) == 0) {

                $user = new Users();
                $user->setName($name);
                $user->setSurname($surname);
                $user->setEmail($email);
                $user->setRole('ROLE_USER');
                $user->setCreatedAt(new DateTimeImmutable('now'));
                $user->setUpdatedAt(new DateTimeImmutable('now'));

                //Cifrar password
                $pwd = hash('sha256', $password);
                $user->setPassword($pwd);
            
              
                $user_repo = $this->doctrine->getRepository(Users::class);
                $isset_user = $user_repo->findBy(array(
                    'email' => $email
                ));
                if (count($isset_user) == 0) {
                  
                    $this->doctrine->persist($user);
                    $this->doctrine->flush();
                   
                    $data = [
                        'status' => 'succes',
                        'code' => 200,
                        'message' => 'Usuario creado correctamente',
                        'user' => $user
                    ];
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'El usuario ya existe'
                    ];
                }
            } else {
                $data = [
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Revise los datos, datos no validos'
                ];
            }
        } else {
            $data = [
                'status' => 'error',
                'code' => 200,
                'message' => 'No se reciben datos'
            ];
        }



        return $this->resjson($data);
    }

    public function login(Request $request, JwtAuth $jwt){
        $json= $request->get('json',null);
        $params = json_decode($json);
        if ($json != null) {
            $email = (!empty($params->email)) ? $params->email : null;
            $password = (!empty($params->password)) ? $params->password : null;
            $gettoken = (!empty($params->gettoken)) ? $params->gettoken : null;
           
            $validator = Validation::createValidator();
            $validate_email = $validator->validate($email, [
                new Email()
            ]);
            if(count($validate_email) == 0 && $email != null && $password != null){
                  $pwd= hash('sha256',$password);
             
                if($gettoken){
                    $signup= $jwt->signup($email,$pwd,$gettoken);

                    $data = [
                        'status' => 'success',
                        'code' => 200,
                      'token' => $signup
                    ];
                }else{
                    $signup= $jwt->signup($email,$pwd);
                    $data = [
                        'status' => 'success',
                        'code' => 200,
                      'user' => $signup
                    ];
                }

            }else {
                $data = [
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'Revise los datos, datos no validos'
                ];
            }
        }else {
            $data = [
                'status' => 'error',
                'code' => 200,
                'message' => 'No se reciben datos'
            ];
        }

        return $this->json($data);
    }

    public function edit(Request $request, JwtAuth $jwt){
         $token = $request->headers->get('Authorization');
        
        if($jwt->checkToken($token)){

            $identity=$jwt->checkToken($token,true);

            $user_repo = $this->doctrine->getRepository(Users::class);
            $user= $user_repo->findOneBy([
                'id' => $identity->sub,
            ]);
          
            $json= $request->get('json', null);
            $params= json_decode($json);

            
            if ($json != null) {
                $name = (!empty($params->name)) ? $params->name : null;
                $surname = (!empty($params->surname)) ? $params->surname : null;
                $email = (!empty($params->email)) ? $params->email : null;
              
                $validator = Validation::createValidator();
                $validate_email = $validator->validate($email, [
                    new Email()
                ]);
                if ($name != null && $surname != null && $email != null  && count($validate_email) == 0) {
    
                   
                  
                    $isset_user= $user_repo->findBy([
                        'email' => $email,
                    ]);
        
                    if(count($isset_user) == 0 || $identity->email == $email){
                        $user->setName($name);
                        $user->setSurname($surname);
                        $user->setEmail($email);
                        $user->setUpdatedAt(new DateTimeImmutable('now'));

                        $this->doctrine->persist($user);
                        $this->doctrine->flush();

                        $data = [
                            'status' => 'success',
                            'code' => 200,
                            'UserUpdated' => $user
                        ];
                    }else{
                        $data = [
                            'status' => 'error',
                            'code' => 200,
                            'message' => 'Usuario duplicado'
                        ];
                    }


                }else {
                    $data = [
                        'status' => 'error',
                        'code' => 200,
                        'message' => 'Revise los datos, datos no validos'
                    ];
                }
            }else {
                $data = [
                    'status' => 'error',
                    'code' => 200,
                    'message' => 'No se reciben datos'
                ];
            }


        }else{
            $data = [
                'status' => 'error',
                'code' => 200,
               'message' => 'El token es incorrecto'
            ];
        }

     
         return  $this->resjson($data);
    }
}
