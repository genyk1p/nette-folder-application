"use strict";
import naja from 'naja';
document.addEventListener('DOMContentLoaded', function() {

    naja.initialize({
        history: false
    });

        const form = document.getElementById('addForm');
        const inputData = document.getElementById('add-form-input-data');
        const hiddenInputPath = document.getElementById('add-form-path');
        const postUrl = form.getAttribute('action');
        const renameSubmitBtn = document.getElementById('rename-submit-btn');
        const renameFormInputData = document.getElementById('rename-form-input-data');
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
        const renameModal = new bootstrap.Modal(document.getElementById('renameFormModal'));
        const renameWarningModal = new bootstrap.Modal(document.getElementById('renameWarningModal'));
        const renameWarninText = document.getElementById('renameWarningModal').querySelector('.modal-body');
        const renameWarnBtn = document.getElementById('rename-warn-btn');
        let folderNameFoDelete = '';
        let folderNameFoRename = '';
        let currentPath = 'baseFolder/';
        let currentFolders = [];


        // Sends a POST request from the form with the id "addForm" to the server via AJAX to add a folder.
        const sendFormData = () => {
            if (inputData.value !== ""){
                naja.makeRequest("POST", postUrl, new FormData(form))
                    .then((payload) => {
                        renderPath(currentPath);
                        renderFolder(currentPath);
                    });
            }
            inputData.value = "";
        };

        // Sending a DELETE request to delete a folder.
        const sendDeleteRequest = (folderName) => {
            const originalUrl = document.getElementById('getDeleteUrl').textContent;
            const urlParts = originalUrl.split('?');
            const baseUrl = urlParts[0];
            const deleteUrl = `${baseUrl}?name=${folderName}&path=${currentPath}`;
            naja.makeRequest("DELETE", deleteUrl)
                .then((payload) => {
                    renderPath(currentPath);
                    renderFolder(currentPath);
                });
            inputData.value = "";
        };

         // Sending data for folder renaming to the server using the PUT method.
         const sendRenameFormmData = () => {
            const renameFormInputData = document.getElementById('rename-form-input-data');
            const originalUrl = document.getElementById('getRenameUrl').textContent;
            const urlParts = originalUrl.split('?');
            const baseUrl = urlParts[0];
            const renameUrl = `${baseUrl}?newName=${renameFormInputData.value}&oldName=${folderNameFoRename}&path=${currentPath}`; 
            naja.makeRequest('PUT', renameUrl)
                .then((payload) => {
                    renderPath(currentPath);
                    renderFolder(currentPath);
                    renameModal.hide();
                });
            renameFormInputData.value = "";
        };

        // Attaches an event handler to the delete button in the modal window, which contains the folder deletion function and hides the modal window.
        const deleteButton = document.getElementById("delet-btn");
        deleteButton.addEventListener('click', () => {
            const folderName = folderNameFoDelete;
            folderNameFoDelete = '';
            deleteModal.hide();
            sendDeleteRequest(folderName);
            });

        // Renders folders depending on the provided path.
        const renderFolder = (path) => {
            const getUrl = document.getElementById('getUrl').textContent;
            const folderDiv = document.querySelector('.folders');
            const url = window.location.origin + getUrl
            const urlWithParams = new URL(url);
            let foldersNames = [];
            urlWithParams.searchParams.append('path', path);
            naja.makeRequest('GET', urlWithParams.toString())
                .then((payload) => {
                    if (payload.snippets && payload.snippets.foldersName) {
                        foldersNames = payload.snippets.foldersName;
                        currentFolders = foldersNames;
                        folderDiv.innerHTML = '';
                        foldersNames.forEach(item => {
                            folderDiv.innerHTML += `<div class="folder btn btn-primary btn-lg" >
                                                        ${item}
                                                        <img class="trash_img" src="img/red-trash-can-icon.svg">
                                                        <img class="rename_img" src="img/rename-icon.svg">   
                                                    </div>`;
                        })
                    }
                });
        };

        // Renders the path depending on the provided path.
        const renderPath = (path) => {
            const pathOl = document.getElementById("breadcrumb_path");
            const arrPath = path.split("/").filter(Boolean);
            pathOl.innerHTML = '';
            let currentPath = '';
            arrPath.forEach(folder => {
                currentPath += folder + "/";
                pathOl.innerHTML += `<li class="breadcrumb-item active"><a href=${currentPath} class="folder_link">${folder}</a></li>`;
            });
            pathOl.lastElementChild.classList.remove("active");
        }
        
        renderPath(currentPath);
        renderFolder(currentPath);

        // Event handler for the submission of the new folder creation form.
        form.addEventListener('submit', function (e) {
            hiddenInputPath.value = currentPath;
            inputData.value = inputData.value.trim();
            e.preventDefault();
            sendFormData();
        });

        // Attaching an event handler to the "Rename" button in the modal window with the renaming form.
        renameSubmitBtn.addEventListener('click', () => {
            const enteredFolderName = renameFormInputData.value.trim();
            if(enteredFolderName === ""){
                renameFormInputData.classList.add("is-invalid");
            } else if(currentFolders.includes(enteredFolderName)){
                renameModal.hide();
                renameWarninText.innerHTML = `This directory already has a folder named <span class="fw-bold">${enteredFolderName}</span>, when you click the "rename" button, the folder you want to rename will be deleted, and all its subfolders will be moved to the <span class="fw-bold">${enteredFolderName}</span> folder`;
                renameWarningModal.show();
            } else {
                sendRenameFormmData();
            }
        })

        // Attaching an event handler to the button in the modal window with a warning that there is already a folder with the same name in our directory, which we want to rename our folder to.
        renameWarnBtn.addEventListener('click', () => {
            sendRenameFormmData();
            renameWarningModal.hide();
        })

        // Event handler for the window that tracks clicks on folders, clicks on paths, and clicks on icons for delete, rename, and performs the necessary logic.
        window.addEventListener('click', (e) => {
            if(e.target && e.target.classList.contains("folder") && !e.target.classList.contains("trash_img") && !e.target.classList.contains("rename_img")){
                currentPath += e.target.textContent.trim() + '/';
                renderPath(currentPath);
                renderFolder(currentPath);
            } else if(e.target && e.target.classList.contains("folder_link") ){
                e.preventDefault();
                currentPath = e.target.getAttribute('href')
                renderPath(currentPath);
                renderFolder(currentPath);
            } else if(e.target && e.target.classList.contains("trash_img" )){
                const folderName = e.target.parentElement.textContent.trim();
                const spanInModal = document.getElementById('folder_name_in_span');
                spanInModal.innerHTML = '"' + folderName + '"';
                deleteModal.show();
                folderNameFoDelete = folderName;
            }else if(e.target && e.target.classList.contains("rename_img" )){
                renameFormInputData.classList.remove("is-invalid");
                renameFormInputData.value = '';
                folderNameFoRename = e.target.parentElement.textContent.trim();
                renameModal.show();
            };
          });
});
