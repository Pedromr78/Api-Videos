<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Users;
use App\Entity\Videos;
use App\Repository\VideosRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Constraints\Email;
use DateTimeImmutable;
use App\Service\JwtAuth;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Serializer\SerializerInterface;


class VideoController extends AbstractController
{
    public EntityManagerInterface $doctrine;

    public SerializerInterface $serialize;

    public function __construct(
        private EntityManagerInterface $doctrines,
        private SerializerInterface $serializes,

    ) {
        $this->doctrine = $doctrines;
        $this->serialize = $serializes;
    }


    public function resjson($data)
    {
        $json = $this->serialize->serialize($data, 'json');

        $response = new Response();
        $response->setContent($json);
        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }

    public function add(Request $request, JwtAuth $jwt, $id = null)
    {
        $token = $request->headers->get('Authorization');

        if ($jwt->checkToken($token)) {
            $json = $request->get('json');
            $params = json_decode($json);
            $identity = $jwt->checkToken($token, true);

            if ($json != null) {
                $user_id = ($identity->sub != null) ? $identity->sub : null;
                $title = ($params->title != null) ? $params->title : null;
                $description = ($params->description != null) ? $params->description : null;
                $url = ($params->url != null) ? $params->url : null;

                if (!empty($user_id) && !empty($title)) {



                    $user_repo = $this->doctrine->getRepository(Users::class);
                    $user = $user_repo->findOneBy([
                        'id' => $user_id
                    ]);

                    if (is_object($user)) {

                        if ($id == null) {
                            $video = new Videos();
                            $video->setUserId($user);
                            $video->setTitle($title);
                            $video->setDescription($description);
                            $video->setUrl($url);
                            // $video->setStatus('normal');
                            $video->setCreatedAt(new DateTimeImmutable('now'));
                            $video->setUpdatedAt(new DateTimeImmutable('now'));

                            $this->doctrine->persist($video);
                            $this->doctrine->flush();

                            $data = [
                                'status' => 'succes',
                                'code' => 200,
                                'message' => 'Video guardado correctamente',
                                $video
                            ];
                        } elseif ($id != null) {

                            $video_repo = $this->doctrine->getRepository(Videos::class);
                            $video = $video_repo->findOneBy(
                                [
                                    'id' => $id,
                                    'user_id' => $identity->sub
                                ]
                            );
                            if (is_object($video) && $video->getUserId()->getId() == $user->getId()) {

                                $video->setTitle($title);
                                $video->setDescription($description);
                                $video->setUrl($url);
                                $video->setUpdatedAt(new DateTimeImmutable('now'));

                                $this->doctrine->persist($video);
                                $this->doctrine->flush();

                                $data = [
                                    'status' => 'succes',
                                    'code' => 200,
                                    'videoUpdated' => $video

                                ];
                            } else {

                                $data = [
                                    'status' => 'error',
                                    'code' => 400,
                                    'message' => 'No tienes acceso a este contenido'
                                ];
                            }
                        }
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'Error al encontrar el usuario'
                        ];
                    }
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'Faltan datos por añadir'
                    ];
                }
            } else {
                $data = [
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'No se reciben datos'
                ];
            }
        } else {
            $data = [
                'status' => 'error',
                'code' => 400,
                'message' => 'El token es incorrecto'
            ];
        }




        return $this->json($data);
    }



    public function videos(Request $request, JwtAuth $jwt, PaginatorInterface $paginator)
    {
        $token = $request->headers->get('Authorization');

        if ($jwt->checkToken($token)) {
            // $json = $request->get('json');
            // $params = json_decode($json);
            $identity = $jwt->checkToken($token, true);



            if ($identity->sub !== null) {
                $user_repo = $this->doctrine->getRepository(Users::class);

                $user = $user_repo->findOneBy([
                    'id' => $identity->sub
                ]);

                if (is_object($user)) {



                    $dql = "SELECT v FROM App\Entity\Videos v WHERE v.user_id = {$identity->sub} ORDER BY v.id DESC";
                    $query = $this->doctrine->createQuery($dql);
                    $page = $request->query->getInt('page', 1);
                    $items_per_page = 5;

                    $pagination = $paginator->paginate($query, $page, $items_per_page);
                    $total = $pagination->getTotalItemCount();

                    if ($pagination != null) {

                        $data = [
                            'status' => 'succes',
                            'code' => 200,
                            'user' => $user,
                            'total_items' => $total,
                            'pages' => $page,
                            'items_per_page' => $items_per_page,
                            'totalpages' => ceil($total / $items_per_page),
                            'videos' => $pagination

                        ];
                    } else {
                        $data = [
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'El ususrio ' . $identity->name . ' no tiene ningun video en su repositorio'
                        ];
                    }
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'Error al encontrar el usuario'
                    ];
                }
            } else {
                $data = [
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'Faltan datos por añadir'
                ];
            }
        } else {
            $data = [
                'status' => 'error',
                'code' => 400,
                'message' => 'El token es incorrecto'
            ];
        }

        return $this->resjson($data);
    }
    public function video(Request $request, JwtAuth $jwt, $id = null)
    {
        $token = $request->headers->get('Authorization');

        if ($jwt->checkToken($token)) {
            // $json = $request->get('json');
            // $params = json_decode($json);
            $identity = $jwt->checkToken($token, true);



            if ($identity->sub !== null) {
                $user_repo = $this->doctrine->getRepository(Users::class);

                $user = $user_repo->findOneBy([
                    'id' => $identity->sub
                ]);

                if (is_object($user)) {

                    $video_repo = $this->doctrine->getRepository(Videos::class);
                    $video = $video_repo->findOneBy(
                        [
                            'id' => $id
                        ]
                    );

                    if (is_object($video) && $video->getUserId()->getId() == $user->getId()) {



                        $data = [
                            'status' => 'succes',
                            'code' => 200,
                            $video

                        ];
                    } else {

                        $data = [
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'No tienes acceso a este contenido'
                        ];
                    }
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'Error al encontrar el usuario'
                    ];
                }
            } else {
                $data = [
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'Faltan datos por añadir'
                ];
            }
        } else {
            $data = [
                'status' => 'error',
                'code' => 400,
                'message' => 'El token es incorrecto'
            ];
        }

        return $this->resjson($data);
    }
    public function remove(Request $request, JwtAuth $jwt, $id = null)
    {
        $token = $request->headers->get('Authorization');

        if ($jwt->checkToken($token)) {
            // $json = $request->get('json');
            // $params = json_decode($json);
            $identity = $jwt->checkToken($token, true);



            if ($identity->sub !== null) {
                $user_repo = $this->doctrine->getRepository(Users::class);

                $user = $user_repo->findOneBy([
                    'id' => $identity->sub
                ]);

                if (is_object($user)) {


                    $video_repo = $this->doctrine->getRepository(Videos::class);
                    $video = $video_repo->findOneBy(
                        [
                            'id' => $id
                        ]
                    );

                    if (is_object($video) && $video->getUserId()->getId() == $user->getId()) {

                        $this->doctrine->remove($video);
                        $this->doctrine->flush();

                        $data = [
                            'status' => 'succes',
                            'code' => 200,
                            'videoRemoved' => $video

                        ];
                    } else {

                        $data = [
                            'status' => 'error',
                            'code' => 400,
                            'message' => 'No tienes acceso a este contenido'
                        ];
                    }
                } else {
                    $data = [
                        'status' => 'error',
                        'code' => 400,
                        'message' => 'Error al encontrar el usuario'
                    ];
                }
            } else {
                $data = [
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'Faltan datos por añadir'
                ];
            }
        } else {
            $data = [
                'status' => 'error',
                'code' => 400,
                'message' => 'El token es incorrecto'
            ];
        }

        return $this->resjson($data);
    }
}
