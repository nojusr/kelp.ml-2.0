<?php
// src/Controller/AdminController.php
namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\HttpFoundation\Response;

use App\Controller\ApiController;

use App\Entity\File;
use App\Entity\User;
use App\Entity\Paste;
use App\Entity\Invite;

class AdminController extends AbstractController
{
    /**
     * @Route("/admin", name="admin")
     */
    public function admin(SessionInterface $session)
    {
        $user = $session->get('user');
        if(!$user){
            return $this->render('admin.html.twig', ['msg' => 'Not logged in.']);

        }

        if ($user->getAccessLevel() !== 3){
            return $this->render('admin.html.twig', ['msg' => 'No admin privileges.']);
        }

        $users = $this->getDoctrine()->getRepository(User::class);
        $allUsers = $users->findAll();


        return $this->render('admin.html.twig', ['users' => $allUsers]);


    }


}
