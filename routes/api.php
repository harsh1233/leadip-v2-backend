<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\API\TeamController;
use App\Http\Controllers\API\{
    AuthController,
    UserController,
    RoleController,
    CountryController,
    CityController,
    LanguageController,
    CompanyController,
    CompanyContactController,
    RegionController,
    ContactListController,
    GlobalFileController,
    NotificationController,
    ProtocolController,
    ReportController,
    ApolloController,
    TradeMarkController
};
/*  */
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

// welcome
Route::get('/', function () {
    return ok('Welcome to leadip-api');
});

// user authanticaion
Route::controller(AuthController::class)->group(function () {
    Route::post('register', 'register');
    Route::post('user-verification', 'userVerification')->name('user-verification');
    Route::post('login', 'login');

    Route::post('forgot-password', 'forgotPassword');
    Route::post('reset-password', 'resetPassword');
    Route::post('check-token', 'checkToken');
    Route::post('check-unique', 'checkUniqueFields');
});

// Roles
Route::controller(RoleController::class)->group(function () {
    Route::group(['prefix' => 'roles'], function () {
        Route::get('list', 'list');
    });
});

// Regions
Route::controller(RegionController::class)->group(function () {
    Route::group(['prefix' => 'regions'], function () {
        Route::get('list', 'list');
    });
});

Route::controller(UserController::class)->group(function () {
    Route::group(['prefix' => 'user'], function () {
        Route::post('get-details', 'getDetails');
    });
});
//



Route::middleware('auth-sanctum-custome')->group(function () {

    Route::controller(AuthController::class)->group(function () {
        Route::post('logout', 'logout');
        Route::post('change-password', 'changePassword');
        Route::post('get-company-detail', 'getCompanyDetail');
    });

    // Country
    Route::controller(CountryController::class)->group(function () {
        Route::group(['prefix' => 'country'], function () {
            Route::get('list', 'list');
        });
    });

    // City
    Route::controller(CityController::class)->group(function () {
        Route::group(['prefix' => 'city'], function () {
            Route::post('list', 'list');
        });
    });


    // Language
    Route::controller(LanguageController::class)->group(function () {
        Route::group(['prefix' => 'language'], function () {
            Route::post('list', 'list');
        });
    });

    // User - Onboarding Steps - Your Company
    Route::controller(UserController::class)->group(function () {
        Route::group(['prefix' => 'company'], function () {
            Route::post('about-company-update', 'aboutCompanyUpdate');
            Route::post('add-team', 'addTeam');
            Route::post('create-password', 'createPassword')->name('create-password')->withoutMiddleware(['auth-sanctum-custome']);
            Route::post('sync-contact', 'enterLeadIp');
            Route::post('enter-leadip', 'enterLeadIp');
            Route::post('update-onboard-status', 'updateOnboardStatus');
            Route::post('user-profile', 'userProfile');
            Route::post('user-contacts-and-channels', 'contactsAndChannels');
            Route::post('user-locations', 'userLocations');
            Route::post('user-languages', 'userLanguages');
            Route::post('user-areas-of-expertise', 'userAreasOfExpertise');
            Route::post('user-interested', 'userInterested');
            Route::post('add-preference', 'addPreference');
            Route::post('list-industries', 'listIndustry');
            Route::post('user-preference', 'userPreference');
            Route::post('update-preference', 'updatePreference');
            Route::post('user-certification', 'userCertification');
            Route::get('user-view-profile/{id}', 'userViewProfile');
            Route::post('profile-completion-criteria', 'profileCompletionCriteria');
            Route::post('change-sync-contact', 'syncContactStatus');
            Route::post('update-profile', 'update')->middleware('permission:manage_company_settings_info_my_profile,edit');
        });
        Route::group(['prefix' => 'user'], function () {
            //Route::post('get-details', 'getDetails');
            Route::post('set-first-login', 'setFirstLogin');
        });
    });

    // User > Team
    Route::controller(TeamController::class)->group(function () {
        Route::group(['prefix' => 'team'], function () {
            Route::post('add-member', 'addMember')->middleware('role:Super Admin|Admin');
            Route::post('list-members', 'listMembers');
            Route::post('update-member/{id}', 'updateMember')->middleware('role:Super Admin|Admin');
            Route::post('delete-member', 'deleteMember')->middleware(['role:Super Admin', 'permission:manage_company_settings_info_team,remove']);
            Route::post('update-role/{id}', 'updateRole')->middleware('role:Super Admin|Admin');
            Route::post('create-password/{id}', 'createPassword');
            Route::post('view-member/{id}', 'viewMember');
            Route::post('get-member', 'getMember');
            Route::post('active-member', 'activeMember');
            Route::post('all-members', 'allMembers');
        });
    });

    // Company profile
    Route::controller(CompanyController::class)->group(function () {
        Route::group(['prefix' => 'company'], function () {
            Route::post('description', 'description');
            Route::post('contacts-and-channels', 'contactsAndChannels');
            Route::post('office', 'companyOffice');
            Route::post('languages', 'companyLanguages');
            Route::post('expertises', 'companyExpertises');
            Route::post('services', 'companyServices');
            Route::post('regions', 'companyRegions');
            Route::post('certifications', 'companyCertifications');
            Route::post('meet', 'companyMeet');
            Route::get('view-profile/{id}', 'companyViewProfile');
            Route::post('update', 'update')->middleware(['role:Super Admin|Admin', 'permission:manage_company_settings_info_my_company,edit']);
        });
    });

    // Company profile
    Route::controller(CompanyContactController::class)->group(function () {
        Route::group(['prefix' => 'contact'], function () {
            Route::post('create', 'create')->middleware('permission:manage_contacts|manage_prospects|manage_clients,add');
            Route::post('update', 'update')->middleware('permission:manage_contacts|manage_prospects|manage_clients,edit');
            Route::post('update-priority', 'updatePriority')->middleware('permission:manage_contacts|manage_prospects|manage_clients,edit');
            Route::post('addToArchive', 'softDelete')->middleware('permission:manage_contacts|manage_prospects|manage_clients,edit');
            Route::post('listArchive', 'listArchive')->middleware('permission:manage_contacts|manage_prospects|manage_clients,edit');
            Route::post('listArchiveNew', 'listArchiveNew')->middleware('permission:manage_contacts|manage_prospects|manage_clients,edit');
            Route::post('restoreArchive', 'restoreArchive')->middleware('permission:manage_contacts|manage_prospects|manage_clients,edit');
            Route::post('delete', 'destroy')->middleware('permission:manage_contacts|manage_prospects|manage_clients,edit');
            Route::post('list', 'list')->middleware('permission:manage_contacts|manage_prospects|manage_clients,view');
            Route::post('list-new', 'listNew')->middleware('permission:manage_contacts|manage_prospects|manage_clients,view');
            Route::post('assign', 'assign')->middleware('permission:manage_contacts|manage_prospects|manage_clients,assign');
            Route::post('export', 'export')->middleware('permission:manage_contacts|manage_prospects|manage_clients,export');
            Route::post('import', 'import')->middleware('permission:manage_contacts|manage_prospects|manage_clients,upload');
            Route::post('importcompany', 'importcompany')->middleware('permission:manage_contacts|manage_prospects|manage_clients,upload');
            Route::get('get', 'downloadfile');
            Route::get('getcompanyimport', 'getcompanyimport');
            Route::post('move', 'move')->middleware('permission:manage_contacts|manage_prospects|manage_clients,move');
            Route::get('view', 'view')->middleware('permission:manage_contacts|manage_prospects|manage_clients,view');
            Route::post('share', 'share')->middleware('permission:manage_contacts|manage_prospects|manage_clients,share');
            Route::post('listemail', 'listemail');
            Route::post('allmylist', 'allmylist');
            Route::post('filter', 'filter');
            Route::post('getpeople', 'getpeople');
            Route::post('getCompanyContact', 'getCompanyContact');
            Route::post('company-team', 'companyTeam')->middleware('permission:manage_teams,view');
        });
    });

    /*ContactList Controller for list module */
    Route::controller(ContactListController::class)->group(function () {
        Route::group(['prefix' => 'contact-list'], function () {
            Route::post('list',             'list')->middleware('permission:manage_lists,view');
            Route::post('store',            'store')->middleware('permission:manage_lists,add');
            Route::post('update/{id}',      'update')->middleware('permission:manage_lists,edit');
            Route::post('delete/{id?}',     'delete')->middleware('permission:manage_lists,delete');
            Route::post('assign',           'assign')->middleware('permission:manage_lists,assign');
            Route::post('add-to-list',     'addToList');
            Route::post('merge',            'merge');
            Route::post('move-list',       'moveList')->middleware('permission:manage_lists,move');
            Route::post('list-contact',    'listContact')->middleware('permission:manage_list_detail,view');
            Route::post('change-type-list', 'changeTypeList');
            Route::post('get-list',         'getList');
            Route::post('assign-contact',   'assignContact')->middleware('permission:manage_list_detail,assign');
            Route::post('edit-contact',     'editContact')->middleware('permission:manage_list_detail,edit');
            Route::post('move-contact',     'moveContact')->middleware('permission:manage_list_detail,move');;
            Route::post('remove-contact',   'removeContact')->middleware('permission:manage_list_detail,remove');
            Route::post('list-to-prospect', 'listToProspect');
            Route::post('filter', 'filter');
        });
    });

    /*Globalfile controller for global file upload */
    Route::controller(GlobalFileController::class)->group(function () {
        Route::group(['prefix' => 'global-files'], function () {
            Route::post('newfolder', 'newfolder');
            Route::post('store', 'store')->middleware('permission:manage_files,upload');
            Route::post('list', 'list')->middleware('permission:manage_files,view');
            Route::post('listfiles', 'listfiles')->middleware('permission:manage_files,view');
            Route::post('delete', 'delete')->middleware('permission:manage_files,delete');
            Route::post('deleteglobalfiles', 'deleteglobalfiles')->middleware('permission:manage_files,delete');
            Route::post('deletfolderfiles', 'deletfolderfiles');
        });
        Route::group(['prefix' => 'note'], function () {
            Route::post('store', 'addNote')->middleware('permission:manage_notes,add');
            Route::post('list', 'listNote')->middleware('permission:manage_notes,view');
            Route::post('view',  'viewNote')->middleware('permission:manage_notes,view');;
            Route::post('edit', 'editNote')->middleware('permission:manage_notes,edit');
            Route::post('store-type', 'storeNoteType');
            Route::post('list-type', 'listNoteType');
        });
    });
    /*Notification Controller for notifications */

    Route::controller(NotificationController::class)->group(function () {
        Route::group(['prefix' => 'notification'], function () {
            Route::post('list', 'list');
            Route::post('read/{id?}', 'read');
            Route::post('clear', 'clear');
            Route::post('unread-count', 'unreadCount');
        });
    });

    Route::controller(ProtocolController::class)->group(function () {
        Route::group(['prefix' => 'protocol'], function () {
            Route::post('list', 'list');
        });
    });
    /* Set Reports route */
    Route::controller(ReportController::class)->group(function () {
        Route::group(['prefix' => 'report-contact'], function () {
            Route::post('count', 'contactCount');
            Route::post('count-new', 'contactCountNew');
            Route::post('percentage', 'contactPercentage');
            Route::post('percentage-new', 'contactPercentageNew');
            Route::post('base-count', 'contactBaseCount');
            Route::post('base-count-new', 'contactBaseCountNew');
            Route::post('country-count', 'contactcountryCount');
            Route::post('import', 'import');
        });
    });

    /* Enrich route */
    Route::controller(ApolloController::class)->group(function () {
        Route::group(['prefix' => 'contact'], function () {
            Route::post('/enrich', 'enrichContacts');
            Route::post('/search-country', 'getTradmarksUsingCompany');
        });
    });

    /* TradeMark route */
    Route::controller(TradeMarkController::class)->group(function () {
        Route::group(['prefix' => 'trademark'], function () {
            Route::post('/list', 'listTradeMarks');
            Route::post('/holders-list', 'getTradeMarkHolderInfo');
            Route::post('/holders-count', 'getTradeMarkHolderCount');
        });
    });
});
