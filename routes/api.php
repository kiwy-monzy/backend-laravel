<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\ContentController;
use App\Http\Controllers\Api\DonationController;
use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\MailController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\VolunteerController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth.api')->group(function () {
    Route::post('/Me', [AuthController::class, 'me']);
    Route::post('/Logout', [AuthController::class, 'logout']);
    Route::post('/UpdateProfile', [AuthController::class, 'updateProfile']);
    Route::post('/UpdateProfileImage', [AuthController::class, 'updateProfileImage']);
    Route::post('/ListUsers', [AuthController::class, 'listUsers']);
    Route::post('/UpdateUser', [AuthController::class, 'updateUser']);
    Route::post('/DeleteUser', [AuthController::class, 'deleteUser']);

    Route::post('/UpdateSection', [ContentController::class, 'updateSection']);
    Route::post('/UpdateAll', [ContentController::class, 'updateAll']);

    Route::post('/CreateGalleryImage', [GalleryController::class, 'create']);
    Route::post('/UpdateGalleryImage', [GalleryController::class, 'update']);
    Route::post('/DeleteGalleryImage', [GalleryController::class, 'delete']);

    Route::post('/ListDonations', [DonationController::class, 'list']);
    Route::post('/UpdateDonation', [DonationController::class, 'update']);
    Route::post('/DeleteDonation', [DonationController::class, 'delete']);

    Route::post('/ListVolunteers', [VolunteerController::class, 'list']);
    Route::post('/UpdateVolunteer', [VolunteerController::class, 'update']);
    Route::post('/DeleteVolunteer', [VolunteerController::class, 'delete']);

    Route::post('/SaveMailConfig', [MailController::class, 'saveConfig']);
    Route::post('/GetMailConfig', [MailController::class, 'getConfig']);
    Route::post('/DeleteMailConfig', [MailController::class, 'deleteConfig']);
    Route::post('/FetchMails', [MailController::class, 'fetchMails']);
    Route::post('/ListMailFolders', [MailController::class, 'listFolders']);
    Route::post('/SendMail', [MailController::class, 'sendMail']);

    Route::post('/ListUploads', [UploadController::class, 'list']);
    Route::post('/UploadFile', [UploadController::class, 'create']);
    Route::post('/DeleteUpload', [UploadController::class, 'delete']);

    Route::post('/ListMessages', [MessageController::class, 'list']);
    Route::post('/UpdateMessage', [MessageController::class, 'update']);
    Route::post('/DeleteMessage', [MessageController::class, 'delete']);

    Route::post('/BackupNow', [BackupController::class, 'now']);
});

// Registration is deliberately absent. Accounts are created by a system
// admin (System → Users) or by an organization owner for their own team —
// there is no self-service sign-up, so there is no endpoint for one.
Route::post('/Login', [AuthController::class, 'login']);
Route::post('/GetWebsite', [ContentController::class, 'getWebsite']);
Route::post('/ListGallery', [GalleryController::class, 'list']);
Route::post('/CreateDonation', [DonationController::class, 'create']);
Route::post('/CreateVolunteer', [VolunteerController::class, 'create']);
Route::post('/CreateMessage', [MessageController::class, 'create']);