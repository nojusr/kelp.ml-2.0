<?php
// src/Controller/FrontendController.php
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
     * @Route("/upload", name="upload")
     */
    public function upload(Request $request, SessionInterface $session)
    {
        $stats = $this->forward('App\Controller\ApiController::fetchStats');
        $statData = json_decode($stats->getContent(), true);

        if ($request->isMethod('post')) {
            $response = $this->forward('App\Controller\ApiController::upload');
            $json = json_decode($response->getContent());

            if ($json->success === 'true'){
                return $this->render('upload.html.twig', ['stats'=>$statData, 'link'=>$json->link]);
            }else{
                return $this->render('upload.html.twig', ['stats'=>$statData, 'msg'=>$json->reason]);
            }


        }else{
            $user = $session->get('user');
            if (!$user){
                return $this->render('upload.html.twig', ['stats'=>$statData]);
            }else{
                return $this->render('upload.html.twig', ['stats'=>$statData, 'user'=>$user]);
            }

        }
    }
    /**
     * @Route("/login", name="login")
     */
    public function login(Request $request, SessionInterface $session, UserPasswordEncoderInterface $encoder)
    {
        if ($request->isMethod('post')) {

            $userName = $request->request->get('username');
            $passWord = $request->request->get('password');

            if (!$userName || !$passWord){
                return $this->render('login.html.twig', ['msg'=>'Please fill out all fields.']);
            }

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
    public function register(Request $request, UserPasswordEncoderInterface $encoder)
    {
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

            // invite key checking
            // TODO: regenerate invite key upon startup, also every time somebody registers
            if ('lolmao' !== $inviteTest){
                echo $this->inviteKey.'<br>';
                return $this->render('register.html.twig', ['msg' => 'Invalid invite key.']);
            }

            // check if username already exists

            $users = $this->getDoctrine()->getRepository(User::class);

            $user = $users->findOneBy(['username' => $userName]);

            if ($user){
                return $this->render('register.html.twig', ['msg' => 'User already exists.']);
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

    /**
     * @Route("/profile", name="view_profile")
     */
    public function viewProfile(Request $request, SessionInterface $session, UserPasswordEncoderInterface $encoder)
    {
      $user = $session->get('user');
      if(!$user){
          return $this->redirectToRoute('index');
      }

      // create internal request, use it to POST to api links
      $intReq = Request::create(
          '',
          'POST',
          ['api_key' => $user->getApiKey()]
      );

      $userStats = $this->forward('App\Controller\ApiController::fetchUser', array('request' => $intReq));

      if ($request->isMethod('post')) {
          // password reset

          $oldPwd = $request->request->get('old_pwd');
          $newPwd = $request->request->get('new_pwd');
          $newPwdConfirm = $request->request->get('new_pwd_confirm');
          if ($newPwd !== $newPwdConfirm){
            return $this->render('profile.html.twig', ['ustats' => json_decode($userStats->getContent()),
                                                       'msg' => 'New passwords do not match.']);
          }

          if (!$encoder->isPasswordValid($user, $oldPwd)){
              return $this->render('profile.html.twig', ['ustats' => json_decode($userStats->getContent()),
                                                         'msg'=>'Incorrect password.']);
          }

          // all good from this point onward

          $entityManager = $this->getDoctrine()->getManager();

          $dbUser = $entityManager->getRepository(User::class)->findOneBy(['username' => $user->getUsername()]);


          $dbUser->setPassword($encoder->encodePassword($dbUser, $newPwd));

          $entityManager->flush();


          return $this->render('profile.html.twig', ['ustats' => json_decode($userStats->getContent()),
                                                     'msg'=>'Password changed successfully',
                                                     ]);

      } else {
        if($user->getAccessLevel() === 3){
          $this->inviteKey = $this->generateRandomString(20);
          return $this->render('profile.html.twig', ['ustats' => json_decode($userStats->getContent()),
                                                     'i_key' => $this->inviteKey]);
        }else{
          return $this->render('profile.html.twig', ['ustats' => json_decode($userStats->getContent())]);
        }

      }
    }


    /**
     * @Route("/files", name="files")
     */
    public function files(Request $request, SessionInterface $session)
    {
        $user = $session->get('user');
        if(!$user){
            return $this->render('files.html.twig');

        }

        // create internal request, use it to POST to api links to git some data
        $intReq = Request::create(
            '',
            'POST',
            ['api_key' => $user->getApiKey()]
        );

        $userStats = $this->forward('App\Controller\ApiController::fetchUser', array('request' => $intReq));
        $userFiles = $this->forward('App\Controller\ApiController::fetchUserFiles', array('request' => $intReq));
        return $this->render('files.html.twig', ['ustats' => json_decode($userStats->getContent()),
                                                 'ufiles' => json_decode($userFiles->getContent())]);
    }

    /**
     * @Route("/files/delete/all", name="delete_all_files")
     */
    public function deleteAllFiles(Request $request, SessionInterface $session)
    {
        $user = $session->get('user');
        if(!$user){
            return $this->redirectToRoute('files');
        }

        // create internal request, use it to POST to api links
        $intReq = Request::create(
            '',
            'POST',
            ['api_key' => $user->getApiKey()]
        );
        $response = $this->forward('App\Controller\ApiController::deleteAllUploads', array('request' => $intReq));
        return $this->redirectToRoute('files');


    }

    /**
     * @Route("/files/delete/{id}", name="delete_file")
     */
    public function deleteFile(Request $request, SessionInterface $session, $id)
    {
        $user = $session->get('user');
        if(!$user){
            return $this->redirectToRoute('files');
        }

        // create internal request, use it to POST to api links
        $intReq = Request::create(
            '',
            'POST',
            ['api_key' => $user->getApiKey(),
             'file_id' => $id]
        );

        $response = $this->forward('App\Controller\ApiController::deleteUpload', array('request' => $intReq));

        return $this->redirectToRoute('files');

    }

    /**
     * @Route("/p/{id}", name="view_paste")
     */
    public function viewPaste(Request $request, $id)
    {
        // create internal request, use it to POST to api links
        $intReq = Request::create(
            '',
            'POST',
            ['paste_id' => $id]
        );
        $paste = $this->forward('App\Controller\ApiController::getPaste', array('request' => $intReq));
        return $this->render('view_paste.html.twig', ['paste' => json_decode($paste->getContent())]);

    }

    /**
     * @Route("/p/raw/{id}", name="view_raw_paste")
     */
    public function viewRawPaste(Request $request, $id)
    {
        // create internal request, use it to POST to api links
        $intReq = Request::create(
            '',
            'POST',
            ['paste_id' => $id]
        );
        $paste = $this->forward('App\Controller\ApiController::getPaste', array('request' => $intReq));
        $paste = json_decode($paste->getContent());

        $response = new Response(
            $paste->paste_text,
            Response::HTTP_OK,
            ['content-type' => 'text/plain']
        );

        return $response;

    }




    /**
     * @Route("/paste", name="create_paste")
     */
    public function createPaste(Request $request)
    {
        if ($request->isMethod('post')) {
            $response = $this->forward('App\Controller\ApiController::paste');
            $json = json_decode($response->getContent());

            if ($json->success === 'true'){
                return $this->redirect($json->web_link);
            }else{
              return $this->render('create_paste.html.twig', ['msg' => $paste->reason]);
            }

        }else{
            return $this->render('create_paste.html.twig');
        }
    }

    /**
     * @Route("/pastes", name="pastes")
     */
    public function showPastes(Request $request, SessionInterface $session)
    {
        $user = $session->get('user');
        if(!$user){
            return $this->render('pastes.html.twig');

        }

        // create internal request, use it to POST to api links to git some data
        $intReq = Request::create(
            '',
            'POST',
            ['api_key' => $user->getApiKey()]
        );

        $userStats = $this->forward('App\Controller\ApiController::fetchUser', array('request' => $intReq));
        $userPastes = $this->forward('App\Controller\ApiController::fetchUserPastes', array('request' => $intReq));
        return $this->render('pastes.html.twig', ['ustats' => json_decode($userStats->getContent()),
                                                 'upastes' => json_decode($userPastes->getContent())]);
    }

    /**
     * @Route("/p/delete/all", name="delete_all_pastes")
     */
    public function deleteAllPastes(Request $request, SessionInterface $session)
    {
      $user = $session->get('user');
      if(!$user){
          return $this->redirectToRoute('pastes');
      }

      // create internal request, use it to POST to api links
      $intReq = Request::create(
          '',
          'POST',
          ['api_key' => $user->getApiKey()]
      );

      $response = $this->forward('App\Controller\ApiController::deleteAllPastes', array('request' => $intReq));

      return $this->redirectToRoute('pastes');

    }

    /**
     * @Route("/easter", name="change_color")
     */
    public function changeColor(SessionInterface $session)
    {
        $session->start();

        if ($session->get('color')){
            $session->remove('color');
        }else{
            $session->set('color', 'white');
        }
        return $this->redirectToRoute('index');
    }

}
?>
