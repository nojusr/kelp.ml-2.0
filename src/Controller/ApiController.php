<?php
// src/Controller/ApiController.php
namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use App\Entity\File;
use App\Entity\User;
use App\Entity\Paste;

class ApiController extends AbstractController
{

    public function human_filesize($bytes, $dec = 2) //
    {
        $size   = array('b', 'kb', 'mb', 'gb', 'tb', 'pb', 'eb', 'zb', 'yb');
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }

    /**
     * @Route("/api/upload", name="file_upload")
     */
    public function upload() // JSON-only API upload route.
    {
        $request = Request::createFromGlobals();
        $apiKey = $request->request->get('api_key');
        $uFile = $request->files->get('u_file');
        $users = $this->getDoctrine()->getRepository(User::class);

        $user = $users->findOneBy(['api_key' => $apiKey]);
        
        $fileSize = $uFile->getClientSize();


        // error handling
        if (!$fileSize) {
            return $this->json(['success' => 'false', 'reason' => 'Uploaded file is empty']);
        }

        if (!$uFile) {
            return $this->json(['success' => 'false', 'reason' => 'No file provided or filesize too large']);
        }

        if (!$user) {
            return $this->json(['success' => 'false', 'reason' => 'No matching API key found']);
        }


        $fileName = explode('.', $uFile->getClientOriginalName());
        $realName = $fileName[0];
        $fileType = implode('.', array_slice($fileName, 1));

        $allowedFiles = $this->getParameter('allowed_filetypes');
        $allowedFiles = explode(',', $allowedFiles);

        // check if file is allowed
        foreach ($allowedFiles as $allowedFile) {
            if ($fileType === $allowedFile) {
                
                // everything is alright beyond this point, carry on uploading
                $entityManager = $this->getDoctrine()->getManager();
                $dbFile = new File(); // file entry in database

                $dbFile->setCorrUid($user->getID());
                $dbFile->setFiletype($fileType);
                $dbFile->setOrgFilename($realName);

                // set all other essential data
                $entityManager->persist($dbFile);
                $entityManager->flush();

                // now that we got it's id, we can generate an actual filename
                $fileId = strval($dbFile->getID() + 50000);
                $fileId = base_convert($fileId, 10, 36);
                $dbFile->setFilename($fileId);

                $entityManager->persist($dbFile);
                $entityManager->flush();

                // saving
                $finalName = $fileId.'.'.$dbFile->getFiletype();
                $uFile->move($this->getParameter('upload_directory'), $finalName);
                $host = $request->getSchemeAndHttpHost();
                // json output
                return $this->json(['success' => 'true',
                                    'filesize' => $this->human_filesize($fileSize),
                                    'file_id' => $fileId,
                                    'filename' => $finalName,
                                    'link' => $host.'/u/'.$finalName]);
            }
        }

        return $this->json(['success' => 'false', 'reason' => 'Unsupported or unallowed filetype']);
    }

    /**
     * @Route("/api/upload/delete", name="file_delete")
     */
    public function upload_delete() // JSON-only API upload deletion route.
    {
        $request = Request::createFromGlobals();
        $apiKey = $request->request->get('api_key');
        $fileId = $request->request->get('file_id');
        $users = $this->getDoctrine()->getRepository(User::class);

        $user = $users->findOneBy(['api_key' => $apiKey]);
        
        // error handling
        if (!$fileId) {
            return $this->json(['success' => 'false', 'reason' => 'No file ID provided']);
        }

        if (!$user) {
            return $this->json(['success' => 'false', 'reason' => 'No matching API key found']);
        }
        
        $files = $this->getDoctrine()->getRepository(File::class);
        
        $file = $files->findOneBy(['corr_uid' => $user->getID(), 'filename' => $fileId]);
        
        if (!$file){
            return $this->json(['success' => 'false', 'reason' => 'File not found']);
        }
        
        // from this point forward, everything should be in order
        
        // deleting from db
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($file);
        $entityManager->flush();
        
        // deleting from fs
        $fs = new Filesystem(); 
        $fs->remove($this->getParameter('upload_directory').'/'.$file->getFilename().'.'.$file->getFiletype());
        
        return $this->json(['success' => 'true']);
        
        
    }

    /**
     * @Route("/api/paste", name="paste_upload")
     */
    public function paste() // JSON-only API paste route.
    {
        $request = Request::createFromGlobals();
        $apiKey = $request->request->get('api_key');
        $uPaste = $request->request->get('u_paste');
        $pasteName = $request->request->get('paste_name');
        $users = $this->getDoctrine()->getRepository(User::class);

        $user = $users->findOneBy(['api_key' => $apiKey]);
        
        if (!$user) {
            return $this->json(['success' => 'false', 'reason' => 'No matching API key found']);
        }
        
        if (!$uPaste) {
            return $this->json(['success' => 'false', 'reason' => 'Paste text wasn\'t provided']);
        }
        
        $paste = new Paste();
        
        $paste->setCorrUid($user->getID());
        
        if (!$pasteName) {
            $paste->setPasteName("null");
        }
        else {
            $paste->setPasteName($pasteName);
        }
        
        $paste->setPasteText($uPaste);
        
        
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($paste);
        $entityManager->flush(); 
        
        // commit the object to the db once, get it's db ID, calculate 
        // it's link ID, commit it again with the link ID
        $realId = strval($paste->getID() + 200);
        $realId = base_convert($realId, 10, 36);
        $paste->setRealId($realId);
        
        $entityManager->persist($paste);
        $entityManager->flush();
        
        $host = $request->getSchemeAndHttpHost();
        
        return $this->json(['success' => 'true', 
                            'api_link' => $host.'/api/p/'.$realId,
                            'web_link' => $host.'/p/'.$realId]);
        
    }

    /**
     * @Route("/api/p", name="paste_get")
     */
    public function getPaste() // Get paste via POST.
    {
        $request = Request::createFromGlobals();
        $pasteId = $request->request->get('paste_id');



        
        $pastes = $this->getDoctrine()->getRepository(Paste::class);
        
        $paste = $pastes->findOneBy(['real_id' => $pasteId]);
        
        if (!$paste){
            return $this->json(['success' => 'false', 'reason' => 'Paste not found']);
        }
        
        return $this->json(['success' => 'true',
                            'paste_name' => $paste->getPasteName(),
                            'paste_text' => $paste->getPasteText()]);
        
        
    }

    /**
     * @Route("/api/paste/delete", name="paste_delete")
     */
    public function deletePaste() // JSON-only API paste deletion route.
    {
        $request = Request::createFromGlobals();
        $apiKey = $request->request->get('api_key');
        $pasteId = $request->request->get('paste_id');

        $users = $this->getDoctrine()->getRepository(User::class);

        $user = $users->findOneBy(['api_key' => $apiKey]);
        
        if (!$user) {
            return $this->json(['success' => 'false', 'reason' => 'No matching API key found']);
        }
        
        $pastes = $this->getDoctrine()->getRepository(Paste::class);
        
        $paste = $pastes->findOneBy(['corr_uid' => $user->getID(), 'real_id' => $pasteId]);
        
        if (!$paste){
            return $this->json(['success' => 'false', 'reason' => 'Paste not found']);
        }
        
        // deleting from db
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->remove($paste);
        $entityManager->flush();       
        
        return $this->json(['success' => 'true']); 
        
    }
    // API DEFINTION:
    // all links that are designed to return JSON, and are designed to interface with various programs
    // begin with /api/
    //
    // all links that serve publicly accessibale data are prefixed with /api/get
    // all links that serve private data OR do private functions are prefixed with /api/get
    //
    // POST LINKS: 
    // post file: /api/upload
    // post paste: /api/paste
    // get file: /u/file.extension
    // get paste (in a nice format): /p/PASTEID
    // get paste (in raw format): /p/raw/PASTEID
    // get paste (in JSON): /api/p
    // doing pastes....
    // uploading seems p simple, but how should i implement paste recieving?
    // should i do two different routes? 
    
    //
    
    
}
?>
