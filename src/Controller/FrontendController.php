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
use App\Entity\Invite;

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

            $entityManager = $this->getDoctrine()->getManager();

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

            $invite = $entityManager->getRepository(Invite::class)->find(1);

            if (!$invite){
                return $this->render('register.html.twig', ['msg' => 'Internal error; registration not yet supported.']);
            }

            $inviteKey = $invite->getInviteKey();

            if ($inviteKey !== $inviteTest){
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

            // user creation
            $newUser = new User();

            $newUser->setUsername($userName);
            $newUser->setPassword($encoder->encodePassword($newUser, $plainPassword));
            $newUser->setAccessLevel(1);
            $newUser->setApiKey($this->generateRandomString(20));
            $newUser->setDateAdded(new \DateTime());

            $entityManager->persist($newUser);

            // change invite key
            $invite->setInviteKey($this->generateRandomString(20));

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

      $entityManager = $this->getDoctrine()->getManager();

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



          $dbUser = $entityManager->getRepository(User::class)->findOneBy(['username' => $user->getUsername()]);


          $dbUser->setPassword($encoder->encodePassword($dbUser, $newPwd));

          $entityManager->flush();


          return $this->render('profile.html.twig', ['ustats' => json_decode($userStats->getContent()),
                                                     'msg'=>'Password changed successfully',
                                                     ]);

      } else {
        if($user->getAccessLevel() === 3){

          $invite = $entityManager->getRepository(Invite::class)->find(1);

          if (!$invite){
            $invite = new Invite;
            $invite->setInviteKey($this->generateRandomString(20));
            $entityManager->persist($invite);

          }else{
            $invite->setInviteKey($this->generateRandomString(20));

          }
          $entityManager->flush();

          $inviteKey = $invite->getInviteKey();

          return $this->render('profile.html.twig', ['ustats' => json_decode($userStats->getContent()),
                                                     'i_key' => $inviteKey]);
        }else{
          return $this->render('profile.html.twig', ['ustats' => json_decode($userStats->getContent())]);
        }

      }
    }

    /**
     * @Route("/files/{page}", name="files")
     */
    public function files(Request $request, $page, SessionInterface $session)
    {
        $user = $session->get('user');
        if(!$user){
            return $this->render('files.html.twig', ['page' => 0]);

        }

        // create internal request, use it to POST to api links to git some data
        $intReq = Request::create(
            '',
            'POST',
            ['api_key' => $user->getApiKey()]
        );

        $userStats = $this->forward('App\Controller\ApiController::fetchUser', array('request' => $intReq));
        $userFiles = $this->forward('App\Controller\ApiController::fetchUserFiles', array('request' => $intReq));

        $userFiles = json_decode($userFiles->getContent());
        // get pagelen parameter

        $pageLen = $this->getParameter('number_of_items');

        // basic checks


        if ($page*$pageLen > count($userFiles->files)){
            return $this->redirectToRoute('files', ['page' => 0]);
        }

        $pageAmount = floor(count($userFiles->files)/$pageLen);

        if ($page < 0){
            return $this->redirectToRoute('files', ['page' => $pageAmount]);
        }

        // select page specific files
        if (count($userFiles->files) > $pageLen){
            $userFiles->files = array_slice($userFiles->files, $pageLen*$page, $pageLen);
        }

        return $this->render('files.html.twig', ['ustats' => json_decode($userStats->getContent()),
                                                 'ufiles' => $userFiles,
                                                 'page' => $page,
                                                 'page_amount' => $pageAmount]);
    }

    /**
     * @Route("/files/delete/all", name="delete_all_files")
     */
    public function deleteAllFiles(Request $request, SessionInterface $session)
    {
        $user = $session->get('user');
        if(!$user){
            return $this->redirectToRoute('files', ['page' => 0]);
        }

        // create internal request, use it to POST to api links
        $intReq = Request::create(
            '',
            'POST',
            ['api_key' => $user->getApiKey()]
        );
        $response = $this->forward('App\Controller\ApiController::deleteAllUploads', array('request' => $intReq));
        $referer = $request->headers->get('referer');
        return $this->redirect($referer);


    }

    /**
     * @Route("/files/delete/{id}", name="delete_file")
     */
    public function deleteFile(Request $request, SessionInterface $session, $id)
    {
        $user = $session->get('user');
        if(!$user){
            return $this->redirectToRoute('files', ['page' => 0]);
        }

        // create internal request, use it to POST to api links
        $intReq = Request::create(
            '',
            'POST',
            ['api_key' => $user->getApiKey(),
             'file_id' => $id]
        );

        $response = $this->forward('App\Controller\ApiController::deleteUpload', array('request' => $intReq));

        $referer = $request->headers->get('referer');
        return $this->redirect($referer);

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
     * @Route("/pastes/{page}", name="pastes")
     */
    public function showPastes(Request $request, $page, SessionInterface $session)
    {
        $user = $session->get('user');
        if(!$user){
            return $this->render('pastes.html.twig', ['page' => 0]);

        }

        // create internal request, use it to POST to api links to git some data
        $intReq = Request::create(
            '',
            'POST',
            ['api_key' => $user->getApiKey()]
        );

        $userStats = $this->forward('App\Controller\ApiController::fetchUser', array('request' => $intReq));
        $userPastes = $this->forward('App\Controller\ApiController::fetchUserPastes', array('request' => $intReq));

        $userPastes = json_decode($userPastes->getContent());

        $pageLen = $this->getParameter('number_of_items');

        if ($page*$pageLen > count($userPastes->pastes)){
            return $this->redirectToRoute('pastes', ['page' => 0]);
        }

        $pageAmount = floor(count($userPastes->pastes)/$pageLen);

        if ($page < 0){
            return $this->redirectToRoute('pastes', ['page' => $pageAmount]);
        }

        // select page specific files
        if (count($userPastes->pastes) > $pageLen){
            $userPastes->pastes = array_slice($userPastes->pastes, $pageLen*$page, $pageLen);
        }

        return $this->render('pastes.html.twig', ['ustats' => json_decode($userStats->getContent()),
                                                  'upastes' => $userPastes,
                                                  'page' => $page,
                                                  'page_amount' => $pageAmount]);
    }

    /**
     * @Route("/p/delete/all", name="delete_all_pastes")
     */
    public function deleteAllPastes(Request $request, SessionInterface $session)
    {
      $user = $session->get('user');
      if(!$user){
          return $this->redirectToRoute('pastes', ['page' => 0]);
      }

      // create internal request, use it to POST to api links
      $intReq = Request::create(
          '',
          'POST',
          ['api_key' => $user->getApiKey()]
      );

      $response = $this->forward('App\Controller\ApiController::deleteAllPastes', array('request' => $intReq));

      $referer = $request->headers->get('referer');
      return $this->redirect($referer);

    }

    /**
     * @Route("/p/delete/{id}", name="delete_paste")
     */
    public function deletePaste(Request $request, SessionInterface $session, $id)
    {
      $user = $session->get('user');
      if(!$user){
          return $this->redirectToRoute('pastes', ['page' => 0]);
      }

      // create internal request, use it to POST to api links
      $intReq = Request::create(
          '',
          'POST',
          ['api_key' => $user->getApiKey(),
           'paste_id' => $id]
      );



      $response = $this->forward('App\Controller\ApiController::deletePaste', array('request' => $intReq));

      $referer = $request->headers->get('referer');
      return $this->redirect($referer);

    }

    /**
     * @Route("/p/edit/{id}", name="edit_paste")
     */
    public function editPaste(Request $request, SessionInterface $session, $id)
    {
      $user = $session->get('user');
      if(!$user){
          return $this->redirectToRoute('pastes', ['page' => 0]);
      }

      $pastes = $this->getDoctrine()->getRepository(Paste::class);
      $paste = $pastes->findOneBy(['real_id' => $id]);

      if (!$paste){
          return $this->render('create_paste.html.twig', ['msg' => 'Paste not found.']);
      }

      if ($user->getID() !== $paste->getCorrUid()){
          return $this->render('create_paste.html.twig', ['msg' => 'Paste does not belong to user.']);
      }

      echo 'id:'.$id.'<br>';

      // create internal request, use it to POST to api links

      if ($request->isMethod('post')) {
          $request->request->set('paste_id', $id);

          $response = $this->forward('App\Controller\ApiController::updatePaste');
          $json = json_decode($response->getContent());

          if ($json->success === 'true'){
              return $this->redirect($json->web_link);
          }else{
              return $this->render('edit_paste.html.twig', ['msg' => $json->reason]);
          }



      }else{
          $intReq = Request::create(
              '',
              'POST',
              ['api_key' => $user->getApiKey(),
               'paste_id' => $id]
          );

          $response = $this->forward('App\Controller\ApiController::getPaste', array('request' => $intReq));
          $paste = json_decode($response->getContent());
          return $this->render('edit_paste.html.twig', ['paste' => $paste]);
      }

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
