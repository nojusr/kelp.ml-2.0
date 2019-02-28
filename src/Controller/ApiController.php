<?php
// src/Controller/ApiController.php
namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
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

    public function human_filesize($bytes, $dec = 2) // used for generating a human readable size from bytes
    {
        $size   = array('b', 'kb', 'mb', 'gb', 'tb', 'pb', 'eb', 'zb', 'yb');
        $factor = floor((strlen($bytes) - 1) / 3);

        return sprintf("%.{$dec}f", $bytes / pow(1024, $factor)) . @$size[$factor];
    }
    
    function get_file_type($file) { // gets file MIME type by checking it's magic value.
        if(function_exists('shell_exec') === TRUE) {
            $dump = shell_exec(sprintf('file -bi %s', escapeshellarg($file)));
            $info = explode(';', $dump);
            return $info[0];
        }
            return FALSE;
    }
    
    function get_media_extension($mimeType) { // used for changing the mimetype of media files, in order to not fool the end user
        $mimeMap = [ ' image/png' => 'png',
                     ' image/jpg' => 'jpg',
                     ' image/jpeg' => 'jpeg',    
                     ' image/tiff' => 'tiff',
                     ' image/gif' => 'gif',
                     ' audio/opus'=> 'opus',
                     ' audio/webm' => 'webm',
                     ' audio/flac' => 'flac',
                     ' audio/mpeg' => 'mp3',
                     ' video/webm' => 'webm',
                     ' video/mp4' => 'mp4'
                   ];
                   
        return isset($mimeMap[$mimeType]) === true ? $mimeMap[$mimeType] : false;
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
        
        // getting both the magic value mimetype and the mimetype provided by the client
        $fileMime = ' '.$this->get_file_type($uFile);
        $clientMime = ' '.$uFile->getMimeType();
        
        // more error checking
        
        if ($fileMime !== $clientMime) {
            return $this->json(['success' => 'false', 'reason' => 'Magic value MIME type does not match the MIME type provided by the client']);
        }
        
        if ($fileMime === 'text/plain' && $fileType !== 'txt') {
            return $this->json(['success' => 'false', 'reason' => 'Unsupported or unallowed filetype']);
        }
        
        $mediaType = $this->get_media_extension($fileMime);
        
        if ($mediaType !== false){
            $fileType = $mediaType;
        }
                
        $allowedFiles = $this->getParameter('allowed_filetypes');
        $allowedFiles = explode(',', $allowedFiles);

        // check if file is allowed
        foreach ($allowedFiles as $allowedFile) {
            if ($fileMime === $allowedFile) {
                
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
    
    /**
     * @Route("/api/fetch/user", name="fetch_user")
     */
    public function fetchUser() // fetch user info.
    {
        $request = Request::createFromGlobals();
        $apiKey = $request->request->get('api_key');

        $users = $this->getDoctrine()->getRepository(User::class);

        $user = $users->findOneBy(['api_key' => $apiKey]);
        
        if (!$user) {
            return $this->json(['success' => 'false', 'reason' => 'No matching API key found']);
        }
        
        $pastes = $this->getDoctrine()->getRepository(Paste::class);
        $files = $this->getDoctrine()->getRepository(File::class);
        
        $userPastes = $pastes->findBy(['corr_uid' => $user->getID()]);
        $userFiles = $files->findBy(['corr_uid' => $user->getID()]);
        
        $totalFileSize = 0;
        $pasteCount = 0;
        $fileCount = 0;
        
        $fs = new Filesystem(); 
        
        foreach ($userPastes as $paste){
            $pasteCount += 1;
        }
        foreach ($userFiles as $file){
            $totalFileSize += filesize($this->getParameter('upload_directory').'/'.$file->getFilename().'.'.$file->getFiletype());
            $fileCount += 1;
        }
        
        return $this->json(['success' => 'true',
                            'paste_count' => $pasteCount,
                            'file_count' => $fileCount,
                            'total_filesize' => $this->human_filesize($totalFileSize)]); 
        
    }
    
    /**
     * @Route("/api/fetch/stats", name="fetch_stats")
     */
    public function fetchStats() // fetch global info.
    {
        $pastes = $this->getDoctrine()->getRepository(Paste::class);
        $files = $this->getDoctrine()->getRepository(File::class);
        
        $allPastes = $pastes->findAll();
        $allFiles = $files->findAll();
        
        $totalFileSize = 0;
        $pasteCount = 0;
        $fileCount = 0;
        
        $fs = new Filesystem(); 
        
        foreach ($allPastes as $paste){
            $pasteCount += 1;
        }
        foreach ($allFiles as $file){
            $totalFileSize += filesize($this->getParameter('upload_directory').'/'.$file->getFilename().'.'.$file->getFiletype());
            $fileCount += 1;
        }
        
        return $this->json(['success' => 'true',
                            'paste_count' => $pasteCount,
                            'file_count' => $fileCount,
                            'total_filesize' => $this->human_filesize($totalFileSize)]); 
        
    }    
      
    /**
     * @Route("/api/fetch/files", name="fetch_user_files")
     */
    public function fetchUserFiles() // fetch user files.
    {
        $request = Request::createFromGlobals();
        $apiKey = $request->request->get('api_key');

        $users = $this->getDoctrine()->getRepository(User::class);

        $user = $users->findOneBy(['api_key' => $apiKey]);
        
        if (!$user) {
            return $this->json(['success' => 'false', 'reason' => 'No matching API key found']);
        }
        
        $files = $this->getDoctrine()->getRepository(File::class);

        $userFiles = $files->findBy(['corr_uid' => $user->getID()]);
        
        $outputInfo = array();
        
        foreach ($userFiles as $file){
            $outputInfo[] = array(
                'org_filename' => $file->getOrgFilename(),
                'filename' => $file->getFilename(),
                'filetype' => $file->getFiletype()
            );
        }
        
        return $this->json(['success' => 'true',
                            'files' => $outputInfo]); 
        
    }
    
    /**
     * @Route("/api/fetch/pastes", name="fetch_user_pastes")
     */
    public function fetchUserPastes() // fetch user pastes.
    {
        $request = Request::createFromGlobals();
        $apiKey = $request->request->get('api_key');

        $users = $this->getDoctrine()->getRepository(User::class);

        $user = $users->findOneBy(['api_key' => $apiKey]);
        
        if (!$user) {
            return $this->json(['success' => 'false', 'reason' => 'No matching API key found']);
        }
        
        $pastes = $this->getDoctrine()->getRepository(Paste::class);

        $userPastes = $pastes->findBy(['corr_uid' => $user->getID()]);
        
        $outputInfo = array();
        
        foreach ($userPastes as $paste){
            $outputInfo[] = array(
                'id' => $paste->getRealId(),
                'paste_name' => $paste->getPasteName(),
            );
        }
        

        return $this->json(['success' => 'true',
                            'pastes' => $outputInfo]); 
        
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
    
    // WORK FOR TOMORRROW
    // /fetch/user -- get user data (in JSON), show amount of files uploaded, total filesize, time joined.
    // /fetch/user/files -- get all files of user
    // /fetch/user/pastes -- get all pastes of user
    // /fetch/stats -- get global website stats (amount of users, total amount of files, that kind of thing)
}
?>
