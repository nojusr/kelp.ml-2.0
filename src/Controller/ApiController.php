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
     * @Route("/api/upload", name="api_file_upload")
     */
    public function upload(Request $request) // JSON-only API upload route.
    {
        $apiKey = $request->request->get('api_key');
        $uFile = $request->files->get('u_file');
        $users = $this->getDoctrine()->getRepository(User::class);

        $user = $users->findOneBy(['api_key' => $apiKey]);
        
        


        // error handling


        if (!$uFile) {
            return $this->json(['success' => 'false', 'reason' => 'No file provided or filesize too large']);
        }

        if (!$user) {
            return $this->json(['success' => 'false', 'reason' => 'No matching API key found']);
        }
        
        $fileSize = $uFile->getClientSize();

        if (!$fileSize) {
            return $this->json(['success' => 'false', 'reason' => 'Uploaded file is empty']);
        }


        $fileName = explode('.', $uFile->getClientOriginalName());
        $realName = $fileName[0];
        $fileType = implode('.', array_slice($fileName, 1));
        
        // getting both the magic value mimetype and the mimetype provided by the client
        $fileMime = ' '.$this->get_file_type($uFile);
        $clientMime = ' '.$uFile->getMimeType();
        
        // more error checking
        
        // if client says its ' text/plain', change it's extention to .txt.

        if (substr( $fileMime, 0, 5 ) === ' text' || substr( $clientMime, 0, 5 ) === ' text') {
            // changing some variables to bypass the error checker below
            $fileMime = ' text/plain';
            $clientMime = ' text/plain';
            $fileType = 'txt';
        }        

        if ($fileMime !== $clientMime) {
            return $this->json(['success' => 'false', 'reason' => 'Magic value MIME type does not match the MIME type provided by the client', 'clientmime' => $clientMime, 'filemime' => $fileMime]);
        }


        
        $mediaType = $this->get_media_extension($fileMime);
        
        if ($mediaType !== false){
            $fileType = $mediaType;
        }
                
        $allowedFiles = $this->getParameter('allowed_filetypes');
        $allowedFiles = explode(',', $allowedFiles);

        // check if file is allowed
        if(in_array($fileMime,$allowedFiles)){
                
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

        return $this->json(['success' => 'false', 'reason' => 'Unsupported or unallowed filetype']);
    }

    /**
     * @Route("/api/upload/delete/all", name="api_file_delete_all")
     */
    public function deleteAllUploads(Request $request) // JSON-only API all upload deletion route.
    {
        //WARNING: CONFIRM BEFORE RUNNING THIS
        $apiKey = $request->request->get('api_key');
        $users = $this->getDoctrine()->getRepository(User::class);

        $user = $users->findOneBy(['api_key' => $apiKey]);
        
        // error handling
        if (!$user) {
            return $this->json(['success' => 'false', 'reason' => 'No matching API key found']);
        }
        
        
        
        $files = $this->getDoctrine()->getRepository(File::class);
        
        $allUserFiles = $files->findBy(['corr_uid' => $user->getID()]);
        
        if (!$allUserFiles){
            return $this->json(['success' => 'false', 'reason' => 'User has no files']);
        }
        
        // from this point forward, everything should be in order
        
        $entityManager = $this->getDoctrine()->getManager();
        $fs = new Filesystem(); 
        
        
        foreach ($allUserFiles as $file){
                // deleting from db
                
                $entityManager->remove($file);

                
                // deleting from fs
                $fs = new Filesystem(); 
                $fs->remove($this->getParameter('upload_directory').'/'.$file->getFilename().'.'.$file->getFiletype());
        
        
        }
        
        $entityManager->flush();        
        return $this->json(['success' => 'true']);
    }

    /**
     * @Route("/api/upload/delete", name="api_file_delete")
     */
    public function deleteUpload(Request $request) // JSON-only API upload deletion route.
    {
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
        
        
        // cleaning up ID to be purely alphanumeric, just for good measure
         $fileId = preg_replace('/[^a-z\d ]/i', '', $fileId);
        
        
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
     * @Route("/api/paste", name="api_paste_upload")
     */
    public function paste(Request $request) // JSON-only API paste route.
    {
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
     * @Route("/api/p", name="api_paste_get")
     */
    public function getPaste(Request $request) // Get paste via POST.
    {
        $pasteId = $request->request->get('paste_id');

        // cleaning up ID to be purely alphanumeric, just for good measure
        $pasteId = preg_replace('/[^a-z\d ]/i', '', $pasteId);
        
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
     * @Route("/api/paste/delete", name="api_paste_delete")
     */
    public function deletePaste(Request $request) // JSON-only API paste deletion route.
    {
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
     * @Route("/api/fetch/user", name="api_fetch_user_stats")
     */
    public function fetchUser(Request $request) // fetch user info.
    {
        $apiKey = $request->request->get('api_key');

        $users = $this->getDoctrine()->getRepository(User::class);

        $user = $users->findOneBy(['api_key' => $apiKey]);
        
        if (!$user) {
            return $this->json(['success' => 'false', 'reason' => 'No matching API key found', 'test' => $apiKey]);
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
     * @Route("/api/fetch/stats", name="api_fetch_stats")
     */
    public function fetchStats() // fetch global info.
    {
        $users = $this->getDoctrine()->getRepository(User::class);    
        $pastes = $this->getDoctrine()->getRepository(Paste::class);
        $files = $this->getDoctrine()->getRepository(File::class);
        
        $allPastes = $pastes->findAll();
        $allFiles = $files->findAll();
        $allUsers = $users->findAll();
        
        $totalFileSize = 0;
        $pasteCount = count($allPastes);
        $fileCount = count($allFiles);
        $userCount = count($allUsers);
        $fs = new Filesystem(); 
        
        $tmp = 0;
        
        foreach ($allFiles as $file){
            $filePath = $this->getParameter('upload_directory').'/'.$file->getFilename().'.'.$file->getFiletype();
            if (file_exists($filePath)){
                $totalFileSize += filesize($filePath);
            }
        }
        
        return $this->json(['success' => 'true',
                            'paste_count' => $pasteCount,
                            'file_count' => $fileCount,
                            'user_count' => $userCount,
                            'total_filesize' => $this->human_filesize($totalFileSize)]); 
        
    }    
      
    /**
     * @Route("/api/fetch/files", name="api_fetch_user_files")
     */
    public function fetchUserFiles(Request $request) // fetch user files.
    {
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
     * @Route("/api/fetch/pastes", name="api_fetch_user_pastes")
     */
    public function fetchUserPastes(Request $request) // fetch user pastes.
    {
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
    // /fetch/user -- get user data (in JSON), show amount of files uploaded, total filesize, time joined.
    // /fetch/user/files -- get all files of user
    // /fetch/user/pastes -- get all pastes of user
    // /fetch/stats -- get global website stats (amount of users, total amount of files, that kind of thing)
}
?>
