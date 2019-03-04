<?php
// src/Controller/FrontendController.php
namespace App\Controller;

use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;

use App\Controller\ApiController;

use App\Entity\File;
use App\Entity\User;
use App\Entity\Paste;

class FrontendController extends AbstractController
{
    
    // invite key generation
    function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    
    
    
    
    
    /**
     * @Route("/", name="index")
     */
    public function index(SessionInterface $session)
    {
        
        return $this->render('index.html.twig');
    }
    /**
     * @Route("/rice", name="rice")
     */
    public function rice()
    {
        return $this->render('rice.html.twig');
    }
    /**
     * @Route("/upload", name="upload")
     */
    public function upload(SessionInterface $session)
    {
        $request = Request::createFromGlobals();
        $stats = $this->forward('App\Controller\ApiController::fetchStats');
        $statData = json_decode($stats->getContent(), true);
        
        if ($request->isMethod('post')) {
            
        }else{
            $userName = $session->get('username');
            
            if (!$userName){
                return $this->render('upload.html.twig', ['stats'=>$statData]);
            }else{
                $users = $this->getDoctrine()->getRepository(User::class);

                $user = $users->findOneBy(['username' => $userName]);
                if (!$user){
                    return $this->render('upload.html.twig', ['stats'=>$statData]);
                }
                else{
                    return $this->render('upload.html.twig', ['stats'=>$statData, 'user'=>$user]);
                }
            }
            
        }
    }
    /**
     * @Route("/login", name="login")
     */
    public function login(SessionInterface $session, UserPasswordEncoderInterface $encoder)
    {
        $request = Request::createFromGlobals();
        if ($request->isMethod('post')) {
            //basic unencrypted check for now
            
            $userName = $request->request->get('username');
            $passWord = $request->request->get('password');
            
            $users = $this->getDoctrine()->getRepository(User::class);
            
            $user = $users->findOneBy(['username' => $userName]);
            
            if (!$user){
                return $this->render('login.html.twig', ['msg'=>'No user found.']);
            }
            
            if (!$encoder->isPasswordValid($user, $passWord)){
                return $this->render('login.html.twig', ['msg'=>'Incorrect password.']);
            }
            
            $session->start();
            
            $session->set('user', $user);
            
            return $this->redirectToRoute('index');
            
        }else{
            return $this->render('login.html.twig');
            
            
        }
    }
    
    /**
     * @Route("/logout", name="logout")
     */
    public function logout(SessionInterface $session)
    {
        if ($session){
            $session->invalidate();
        }
        
        return $this->redirectToRoute('index');
    }
    
    /**
     * @Route("/register", name="register")
     */
    public function register(UserPasswordEncoderInterface $encoder)
    {
        $request = Request::createFromGlobals();
        if ($request->isMethod('post')) {
            // registration
            
            $userName = $request->request->get('username');
            $plainPassword = $request->request->get('password');
            $inviteTest = $request->request->get('invite_key');
            
            
            // checking for input
            
            if (!$userName){
                return $this->render('register.html.twig', ['msg' => 'No username provided.']);
            }
            
            if (!$plainPassword){
                return $this->render('register.html.twig', ['msg' => 'No password provided.']);
            }
            
            if (!$inviteTest){
                return $this->render('register.html.twig', ['msg' => 'No invite key provided.']);
            }
            
            if ('lolmao' !== $inviteTest){
                return $this->render('register.html.twig', ['msg' => 'Invalid invite key.']);
            }
            
            // all checks done, everything is alright
            $newUser = new User();
            
            $newUser->setUsername($userName);
            $newUser->setPassword($encoder->encodePassword($newUser, $plainPassword));
            $newUser->setAccessLevel(1);
            $newUser->setApiKey($this->generateRandomString(20));
            $newUser->setDateAdded(new \DateTime());
            
            $entityManager = $this->getDoctrine()->getManager();
            
            $entityManager->persist($newUser);
            $entityManager->flush();
            
            return $this->render('register.html.twig', ['msg' => 'Successfully registered.']);
            
        }else{
            return $this->render('register.html.twig');
            
            
        }
    }



}
?>
