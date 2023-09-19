<?php

declare(strict_types=1);

namespace App\Presenters;

use Nette;

final class HomePresenter extends Nette\Application\UI\Presenter
{
    public function __construct(
		private Nette\Database\Explorer $database,
	) {
	}
    // Method that adds folders.
    public function actionAdd(){
        $data = $this->getRequest()->getPost();

        $folders = $this->database
                ->table('folders')
                ->where('BINARY name', $data['name'])
                ->where('BINARY path', $data['path']);
            
        if (count($folders) === 0 ){

            $pathArr = array_filter(explode('/', $data['path']));
            $deep = count($pathArr);

            $this->database->table('folders')->insert([
                'name' => $data['name'],
                'path' => $data['path'],
                'deep' => $deep,
            ]);
        }
        $result = 'Данные успешно получены: ' . $data['name'];
        $this->sendJson(['snippets' => ['result' => $result]]);
    }
    
    // Method that deletes folders.
    public function actionDelete(string $name, string $path){
        $pathForDelete = $path.$name.'/';

        // Before deleting a folder, remove all its attachments.
        $removeAttachments = $this->database
            ->table('folders')
            ->where('BINARY path LIKE ?', $pathForDelete . '%')
            ->delete();

        // Delete the folder.
        $result  = $this->database
            ->table('folders')
            ->where('BINARY name', $name)
            ->where('BINARY path', $path)
            ->delete();

        if ($result > 0){
            $message = 'Папка с именем: ' . $name . ' была успешно удалена';
        } else {
            $message = 'Папка с именем: ' . $name . ' не была успешно удалена';
        }

        $this->sendJson(['snippets' => ['result' => $message]]);
    }

// This method is used for renaming folders. I implemented this method in a way that if, during the renaming of a folder (Name1), there is already a folder
// with the same name (Name2) in the current directory,then all nested folders (Name1) will be moved to the folder (Name2). If the names of the nested 
// folders coincide with the names of the nested folders in folder (Name2), they will be renamed.Also, the paths of all renamed nested folders will be 
// updated to the correct paths.

// In reality, this might not be the best practice, and in a real project, I would likely prohibit renaming folders with matching names.
// In this project, I implemented the folder renaming algorithm only to demonstrate my ability to do so.

    public function actionRename(string $newName, string $oldName, string $path){
        // Определяем на каком уровне вложенности идет запрос на переименование
        $getCurntFolder = $this->database
                ->table('folders')
                ->where('BINARY name', $oldName)
                ->where('BINARY path', $path)
                ->fetch();
        $deep = $getCurntFolder->deep;

        // We attempt to select a record from the database with a combination of name=newName and path=path to determine whether such a record already exists in the database or not.
        $checkAttachments = $this->database
            ->table('folders')
            ->where('BINARY name', $newName)
            ->where('BINARY path', $path);

        // If there is no record in the database with name=newName and path=path, we simply rename our record. However, 
        // if such a record exists, we delete the record with the old name from the database.
        if (count($checkAttachments) === 0){
            $getOldFolderAttachments = $this->database
                ->table('folders')
                ->where('BINARY name', $oldName)
                ->where('BINARY path', $path)
                ->fetch();
            if ($getOldFolderAttachments){
                $getOldFolderAttachments->update(['name' => $newName]);
            }
        } else {
            $dleteOldFolderAttachments  = $this->database
                ->table('folders')
                ->where('BINARY name', $oldName)
                ->where('BINARY path', $path)
                ->delete();
        }

        // Here begins the work with attachments. All attachments that were inside the folder we renamed or deleted need to have their path updated.

        $oldPathForRename = $path.$oldName.'/';
        $newPathForRename = $path.$newName.'/';
        $oldPathLength = mb_strlen($oldPathForRename);

        // We select all the attached folders from the database.
        $renameAttachments = $this->database
            ->table('folders')
            ->where('BINARY path LIKE ?', $oldPathForRename . '%');

        // We create an array to store folders that will need to have their paths updated again after the main logic,
        // as the reason for doing this will become clear later in the code.
        $attachmentFolderArr = [];    

        // When renaming, there could be two scenarios: either there was a folder with the same name at the same level of the hierarchy,
        // or there wasn't. In either case, we need to update the paths of the child folders. The logic below will handle both scenarios.

        foreach ($renameAttachments as $attachment){
            $currentPath = $newPathForRename . substr($attachment->path, $oldPathLength); // The new path that will be set for all child folders.
            $oldName1 = $attachment->name;
            $currentName1 = $attachment->name;

            // If our current nesting level is 1 greater than the nesting level of the folder we are renaming, we need to ensure that
            // if there was a folder with the same name as the one we want to rename our folder to during the renaming process, 
            // the names of the child folders do not match the names of the folders that were inside our folder before renaming.

            // If these names match, we need to generate new names for the folders that were originally inside the folder with a +1 nesting level.
            // The loop below is responsible for this task. The loop will continue running until it finds a new unique name.
            $counter = 0;
            if($attachment->deep == $deep+1){
                while (true){
                    $cursor = $this->database
                        ->table('folders')
                        ->where('BINARY name', $currentName1)
                        ->where('BINARY path', $newPathForRename)
                        ->fetch();
                        if(!$cursor){
                            break;
                        }else{
                            if ($counter === 0){
                                $currentName1 = $currentName1.'_copy';
                            }else{
                                $currentName1 = $currentName1.$counter;
                            }
                        }
                    $counter++;
                }
            }

            // If a name match is found in the loop, the loop counter will change, indicating that there was a name match, and we need 
            // to take additional actions for the attachment that received a new name. These attachments may also have their own nested attachments,
            // and their paths will need to be modified again.

            // In the code that follows, we find and create new paths for these nested attachments and add them to 
            // the `$attachmentFolderArr` array, as I mentioned above.
            if($counter > 0){
                $currentPath1 = $attachment->path.$oldName1;
                $currentPathArr = array_filter(explode('/', $currentPath));
                array_push($currentPathArr, $currentName1);
                $cursor = $this->database
                    ->table('folders')
                    ->where('BINARY path LIKE ?', $currentPath1 . '%');
                forEach ($cursor as $attachment1){
                    $attachFolderPathArr = array_filter(explode('/', $attachment1->path));
                    $attachFolderPathArrEnd = array_slice($attachFolderPathArr, count($currentPathArr));
                    $newAttachFolderPathArr = array_merge($currentPathArr, $attachFolderPathArrEnd);
                    $newAttchFolderPath = implode('/', $newAttachFolderPathArr).'/';
                    $resArr = ["id"=> $attachment1->id, "path" => $newAttchFolderPath];
                    array_push($attachmentFolderArr, $resArr);
                }
            }
            
            // Making changes to the database
            $attachment->update(['path' => $currentPath, 'name' => $currentName1]);
        }

        // Here we iterate through our loop of attachments. For the folders that received new names,
        // we also update their paths, which we previously added to our $attachmentFolderArr array.
        foreach ($attachmentFolderArr as $item) {
            $id = $item['id'];
            $path = $item['path'];
        
            $this->database->table('folders')
                ->where('id', $id)
                ->update(['path' => $path]);
        }

        $message = 'Запрос на переименование отправлен';
        $this->sendJson(['snippets' => ['result' => $message]]);
    }

    // This method selects folders based on the provided path and folder name.
    public function actionGet(){
        $request = $this->getRequest();
        $params = $request->getParameters();
        $path = $params['path'];

        if (isset($path)){
            $foldersNamesArr = $this->database
                ->table('folders')
                ->where('BINARY path', $path)
                ->fetchAll();

            $foldersNames = array_map(function($folder) {
                return $folder['name'];
            }, $foldersNamesArr);

            $this->sendJson(['snippets' => ['foldersName' => array_values($foldersNames)]]);
        }
    }
}
